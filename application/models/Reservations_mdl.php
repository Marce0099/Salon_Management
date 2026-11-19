<?php if( !defined('BASEPATH') ){ exit(); }

class Reservations_mdl extends CI_Model{

	public function get($id){

		return $this->db->where('id_reservation', (int) $id)->get('table_reservations')->row();
	}

	/** Reservations whose start falls on $date (Y-m-d), with table/area labels. */
	public function byDate($date){

		$sql = 'SELECT r.*, d.label AS table_label, a.name AS area_name
				FROM table_reservations r
				JOIN dining_tables d ON d.id_table = r.id_table
				JOIN table_areas a ON a.id_area = d.id_area
				WHERE DATE(r.start_at) = ?
				ORDER BY r.start_at, d.label';

		return $this->db->query($sql, array($date))->result();
	}

	/** True when another active reservation of the table overlaps [start, end). */
	public function overlaps($id_table, $start, $end, $exclude_id = 0){

		$this->db->where('id_table', (int) $id_table)->where('status', 'active')
			->where('start_at <', $end)->where('end_at >', $start);

		if( $exclude_id ){
			$this->db->where('id_reservation !=', (int) $exclude_id);
		}

		return $this->db->count_all_results('table_reservations') > 0;
	}

	public function create($data){

		$this->db->trans_start();
		$this->db->insert('table_reservations', $data);
		$id = (int) $this->db->insert_id();
		$this->audit->log('reservation.create', 'reservation', $id, array(
			'summary' => 'Reserva creada: '.$data['name'].' ('.$data['party_size'].' pers.) '.$data['start_at'],
			'after' => $data
		));
		$this->db->trans_complete();
		return $id;
	}

	public function setStatus($id, $status, $id_tab = NULL){

		$data = array('status' => $status);
		if( $id_tab !== NULL ){ $data['id_tab'] = (int) $id_tab; }

		$this->db->trans_start();
		$before = $this->get($id);
		$this->db->where('id_reservation', (int) $id)->where('status', 'active')->update('table_reservations', $data);
		$changed = $this->db->affected_rows() > 0;

		if( $changed ){
			$names = array('cancelled' => 'cancelada', 'no_show' => 'marcada como no llegó', 'seated' => 'sentada');
			$this->audit->log('reservation.'.$status, 'reservation', $id, array(
				'tab' => $id_tab,
				'summary' => 'Reserva '.(isset($names[$status]) ? $names[$status] : $status).': '.($before ? $before->name : '#'.$id),
				'before' => array('status' => $before ? $before->status : NULL),
				'after' => array('status' => $status, 'id_tab' => $id_tab !== NULL ? (int) $id_tab : NULL)
			));
		}
		$this->db->trans_complete();

		return $changed;
	}

	public function tables(){

		return $this->db->query('SELECT d.id_table, d.label, d.seats, a.name AS area_name
			FROM dining_tables d JOIN table_areas a ON a.id_area = d.id_area AND a.active = 1
			WHERE d.active = 1 ORDER BY a.sort_order, d.label')->result();
	}
}
