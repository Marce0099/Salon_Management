<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Floor plan data: areas, tables (free-form coordinates in % of the canvas) and tabs (open table visits).
 * Table state is derived: occupied = has an open tab; free otherwise (reserved joins in Stage 4).
 */
class Floor_mdl extends CI_Model{

	public function areas(){

		return $this->db->where('active', 1)->order_by('sort_order')->order_by('id_area')->get('table_areas')->result();
	}

	public function area($id_area){

		return $this->db->where('active', 1)->where('id_area', (int) $id_area)->get('table_areas')->row();
	}

	public function saveArea($id_area, $name){

		if( $id_area ){
			$this->db->where('id_area', (int) $id_area)->update('table_areas', array('name' => $name));
			return (int) $id_area;
		}

		$max = $this->db->select_max('sort_order', 'm')->get('table_areas')->row();
		$this->db->insert('table_areas', array('name' => $name, 'sort_order' => ((int) $max->m) + 1));
		return (int) $this->db->insert_id();
	}

	public function areaHasOpenTabs($id_area){

		return $this->db->from('tabs t')
			->join('dining_tables d', 'd.id_table = t.id_table')
			->where('d.id_area', (int) $id_area)->where('t.status', 'open')->count_all_results() > 0;
	}

	public function deleteArea($id_area){

		$this->db->where('id_area', (int) $id_area)->update('dining_tables', array('active' => 0));
		$this->db->where('id_area', (int) $id_area)->update('table_areas', array('active' => 0));
	}

	public function table($id_table){

		return $this->db->where('active', 1)->where('id_table', (int) $id_table)->get('dining_tables')->row();
	}

	public function saveTable($id_table, $data){

		if( $id_table ){
			$this->db->where('id_table', (int) $id_table)->update('dining_tables', $data);
			return (int) $id_table;
		}

		$this->db->insert('dining_tables', $data);
		return (int) $this->db->insert_id();
	}

	public function deleteTable($id_table){

		$this->db->where('id_table', (int) $id_table)->update('dining_tables', array('active' => 0));
	}

	public function labelExists($id_area, $label, $exclude_id = 0){

		$this->db->where('active', 1)->where('id_area', (int) $id_area)->where('LOWER(label)', mb_strtolower($label, 'UTF-8'));
		if( $exclude_id ){
			$this->db->where('id_table !=', (int) $exclude_id);
		}
		return $this->db->count_all_results('dining_tables') > 0;
	}

	/** Tables of an area with derived state and open-tab info. */
	public function tablesWithState($id_area){

		$sql = 'SELECT d.id_table, d.label, d.seats, d.x, d.y, d.w, d.h, d.shape,
				t.id_tab, t.guests, t.opened_at, e.name AS waiter
				FROM dining_tables d
				LEFT JOIN tabs t ON t.id_table = d.id_table AND t.status = "open"
				LEFT JOIN employees e ON e.id_employee = t.id_employee
				WHERE d.active = 1 AND d.id_area = ?
				ORDER BY d.label';

		$rows = $this->db->query($sql, array((int) $id_area))->result();

		$reserved = $this->currentReservations($id_area);

		foreach( $rows as $r ){

			$r->res = isset($reserved[$r->id_table]) ? $reserved[$r->id_table] : NULL;
			$r->state = $r->id_tab ? 'occupied' : ( $r->res ? 'reserved' : 'free' );
		}

		return $rows;
	}

	/** Active reservations whose hold window (start - hold minutes .. end) contains now, first per table. */
	public function currentReservations($id_area){

		$this->load->model('settings_mdl');
		$hold = max(0, (int) $this->settings_mdl->get('reservation_hold_minutes', 30));

		$sql = 'SELECT r.id_reservation, r.id_table, r.name, r.phone, r.party_size, r.start_at, r.end_at, r.notes
				FROM table_reservations r
				JOIN dining_tables d ON d.id_table = r.id_table AND d.active = 1 AND d.id_area = ?
				WHERE r.status = "active" AND r.end_at > NOW() AND DATE_SUB(r.start_at, INTERVAL ? MINUTE) <= NOW()
				ORDER BY r.start_at';

		$out = array();
		foreach( $this->db->query($sql, array((int) $id_area, $hold))->result() as $r ){
			if( !isset($out[$r->id_table]) ){ $out[$r->id_table] = $r; }
		}
		return $out;
	}

	public function openTab($id_table, $id_employee, $guests, $id_admin){

		$this->db->trans_start();

		// lock the table row so two waiters can't open the same table at once
		$this->db->query('SELECT id_table FROM dining_tables WHERE id_table = ? FOR UPDATE', array((int) $id_table));

		$exists = $this->db->where('id_table', (int) $id_table)->where('status', 'open')->count_all_results('tabs') > 0;

		$id_tab = 0;
		if( !$exists ){
			$this->db->insert('tabs', array(
				'id_table'    => (int) $id_table,
				'id_employee' => $id_employee ? (int) $id_employee : NULL,
				'guests'      => max(1, (int) $guests),
				'id_admin'    => (int) $id_admin,
			));
			$id_tab = (int) $this->db->insert_id();
		}

		$this->db->trans_complete();

		return $id_tab;
	}

	public function tab($id_tab){

		return $this->db->where('id_tab', (int) $id_tab)->get('tabs')->row();
	}

	public function closeTab($id_tab, $status = 'void'){

		$this->db->where('id_tab', (int) $id_tab)->where('status', 'open')
			->update('tabs', array('status' => $status, 'closed_at' => date('Y-m-d H:i:s')));

		return $this->db->affected_rows() > 0;
	}

	public function tabHasOpenTable($id_table){

		return $this->db->where('id_table', (int) $id_table)->where('status', 'open')->count_all_results('tabs') > 0;
	}
}
