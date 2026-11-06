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

	/** Orderable menu: active products; stocked ones carry their current stock (it already drops when an order is sent). */
	public function menu(){

		$sql = 'SELECT p.id_product AS id, p.name, p.price_sell, p.is_service, p.stock, p.prints_kitchen,
					IFNULL(p.id_service_category, 0) AS cat
				FROM products p WHERE p.active = 1 AND (p.barcode IS NULL OR p.barcode <> "SPLIT-CONSUMO") ORDER BY p.name';

		$rows = $this->db->query($sql)->result();

		foreach( $rows as $r ){
			$r->id    = (int) $r->id;
			$r->price = (float) $r->price_sell;
			$r->stocked = !$r->is_service;
			$r->available = $r->stocked ? max(0, (int) $r->stock) : NULL;
			unset($r->price_sell, $r->stock, $r->is_service);
		}

		return $rows;
	}

	public function product($id_product){

		return $this->db->where('id_product', (int) $id_product)->where('active', 1)->group_start()->where('barcode IS NULL', NULL, FALSE)->or_where('barcode !=', 'SPLIT-CONSUMO')->group_end()->get('products')->row();
	}

	/** Stock left for a stocked product (orders already took theirs when they were sent). */
	public function available($id_product){

		$p = $this->product($id_product);
		if( !$p || $p->is_service ){ return NULL; }

		return max(0, (int) $p->stock);
	}

	/** Creates the batch + its lines atomically and takes the stocked products out of stock. Returns id_order (0 = failed, -1 = not enough stock). */
	public function createOrder($id_tab, $id_admin, $lines, $id_bill = 0){

		$this->db->trans_start();

		$this->db->insert('orders_kitchen', array('id_tab' => (int) $id_tab, 'id_admin' => (int) $id_admin));
		$id_order = (int) $this->db->insert_id();

		foreach( $lines as $l ){

			// atomic guard: the decrement only applies while enough stock remains (services never touch stock)
			$this->db->query('UPDATE products SET stock = stock - ? WHERE id_product = ? AND is_service = 0 AND stock >= ?', array((int) $l['quantity'], (int) $l['id_product'], (int) $l['quantity']));
			if( $this->db->affected_rows() < 1 && !$this->isService($l['id_product']) ){
				$this->db->trans_rollback();
				return -1;
			}

			$this->db->insert('order_items', array(
				'id_order'       => $id_order,
				'id_tab'         => (int) $id_tab,
				'id_bill'        => (int) $id_bill ?: NULL,
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

	private function isService($id_product){

		$p = $this->db->select('is_service')->where('id_product', (int) $id_product)->get('products')->row();
		return !$p || (int) $p->is_service === 1;
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

	/** Voids an active line and returns its quantity to stock (unless its bill was already paid: that stock was sold). */
	public function voidItem($id_item, $reason, $id_admin){

		$this->db->trans_start();

		$item = $this->db->query('SELECT oi.*, b.status AS bill_status FROM order_items oi LEFT JOIN bills b ON b.id_bill = oi.id_bill WHERE oi.id_order_item = ? FOR UPDATE', array((int) $id_item))->row();

		$this->db->where('id_order_item', (int) $id_item)->where('status', 'active')->update('order_items', array(
			'status' => 'void', 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => mb_substr($reason, 0, 255, 'UTF-8'), 'voided_by' => (int) $id_admin,
		));

		$done = $this->db->affected_rows() > 0;

		if( $done && $item && $item->bill_status !== 'paid' ){
			$this->db->query('UPDATE products SET stock = stock + ? WHERE id_product = ? AND is_service = 0', array((int) $item->quantity, (int) $item->id_product));
		}

		$this->db->trans_complete();

		return $done && $this->db->trans_status();
	}
}
