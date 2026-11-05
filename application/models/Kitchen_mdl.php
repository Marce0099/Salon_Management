<?php if( !defined('BASEPATH') ){ exit(); }

/** Ordering on a tab + kitchen tickets. (Named Kitchen_mdl: Orders_mdl belongs to the disabled supplier-orders feature.) */
class Kitchen_mdl extends CI_Model{

	public function tab($id_tab){

		$sql = 'SELECT t.*, d.label AS table_label, a.name AS area_name, e.name AS waiter
				FROM tabs t
				JOIN dining_tables d ON d.id_table = t.id_table
				JOIN table_areas a ON a.id_area = d.id_area
				LEFT JOIN employees e ON e.id_employee = t.id_employee
				WHERE t.id_tab = ?';

		return $this->db->query($sql, array((int) $id_tab))->row();
	}

	public function categories(){

		return $this->db->select('id_service_category AS id, name_category AS name')->order_by('name_category')->get('service_category')->result();
	}

	/** Orderable menu: active products; stocked ones carry stock minus what open tabs already ordered. */
	public function menu(){

		$sql = 'SELECT p.id_product AS id, p.name, p.price_sell, p.is_service, p.stock, p.prints_kitchen,
					IFNULL(p.id_service_category, 0) AS cat,
					IFNULL((SELECT SUM(oi.quantity) FROM order_items oi JOIN tabs t ON t.id_tab = oi.id_tab AND t.status = "open"
					        WHERE oi.id_product = p.id_product AND oi.status = "active"), 0) AS reserved_qty
				FROM products p WHERE p.active = 1 ORDER BY p.name';

		$rows = $this->db->query($sql)->result();

		foreach( $rows as $r ){
			$r->id    = (int) $r->id;
			$r->price = (float) $r->price_sell;
			$r->stocked = !$r->is_service;
			$r->available = $r->stocked ? max(0, (int) $r->stock - (int) $r->reserved_qty) : NULL;
			unset($r->price_sell, $r->reserved_qty, $r->stock, $r->is_service);
		}

		return $rows;
	}

	public function product($id_product){

		return $this->db->where('id_product', (int) $id_product)->where('active', 1)->get('products')->row();
	}

	/** Stock still unordered for a stocked product (stock minus qty already on open tabs). */
	public function available($id_product){

		$p = $this->product($id_product);
		if( !$p || $p->is_service ){ return NULL; }

		$row = $this->db->query('SELECT IFNULL(SUM(oi.quantity),0) AS q FROM order_items oi JOIN tabs t ON t.id_tab = oi.id_tab AND t.status = "open"
			WHERE oi.id_product = ? AND oi.status = "active"', array((int) $id_product))->row();

		return max(0, (int) $p->stock - (int) $row->q);
	}

	/** Creates the batch + its lines atomically. Returns id_order. */
	public function createOrder($id_tab, $id_admin, $lines){

		$this->db->trans_start();

		$this->db->insert('orders_kitchen', array('id_tab' => (int) $id_tab, 'id_admin' => (int) $id_admin));
		$id_order = (int) $this->db->insert_id();

		foreach( $lines as $l ){
			$this->db->insert('order_items', array(
				'id_order'       => $id_order,
				'id_tab'         => (int) $id_tab,
				'id_product'     => (int) $l['id_product'],
				'product_name'   => $l['name'],
				'quantity'       => (int) $l['quantity'],
				'unit_price'     => $l['price'],
				'notes'          => $l['notes'] !== '' ? $l['notes'] : NULL,
				'course'         => (int) $l['course'],
				'prints_kitchen' => (int) $l['prints_kitchen'],
			));
		}

		$this->db->trans_complete();

		return $this->db->trans_status() ? $id_order : 0;
	}

	public function order($id_order){

		return $this->db->where('id_order', (int) $id_order)->get('orders_kitchen')->row();
	}

	public function orderItems($id_order){

		return $this->db->where('id_order', (int) $id_order)->order_by('course')->order_by('id_order_item')->get('order_items')->result();
	}

	/** All batches of a tab, newest first, each with its lines. */
	public function tabOrders($id_tab){

		$orders = $this->db->where('id_tab', (int) $id_tab)->order_by('id_order', 'DESC')->get('orders_kitchen')->result();

		foreach( $orders as $o ){
			$o->items = $this->orderItems($o->id_order);
		}

		return $orders;
	}

	public function tabTotal($id_tab){

		$row = $this->db->query('SELECT IFNULL(SUM(quantity * unit_price),0) AS total FROM order_items WHERE id_tab = ? AND status = "active"', array((int) $id_tab))->row();
		return (float) $row->total;
	}

	public function setPrint($id_order, $status, $error = NULL){

		$data = array('print_status' => $status, 'print_error' => $error !== NULL ? mb_substr($error, 0, 255, 'UTF-8') : NULL);
		if( $status === 'printed' ){ $data['printed_at'] = date('Y-m-d H:i:s'); }

		$this->db->where('id_order', (int) $id_order)->update('orders_kitchen', $data);
	}

	public function item($id_item){

		return $this->db->where('id_order_item', (int) $id_item)->get('order_items')->row();
	}

	public function voidItem($id_item, $reason, $id_admin){

		$this->db->where('id_order_item', (int) $id_item)->where('status', 'active')->update('order_items', array(
			'status' => 'void', 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => mb_substr($reason, 0, 255, 'UTF-8'), 'voided_by' => (int) $id_admin,
		));

		return $this->db->affected_rows() > 0;
	}
}
