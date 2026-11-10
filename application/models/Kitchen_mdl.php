<?php if( !defined('BASEPATH') ){ exit(); }

/** Ordering on a tab + kitchen tickets. (Named Kitchen_mdl: Orders_mdl belongs to the disabled supplier-orders feature.) */
class Kitchen_mdl extends CI_Model{

	/** Name of the ingredient that could not cover an order (set when createOrder/replaceItem fail with -2). */
	public $short_ingredient = '';

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

		$this->load->model('ingredients_mdl');
		$recipes = $this->ingredients_mdl->recipesFor(array_map(function($r){ return $r->id; }, $rows));

		foreach( $rows as $r ){
			$r->id    = (int) $r->id;
			$r->price = (float) $r->price_sell;
			$r->stocked = !$r->is_service;
			$r->available = $r->stocked ? max(0, (int) $r->stock) : NULL;

			// dishes with a recipe: availability = portions the ingredient stock still covers ("Agotado" at 0)
			if( isset($recipes[$r->id]) ){
				$portions = Ingredients_mdl::portions($recipes[$r->id]);
				$r->stocked   = TRUE;
				$r->available = $r->available === NULL ? $portions : min($r->available, $portions);
			}

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

	/** Portions of a product that its recipe's ingredient stock covers; NULL when it has no recipe. */
	public function recipePortions($id_product){

		$this->load->model('ingredients_mdl');
		$r = $this->ingredients_mdl->recipesFor(array($id_product));

		return isset($r[(int) $id_product]) ? Ingredients_mdl::portions($r[(int) $id_product]) : NULL;
	}

	/**
	 * Takes (sign = -1) or returns (+1) the ingredients of $qty portions of a product. Taking is atomic per ingredient
	 * (UPDATE ... WHERE stock >= need). Returns '' when fine, else the name of the ingredient that fell short. Caller owns the transaction.
	 */
	private function moveIngredients($id_product, $qty, $sign, $id_order_item, $id_admin){

		$rows = $this->db->query('SELECT r.id_ingredient, r.quantity, i.name FROM recipe_items r JOIN ingredients i ON i.id_ingredient = r.id_ingredient
			WHERE r.id_product = ? ORDER BY r.id_ingredient', array((int) $id_product))->result();

		if( !$rows ){ return ''; }

		$this->load->model('ingredients_mdl');

		foreach( $rows as $r ){

			$need = round((float) $r->quantity * (int) $qty, 3);

			if( $sign < 0 ){
				$this->db->query('UPDATE ingredients SET stock = stock - ? WHERE id_ingredient = ? AND stock >= ?', array($need, (int) $r->id_ingredient, $need));
				if( $this->db->affected_rows() < 1 ){ return $r->name; }
			}
			else{
				$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ?', array($need, (int) $r->id_ingredient));
			}

			$this->ingredients_mdl->log($r->id_ingredient, $sign < 0 ? 'order' : 'void', $sign * $need, NULL, $id_admin, $id_order_item);
		}

		return '';
	}

	/** Creates the batch + its lines atomically and takes the stocked products out of stock. Returns id_order (0 = failed, -1 = not enough stock). */
	public function createOrder($id_tab, $id_admin, $lines, $id_bill = 0){

		// manual transaction: in CI 3.0.0 trans_rollback() is a no-op inside trans_start()
		$this->db->trans_begin();
		$id_order = $this->insertOrder($id_tab, $id_admin, $lines, $id_bill);

		if( $id_order < 1 || $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return $id_order < 0 ? $id_order : 0; // -1 = product stock, -2 = ingredient short (see $short_ingredient)
		}

		$this->db->trans_commit();

		return $id_order;
	}

	/** Batch + lines + atomic stock decrement, WITHOUT managing the transaction (the caller owns it). Returns id_order or -1 (not enough stock). */
	private function insertOrder($id_tab, $id_admin, $lines, $id_bill){

		$this->db->insert('orders_kitchen', array('id_tab' => (int) $id_tab, 'id_admin' => (int) $id_admin));
		$id_order = (int) $this->db->insert_id();

		foreach( $lines as $l ){

			// atomic guard: the decrement only applies while enough stock remains (services never touch stock)
			$this->db->query('UPDATE products SET stock = stock - ? WHERE id_product = ? AND is_service = 0 AND stock >= ?', array((int) $l['quantity'], (int) $l['id_product'], (int) $l['quantity']));
			if( $this->db->affected_rows() < 1 && !$this->isService($l['id_product']) ){
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

			$short = $this->moveIngredients($l['id_product'], $l['quantity'], -1, $this->db->insert_id(), $id_admin);
			if( $short !== '' ){
				$this->short_ingredient = $short;
				return -2;
			}
		}

		return $id_order;
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

	/**
	 * Voids $qty units of an active line (0 = the whole line) and returns them to stock, unless its bill was already paid (that stock
	 * was sold). A partial void shrinks the line and records the voided units as a cloned void line. Waiters ($is_admin = FALSE) cannot
	 * touch already-collected consumption. Must run inside a transaction. Returns array(ok, message, item, qty_voided).
	 */
	private function doVoid($id_item, $reason, $id_admin, $qty, $is_admin, $need_open_bill = FALSE){

		$item = $this->db->query('SELECT oi.*, b.status AS bill_status, t.status AS tab_status FROM order_items oi
			LEFT JOIN bills b ON b.id_bill = oi.id_bill LEFT JOIN tabs t ON t.id_tab = oi.id_tab
			WHERE oi.id_order_item = ? FOR UPDATE', array((int) $id_item))->row();

		if( !$item || $item->status !== 'active' ){ return array(FALSE, 'El producto ya fue anulado', NULL, 0); }

		$qty = (int) $qty;
		if( $qty < 0 || $qty > (int) $item->quantity ){ return array(FALSE, 'Cantidad inválida', NULL, 0); }
		if( $qty === 0 ){ $qty = (int) $item->quantity; }

		if( $item->bill_status === 'split' ){ return array(FALSE, 'La cuenta está dividida: deshaga la división antes de anular o cambiar productos', NULL, 0); }
		if( $need_open_bill && $item->bill_status !== 'open' ){ return array(FALSE, 'La cuenta ya no está abierta', NULL, 0); }
		if( !$is_admin && ($item->bill_status === 'paid' || $item->tab_status !== 'open') ){ return array(FALSE, 'La cuenta ya fue cobrada: solo un administrador puede anular', NULL, 0); }

		$void = array('status' => 'void', 'voided_at' => date('Y-m-d H:i:s'), 'void_reason' => mb_substr($reason, 0, 255, 'UTF-8'), 'voided_by' => (int) $id_admin);

		if( $qty === (int) $item->quantity ){
			$this->db->where('id_order_item', (int) $item->id_order_item)->update('order_items', $void);
		}
		else{
			$this->db->where('id_order_item', (int) $item->id_order_item)->update('order_items', array('quantity' => (int) $item->quantity - $qty));
			$clone = (array) $item;
			unset($clone['id_order_item'], $clone['bill_status'], $clone['tab_status']);
			$clone['quantity'] = $qty;
			$this->db->insert('order_items', array_merge($clone, $void));
		}

		if( $item->bill_status !== 'paid' ){
			$this->db->query('UPDATE products SET stock = stock + ? WHERE id_product = ? AND is_service = 0', array($qty, (int) $item->id_product));
			$this->moveIngredients($item->id_product, $qty, 1, $item->id_order_item, $id_admin);
		}

		return array(TRUE, 'Producto anulado', $item, $qty);
	}

	/** Void (whole line or $qty units). Returns array(ok, message). */
	public function voidItem($id_item, $reason, $id_admin, $qty = 0, $is_admin = TRUE){

		$this->db->trans_begin();
		$res = $this->doVoid($id_item, $reason, $id_admin, $qty, $is_admin);

		if( !$res[0] || $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return array(FALSE, $res[0] ? 'No se pudo anular el producto' : $res[1]);
		}

		$this->db->trans_commit();

		return array(TRUE, $res[1]);
	}

	/**
	 * Swap $qty units of a sent line for $new_qty of another product, atomically: the old units are voided and restocked, the new
	 * product is ordered (same bill, same course) with its own stock guard. Any failure leaves everything untouched.
	 * Returns array(ok, message, id_order).
	 */
	public function replaceItem($id_item, $qty, $new, $new_qty, $notes, $reason, $id_admin, $is_admin){

		$this->db->trans_begin();

		$res = $this->doVoid($id_item, $reason, $id_admin, $qty, $is_admin, TRUE);
		if( !$res[0] ){
			$this->db->trans_rollback();
			return array(FALSE, $res[1], 0);
		}

		$old = $res[2];
		$line = array(
			'id_product' => (int) $new->id_product, 'name' => $new->name, 'quantity' => (int) $new_qty, 'price' => (float) $new->price_sell,
			'notes' => (string) $notes, 'course' => (int) $old->course, 'prints_kitchen' => (int) $new->prints_kitchen,
		);

		$id_order = $this->insertOrder($old->id_tab, $id_admin, array($line), $old->id_bill);
		if( $id_order < 1 || $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			$msg = $id_order === -1 ? 'Stock insuficiente de '.$new->name : ($id_order === -2 ? 'Ingredientes insuficientes para '.$new->name.' (falta '.$this->short_ingredient.')' : 'No se pudo guardar el cambio');
			return array(FALSE, $msg, 0);
		}

		$this->db->trans_commit();

		return array(TRUE, 'Producto cambiado', $id_order);
	}
}
