<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Shift inventory report (informational only: never touches products.stock).
 * A shift snapshots the active storages; each storage is counted once (first save wins, row locks),
 * then locked (only super may edit while the shift is open). When every storage is counted the shift can be closed.
 * Result codes of the write methods: array(code, data) with code in ok|busy|notfound|closed|taken|pending|exists|nostorage|invalid.
 */
class Shift_inventory_mdl extends CI_Model{

	const MAX_LINES = 300;

	// ---------- reads ----------

	public function get($id){

		return $this->db->where('id_shift', (int) $id)->get('inv_shifts')->row();
	}

	public function current(){

		return $this->db->where('status', 'open')->order_by('id_shift', 'desc')->get('inv_shifts')->row();
	}

	/** Reports newest first with storage progress and total units. */
	public function history($limit = 60){

		return $this->db->query('SELECT s.*, COUNT(ss.id_shift_storage) AS n_storages, SUM(ss.status = "counted") AS n_counted,
				(SELECT COALESCE(SUM(l.base_units), 0) FROM inv_lines l JOIN inv_shift_storages x ON x.id_shift_storage = l.id_shift_storage WHERE x.id_shift = s.id_shift) AS total_units
			FROM inv_shifts s LEFT JOIN inv_shift_storages ss ON ss.id_shift = s.id_shift
			GROUP BY s.id_shift ORDER BY s.id_shift DESC LIMIT '.(int) $limit)->result();
	}

	/** Storage rows of a shift with line count and unit total. */
	public function storages($id_shift){

		return $this->db->query('SELECT ss.*, COUNT(l.id_line) AS n_lines, COALESCE(SUM(l.base_units), 0) AS total_units
			FROM inv_shift_storages ss LEFT JOIN inv_lines l ON l.id_shift_storage = ss.id_shift_storage
			WHERE ss.id_shift = ? GROUP BY ss.id_shift_storage ORDER BY (SELECT sort_order FROM inv_storages s WHERE s.id_storage = ss.id_storage), ss.storage_name', array((int) $id_shift))->result();
	}

	public function storage($id_ss){

		return $this->db->query('SELECT ss.*, s.status AS shift_status, s.label AS shift_label FROM inv_shift_storages ss JOIN inv_shifts s ON s.id_shift = ss.id_shift WHERE ss.id_shift_storage = ?', array((int) $id_ss))->row();
	}

	public function lines($id_ss){

		return $this->db->where('id_shift_storage', (int) $id_ss)->order_by('product_name')->order_by('pack_units')->order_by('id_line')->get('inv_lines')->result();
	}

	/** All lines of a shift grouped by id_shift_storage. */
	public function linesByStorage($id_shift){

		$out = array();
		$rows = $this->db->query('SELECT l.* FROM inv_lines l JOIN inv_shift_storages ss ON ss.id_shift_storage = l.id_shift_storage WHERE ss.id_shift = ? ORDER BY l.product_name, l.pack_units, l.id_line', array((int) $id_shift))->result();
		foreach( $rows as $r ){ $out[(int) $r->id_shift_storage][] = $r; }

		return $out;
	}

	/** Combined units per product over the whole shift, next to the system stock (informational). */
	public function totals($id_shift){

		return $this->db->query('SELECT l.id_product, MAX(l.product_name) AS product_name, MAX(l.barcode) AS barcode, SUM(l.base_units) AS counted, p.stock AS system_stock, COUNT(DISTINCT l.id_shift_storage) AS n_storages
			FROM inv_lines l JOIN inv_shift_storages ss ON ss.id_shift_storage = l.id_shift_storage
			LEFT JOIN products p ON p.id_product = l.id_product
			WHERE ss.id_shift = ? GROUP BY l.id_product, p.stock ORDER BY product_name', array((int) $id_shift))->result();
	}

	/** Active non-service product + its packs, for validating a line. */
	private function product($id){

		return $this->db->query('SELECT id_product, barcode, name FROM products WHERE id_product = ? AND active = 1 AND is_service = 0', array((int) $id))->row();
	}

	// ---------- transactions ----------

	/** Same retry idea as Kitchen_mdl::runTx: $fn returns array(ok, payload); deadlocks retried up to 3 times. Returns array(ok, payload, gaveUp). */
	private function runTx($fn){

		for( $try = 0; $try < 3; $try++ ){
			try{
				$this->db->trans_begin();
				$r = $fn();
				if( $r[0] ){ $this->db->trans_commit(); } else { $this->db->trans_rollback(); }

				return array((bool) $r[0], $r[1], FALSE);
			}
			catch( Exception $e ){
				log_message('error', 'shift_inventory tx: '.$e->getMessage());
				try{ $this->db->trans_rollback(); } catch( Exception $e2 ){}
				usleep(mt_rand(20000, 90000));
			}
		}

		return array(FALSE, NULL, TRUE);
	}

	private function actor(){

		return array((int) $this->session->userdata('id_admin'), (string) $this->session->userdata('admin_name'));
	}

	/** Opens a report with one pending row per active storage. Only one open shift at a time. */
	public function openShift($label){

		$lock = $this->db->query('SELECT GET_LOCK("inv_shift_open", 5) AS l')->row();
		if( !$lock || (int) $lock->l !== 1 ){ return array('busy', NULL); }

		list($admin, $name) = $this->actor();

		$res = $this->runTx(function() use ($label, $admin, $name){

			if( $this->db->query('SELECT id_shift FROM inv_shifts WHERE status = "open" LIMIT 1 FOR UPDATE')->row() ){ return array(FALSE, 'exists'); }

			$storages = $this->db->query('SELECT id_storage, name FROM inv_storages WHERE active = 1 ORDER BY sort_order, name')->result();
			if( !$storages ){ return array(FALSE, 'nostorage'); }

			$this->db->insert('inv_shifts', array('label' => $label, 'status' => 'open', 'opened_by' => $admin, 'opened_by_name' => $name, 'opened_at' => date('Y-m-d H:i:s')));
			$id = (int) $this->db->insert_id();
			foreach( $storages as $s ){
				$this->db->insert('inv_shift_storages', array('id_shift' => $id, 'id_storage' => (int) $s->id_storage, 'storage_name' => $s->name));
			}

			$this->audit->log('shift_inv.open', 'inv_shift', $id, array('summary' => 'Inventario de turno abierto: '.$label.' ('.count($storages).' almacenes)', 'after' => array('label' => $label, 'storages' => count($storages))));

			return array(TRUE, $id);
		});

		$this->db->query('SELECT RELEASE_LOCK("inv_shift_open")');

		if( $res[2] ){ return array('busy', NULL); }

		return $res[0] ? array('ok', $res[1]) : array($res[1] === 'exists' || $res[1] === 'nostorage' ? $res[1] : 'invalid', NULL);
	}

	/**
	 * Saves the count of one storage. $lines = array of array(id_product, id_pack|NULL, qty, note). $edit = TRUE only for a super editing an already counted storage.
	 * FIRST SAVE WINS: shift + storage rows are locked; if already counted and not $edit the call is refused with 'taken' (data = winner row).
	 */
	public function saveCount($id_ss, $lines, $is_empty, $notes, $edit){

		// Resolve products/packs outside the transaction (read-only); stored as snapshots.
		$clean = array();
		foreach( $lines as $l ){

			$p = $this->product($l['id_product']);
			if( !$p ){ return array('invalid', 'Producto inválido o inactivo en el conteo'); }

			$pack = NULL;
			if( $l['id_pack'] ){
				$pack = $this->db->query('SELECT id_pack, name, units FROM product_packs WHERE id_pack = ? AND id_product = ? AND active = 1', array((int) $l['id_pack'], (int) $p->id_product))->row();
				if( !$pack ){ return array('invalid', 'Presentación inválida para '.$p->name); }
			}

			$units = $pack ? (int) $pack->units : 1;
			$clean[] = array(
				'id_product' => (int) $p->id_product, 'product_name' => $p->name, 'barcode' => $p->barcode,
				'id_pack' => $pack ? (int) $pack->id_pack : NULL, 'pack_name' => $pack ? $pack->name : NULL, 'pack_units' => $pack ? $units : NULL,
				'qty' => (int) $l['qty'], 'base_units' => (int) $l['qty'] * $units, 'note' => $l['note'],
			);
		}

		list($admin, $name) = $this->actor();

		$res = $this->runTx(function() use ($id_ss, $clean, $is_empty, $notes, $edit, $admin, $name){

			$ss = $this->db->query('SELECT * FROM inv_shift_storages WHERE id_shift_storage = ? FOR UPDATE', array((int) $id_ss))->row();
			if( !$ss ){ return array(FALSE, array('notfound', NULL)); }

			$shift = $this->db->query('SELECT * FROM inv_shifts WHERE id_shift = ? FOR UPDATE', array((int) $ss->id_shift))->row();
			if( !$shift || $shift->status !== 'open' ){ return array(FALSE, array('closed', $shift)); }

			if( $ss->status === 'counted' && !$edit ){ return array(FALSE, array('taken', $ss)); }

			$before = NULL;
			if( $ss->status === 'counted' ){
				$before = array('lines' => $this->db->select('id_product, pack_name, qty, base_units')->where('id_shift_storage', (int) $id_ss)->get('inv_lines')->result_array(), 'is_empty' => (int) $ss->is_empty);
				$this->db->where('id_shift_storage', (int) $id_ss)->delete('inv_lines');
			}

			$total = 0;
			foreach( $clean as $c ){
				$c['id_shift_storage'] = (int) $id_ss;
				$this->db->insert('inv_lines', $c);
				$total += $c['base_units'];
			}

			$upd = array('status' => 'counted', 'is_empty' => $is_empty ? 1 : 0, 'notes' => $notes);
			if( !$edit ){
				$upd['counted_by'] = $admin; $upd['counted_by_name'] = $name; $upd['counted_at'] = date('Y-m-d H:i:s');
			}
			$this->db->where('id_shift_storage', (int) $id_ss)->update('inv_shift_storages', $upd);

			$after = array('storage' => $ss->storage_name, 'lines' => count($clean), 'total_units' => $total, 'is_empty' => $is_empty ? 1 : 0);
			$this->audit->log($edit ? 'shift_inv.edit' : 'shift_inv.count', 'inv_shift_storage', (int) $id_ss, array(
				'summary' => ($edit ? 'Conteo corregido: ' : 'Almacén inventariado: ').$ss->storage_name.' ('.$shift->label.')', 'before' => $before, 'after' => $after));

			return array(TRUE, array('ok', $after));
		});

		if( $res[2] ){ return array('busy', NULL); }

		return $res[1];
	}

	/** Marks the shift done once every storage is counted. */
	public function close($id_shift){

		list($admin, $name) = $this->actor();

		$res = $this->runTx(function() use ($id_shift, $admin, $name){

			$shift = $this->db->query('SELECT * FROM inv_shifts WHERE id_shift = ? FOR UPDATE', array((int) $id_shift))->row();
			if( !$shift ){ return array(FALSE, array('notfound', NULL)); }
			if( $shift->status !== 'open' ){ return array(FALSE, array('closed', $shift)); }

			$pending = (int) $this->db->query('SELECT COUNT(*) AS n FROM inv_shift_storages WHERE id_shift = ? AND status = "pending" FOR UPDATE', array((int) $id_shift))->row()->n;
			if( $pending > 0 ){ return array(FALSE, array('pending', $pending)); }

			$this->db->where('id_shift', (int) $id_shift)->update('inv_shifts', array('status' => 'done', 'closed_by' => $admin, 'closed_by_name' => $name, 'closed_at' => date('Y-m-d H:i:s')));
			$this->audit->log('shift_inv.close', 'inv_shift', (int) $id_shift, array('summary' => 'Inventario de turno terminado: '.$shift->label, 'before' => array('status' => 'open'), 'after' => array('status' => 'done')));

			return array(TRUE, array('ok', $shift));
		});

		if( $res[2] ){ return array('busy', NULL); }

		return $res[1];
	}

	/** Super: voids an open shift. */
	public function cancel($id_shift){

		list($admin, $name) = $this->actor();

		$res = $this->runTx(function() use ($id_shift, $admin, $name){

			$shift = $this->db->query('SELECT * FROM inv_shifts WHERE id_shift = ? FOR UPDATE', array((int) $id_shift))->row();
			if( !$shift ){ return array(FALSE, array('notfound', NULL)); }
			if( $shift->status !== 'open' ){ return array(FALSE, array('closed', $shift)); }

			$this->db->where('id_shift', (int) $id_shift)->update('inv_shifts', array('status' => 'void', 'closed_by' => $admin, 'closed_by_name' => $name, 'closed_at' => date('Y-m-d H:i:s')));
			$this->audit->log('shift_inv.cancel', 'inv_shift', (int) $id_shift, array('summary' => 'Inventario de turno anulado: '.$shift->label, 'before' => array('status' => 'open'), 'after' => array('status' => 'void')));

			return array(TRUE, array('ok', $shift));
		});

		if( $res[2] ){ return array('busy', NULL); }

		return $res[1];
	}
}
