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

		$this->db->insert('table_reservations', $data);
		return (int) $this->db->insert_id();
	}

	public function setStatus($id, $status, $id_tab = NULL){

		$data = array('status' => $status);
		if( $id_tab !== NULL ){ $data['id_tab'] = (int) $id_tab; }

		$this->db->where('id_reservation', (int) $id)->where('status', 'active')->update('table_reservations', $data);
		return $this->db->affected_rows() > 0;
	}

	public function tables(){

		return $this->db->query('SELECT d.id_table, d.label, d.seats, a.name AS area_name
			FROM dining_tables d JOIN table_areas a ON a.id_area = d.id_area AND a.active = 1
			WHERE d.active = 1 ORDER BY a.sort_order, d.label')->result();
	}
}
