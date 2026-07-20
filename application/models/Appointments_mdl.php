<?php if( !defined('BASEPATH') ){ exit(); }

class Appointments_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getCountsForRange( $start, $end ){

		$sql = 'select date(date_sub(a.start_datetime, interval 1 hour)) as appt_date, count(*) as total
		        from appointments a
		        where a.start_datetime < '.$this->db->escape($end).'
		        and a.end_datetime > '.$this->db->escape($start).'
		        group by appt_date';

		return $this->db->query( $sql )->result();
	}

	public function getForDay( $date ){

		$window_start = $date . ' 01:00:00';
		$window_end   = date( 'Y-m-d H:i:s', strtotime($date.' +1 day 01:00:00') );

		$sql = 'select a.id_appointment, a.start_datetime, a.end_datetime, a.notes, a.id_client, a.id_employee,
		               concat(c.name_client, " ", c.surname_client) as client_name,
		               group_concat(p.name order by p.name separator ", ") as services,
		               group_concat(p.id_product order by p.name separator ",") as service_ids
		        from appointments a
		        join clients c on c.id_client = a.id_client
		        left join appointment_services aps on aps.id_appointment = a.id_appointment
		        left join products p on p.id_product = aps.id_product
		        where a.start_datetime < '.$this->db->escape($window_end).'
		        and a.end_datetime > '.$this->db->escape($window_start).'
		        group by a.id_appointment
		        order by a.start_datetime asc';

		return $this->db->query( $sql )->result();
	}

	public function getAppointment( $id_appointment ){

		return $this->db->get_where( 'appointments', array( 'id_appointment' => $id_appointment ) )->row();
	}

	public function getActiveServices(){

		$sql = 'select p.id_product, p.name, p.id_service_category,
		               sc.name_category
		        from products p
		        left join service_category sc on sc.id_service_category = p.id_service_category
		        where p.is_service = 1 and p.active = 1
		        order by sc.name_category asc, p.name asc';

		return $this->db->query( $sql )->result();
	}

	public function countActiveServices( array $id_products ){

		if( empty($id_products) ){
			return 0;
		}

		$ids = implode(',', array_map('intval', $id_products));

		$sql = 'select count(*) as total from products where is_service = 1 and active = 1 and id_product in ('.$ids.')';

		return (int) $this->db->query( $sql )->row('total');
	}

	public function save( $id_client, $id_admin, $id_employee, $start_datetime, $end_datetime, $notes, array $id_products ){

		$this->db->trans_start();

		$this->db->insert( 'appointments', array(
			'id_client'      => $id_client,
			'id_admin'       => $id_admin,
			'id_employee'    => $id_employee,
			'start_datetime' => $start_datetime,
			'end_datetime'   => $end_datetime,
			'notes'          => $notes,
		) );

		$id_appointment = $this->db->insert_id();

		foreach( $id_products as $id_product ){

			$this->db->insert( 'appointment_services', array(
				'id_appointment' => $id_appointment,
				'id_product'     => $id_product,
			) );
		}

		$this->db->trans_complete();

		return $this->db->trans_status() ? $id_appointment : FALSE;
	}

	public function hasOverlap( $id_employee, $start_datetime, $end_datetime, $exclude_id_appointment = 0 ){

		$sql = 'select count(*) as total from appointments
		        where id_employee = '.(int) $id_employee.'
		        and start_datetime < '.$this->db->escape($end_datetime).'
		        and end_datetime > '.$this->db->escape($start_datetime);

		if( $exclude_id_appointment > 0 ){

			$sql .= ' and id_appointment != '.(int) $exclude_id_appointment;
		}

		return (int) $this->db->query( $sql )->row('total') > 0;
	}

	public function getEmployeeDayAppointments( $id_employee, $window_start, $window_end, $exclude_id_appointment = 0 ){

		$sql = 'select id_appointment, start_datetime, end_datetime from appointments
		        where id_employee = '.(int) $id_employee.'
		        and start_datetime < '.$this->db->escape($window_end).'
		        and end_datetime > '.$this->db->escape($window_start);

		if( $exclude_id_appointment > 0 ){

			$sql .= ' and id_appointment != '.(int) $exclude_id_appointment;
		}

		$sql .= ' order by start_datetime asc';

		return $this->db->query( $sql )->result();
	}

	public function updateWithShifts( $id_appointment, $id_client, $id_employee, $start_datetime, $end_datetime, $notes, array $id_products, array $shifts ){

		$this->db->trans_start();

		$this->db->where( 'id_appointment', $id_appointment );
		$this->db->update( 'appointments', array(
			'id_client'      => $id_client,
			'id_employee'    => $id_employee,
			'start_datetime' => $start_datetime,
			'end_datetime'   => $end_datetime,
			'notes'          => $notes,
		) );

		$this->db->delete( 'appointment_services', array( 'id_appointment' => $id_appointment ) );

		foreach( $id_products as $id_product ){

			$this->db->insert( 'appointment_services', array(
				'id_appointment' => $id_appointment,
				'id_product'     => $id_product,
			) );
		}

		foreach( $shifts as $shift ){

			$this->db->where( 'id_appointment', $shift['id_appointment'] );
			$this->db->update( 'appointments', array(
				'start_datetime' => $shift['start_datetime'],
				'end_datetime'   => $shift['end_datetime'],
			) );
		}

		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	public function delete( $id_appointment ){

		$this->db->delete( 'appointments', array( 'id_appointment' => $id_appointment ) );
	}
}
