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
		$this->load->model('modifiers_mdl');
		$mods = $this->modifiers_mdl->menuMap();

		foreach( $rows as $r ){
			$r->id    = (int) $r->id;
			$r->price = (float) $r->price_sell;
			$r->stocked = !$r->is_service;
			$r->available = $r->stocked ? max(0, (int) $r->stock) : NULL;
			$r->base = $r->available; // product stock only (NULL for dishes); the recipe part is in rec
			$r->mods = isset($mods[$r->id]) ? $mods[$r->id] : array();
			$r->rec = array();

			// dishes with a recipe: availability = portions the ingredient stock still covers ("Agotado" at 0)
			if( isset($recipes[$r->id]) ){
				$portions = Ingredients_mdl::portions($recipes[$r->id]);
				foreach( $recipes[$r->id] as $rr ){ $r->rec[] = array('i' => (int) $rr->id_ingredient, 'q' => (float) $rr->quantity, 's' => (float) $rr->stock); }
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

	/** Recipe rows read by the last takeStock(): array(id_product => rows). Used to log the per-line ingredient movements. */
	private $cur_recipes = array();

	/**
	 * Locks the product and ingredient rows involved, ALWAYS in the same global order (all products by id, then all ingredients by id).
	 * Two concurrent transactions therefore can never wait for each other in a cycle (no deadlock), whatever order their lines have.
	 * Returns array('products' => id => row, 'ingredients' => id => row) with the locked, stable values.
	 */
	private function lockFor($product_ids, $extra_ingredient_ids = array()){

		$this->load->model('ingredients_mdl');

		$pids = array();
		foreach( $product_ids as $id ){ $pids[(int) $id] = (int) $id; }
		ksort($pids);

		$iids = array();
		foreach( $extra_ingredient_ids as $id ){ $iids[(int) $id] = (int) $id; }
		foreach( $this->ingredients_mdl->recipesFor(array_values($pids)) as $rows ){
			foreach( $rows as $r ){ $iids[(int) $r->id_ingredient] = (int) $r->id_ingredient; }
		}
		ksort($iids);

		$out = array('products' => array(), 'ingredients' => array());

		if( $pids ){
			$rows = $this->db->query('SELECT id_product, is_service, stock FROM products WHERE id_product IN ('.implode(',', $pids).') ORDER BY id_product FOR UPDATE')->result();
			foreach( $rows as $r ){ $out['products'][(int) $r->id_product] = $r; }
		}
		if( $iids ){
			$rows = $this->db->query('SELECT id_ingredient, name, stock FROM ingredients WHERE id_ingredient IN ('.implode(',', $iids).') ORDER BY id_ingredient FOR UPDATE')->result();
			foreach( $rows as $r ){ $out['ingredients'][(int) $r->id_ingredient] = $r; }
		}

		return $out;
	}

	/**
	 * Takes the stock of all lines of an order at once: locks everything in the global order, checks the WHOLE order against the locked
	 * stock, then decrements. First transaction to get the locks wins; a later one sees the reduced stock and is refused untouched.
	 * Returns 0 = ok, -1 = product stock short, -2 = ingredient short (name in $short_ingredient). Caller owns the transaction.
	 */
	private function takeStock($lines){

		$this->short_ingredient = '';

		$need_p = array();
		$ids = array();
		foreach( $lines as $l ){
			$ids[] = (int) $l['id_product'];
			$need_p[(int) $l['id_product']] = (isset($need_p[(int) $l['id_product']]) ? $need_p[(int) $l['id_product']] : 0) + (int) $l['quantity'];
		}

		$locked = $this->lockFor($ids);
		$this->cur_recipes = $this->ingredients_mdl->recipesFor($ids);

		// ingredient needs aggregated across every line (same ingredient may feed several dishes of the order); a line's modifiers
		// remove part of its recipe (never more than the recipe amount, see Modifiers_mdl::resolve), so locks and checks stay valid
		$this->load->model('modifiers_mdl');
		$need_i = array();
		foreach( $lines as $l ){
			$pid = (int) $l['id_product'];
			if( !isset($this->cur_recipes[$pid]) ){ continue; }
			$rem = isset($l['modifiers']) ? Modifiers_mdl::removalMap($l['modifiers']) : array();
			foreach( $this->cur_recipes[$pid] as $r ){
				$ing = (int) $r->id_ingredient;
				$per = max(0, round((float) $r->quantity - (isset($rem[$ing]) ? $rem[$ing] : 0), 3));
				$need_i[$ing] = (isset($need_i[$ing]) ? $need_i[$ing] : 0) + round($per * (int) $l['quantity'], 3);
			}
		}
		ksort($need_i);

		foreach( $need_p as $id => $qty ){
			$p = isset($locked['products'][$id]) ? $locked['products'][$id] : NULL;
			if( $p && !(int) $p->is_service && (int) $p->stock < $qty ){ return -1; }
		}
		foreach( $need_i as $id => $need ){
			if( !isset($locked['ingredients'][$id]) || round((float) $locked['ingredients'][$id]->stock, 3) < round($need, 3) ){
				$this->short_ingredient = isset($locked['ingredients'][$id]) ? $locked['ingredients'][$id]->name : '?';
				return -2;
			}
		}

		foreach( $need_p as $id => $qty ){
			$p = isset($locked['products'][$id]) ? $locked['products'][$id] : NULL;
			if( $p && !(int) $p->is_service ){
				$this->db->query('UPDATE products SET stock = stock - ? WHERE id_product = ?', array($qty, $id));
			}
		}
		foreach( $need_i as $id => $need ){
			$this->db->query('UPDATE ingredients SET stock = stock - ? WHERE id_ingredient = ?', array($need, $id));
		}

		return 0;
	}

	/** Movement-log rows (no stock change) for the ingredients that one order line consumed / gave back. */
	private function logIngredients($recipes, $id_product, $qty, $sign, $id_order_item, $id_admin, $removal = array()){

		if( !isset($recipes[(int) $id_product]) ){ return; }

		$this->load->model('ingredients_mdl');

		foreach( $recipes[(int) $id_product] as $r ){
			$per = max(0, round((float) $r->quantity - (isset($removal[(int) $r->id_ingredient]) ? $removal[(int) $r->id_ingredient] : 0), 3));
			$amount = $sign * round($per * (int) $qty, 3);
			if( $amount == 0 ){ continue; }
			$this->ingredients_mdl->log($r->id_ingredient, $sign < 0 ? 'order' : 'void', $amount, NULL, $id_admin, $id_order_item);
		}
	}

	/** Ingredient ids in the exact-restock snapshot of a line (they must be locked together with the recipe's). */
	private function snapshotIngredientIds($id_item){

		$out = array();
		foreach( $this->db->where('id_order_item', (int) $id_item)->get('order_item_ingredients')->result() as $r ){ $out[] = (int) $r->id_ingredient; }

		return $out;
	}

	/** Copies the per-line snapshots (modifiers + exact ingredient amounts) of $from to its clone $to (partial void / partial move). */
	public function cloneItemData($from, $to){

		$this->load->model('modifiers_mdl');
		$this->modifiers_mdl->cloneRows($from, $to);

		$this->db->query('INSERT INTO order_item_ingredients (id_order_item, id_ingredient, qty_per_unit)
			SELECT ?, id_ingredient, qty_per_unit FROM order_item_ingredients WHERE id_order_item = ?', array((int) $to, (int) $from));
	}
	/** Returns the ingredients of $qty voided portions (rows are already locked by the caller) and logs it. */
	private function restoreIngredients($id_product, $qty, $id_order_item, $id_admin){

		$this->load->model('ingredients_mdl');

		// lines sent after migration 014 carry the exact amounts they took: give back exactly that, whatever the recipe says now
		$snap = $this->db->query('SELECT stock_snapshot FROM order_items WHERE id_order_item = ?', array((int) $id_order_item))->row();
		if( $snap && (int) $snap->stock_snapshot === 1 ){

			foreach( $this->db->where('id_order_item', (int) $id_order_item)->get('order_item_ingredients')->result() as $r ){
				$amount = round((float) $r->qty_per_unit * (int) $qty, 3);
				if( $amount <= 0 ){ continue; }
				$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ?', array($amount, (int) $r->id_ingredient));
				$this->ingredients_mdl->log($r->id_ingredient, 'void', $amount, NULL, $id_admin, $id_order_item);
			}

			return;
		}

		$recipes = $this->ingredients_mdl->recipesFor(array($id_product));

		if( !isset($recipes[(int) $id_product]) ){ return; }

		// legacy line (no snapshot): what it took = current recipe minus the removals its modifiers snapshotted when it was sent
		$this->load->model('modifiers_mdl');
		$removal = $this->modifiers_mdl->snapshotRemoval($id_order_item);

		foreach( $recipes[(int) $id_product] as $r ){
			$per = max(0, round((float) $r->quantity - (isset($removal[(int) $r->id_ingredient]) ? $removal[(int) $r->id_ingredient] : 0), 3));
			$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ?', array(round($per * (int) $qty, 3), (int) $r->id_ingredient));
		}

		$this->logIngredients($recipes, $id_product, $qty, 1, $id_order_item, $id_admin, $removal);
	}

	/**
	 * Runs $fn inside a transaction. $fn returns array(ok, payload). A deadlock / lock timeout (or any DB exception) rolls back and
	 * retries up to 3 times with a tiny random pause. Returns array(ok, payload, db_failed).
	 */
	private function runTx($fn){

		for( $try = 0; $try < 3; $try++ ){

			try{
				$this->db->trans_begin();
				$r = $fn();

				if( $r[0] ){
					$this->db->trans_commit();
				}
				else{
					$this->db->trans_rollback();
				}

				return array((bool) $r[0], $r[1], FALSE);
			}
			catch( Exception $e ){

				log_message('error', 'Kitchen transaction failed (try '.($try + 1).'): '.$e->getMessage());

				try{ $this->db->trans_rollback(); }catch( Exception $e2 ){ /* server already rolled it back */ }

				usleep(mt_rand(20000, 90000));
			}
		}

		return array(FALSE, NULL, TRUE);
	}

	/**
	 * Creates the batch + its lines atomically and takes the stock (products and ingredients) of the whole order in one go.
	 * Returns id_order (0 = failed, -1 = not enough product stock, -2 = ingredient short, -3 = system busy, retry).
	 */
	public function createOrder($id_tab, $id_admin, $lines, $id_bill = 0){

		$res = $this->runTx(function() use ($id_tab, $id_admin, $lines, $id_bill){
			$id = $this->insertOrder($id_tab, $id_admin, $lines, $id_bill);
			return array($id > 0, $id);
		});

		if( $res[2] ){ return -3; }

		return $res[0] ? (int) $res[1] : ((int) $res[1] < 0 ? (int) $res[1] : 0);
	}

	/** Stock check/decrement + batch + lines, WITHOUT managing the transaction (the caller owns it). Returns id_order, -1 or -2. */
	private function insertOrder($id_tab, $id_admin, $lines, $id_bill){

		$this->load->model('modifiers_mdl');
		$short = $this->takeStock($lines);
		if( $short < 0 ){ return $short; }

		$this->db->insert('orders_kitchen', array('id_tab' => (int) $id_tab, 'id_admin' => (int) $id_admin));
		$id_order = (int) $this->db->insert_id();

		foreach( $lines as $l ){

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
				'stock_snapshot' => 1,
			));

			$id_item = (int) $this->db->insert_id();
			$mods = isset($l['modifiers']) ? $l['modifiers'] : array();
			if( $mods ){
				$this->load->model('modifiers_mdl');
				$this->modifiers_mdl->saveForItem($id_item, $mods);
			}

			$removal = Modifiers_mdl::removalMap($mods);
			if( isset($this->cur_recipes[(int) $l['id_product']]) ){
				foreach( $this->cur_recipes[(int) $l['id_product']] as $rr ){
					$per = max(0, round((float) $rr->quantity - (isset($removal[(int) $rr->id_ingredient]) ? $removal[(int) $rr->id_ingredient] : 0), 3));
					if( $per > 0 ){ $this->db->insert('order_item_ingredients', array('id_order_item' => $id_item, 'id_ingredient' => (int) $rr->id_ingredient, 'qty_per_unit' => $per)); }
				}
			}

			$this->logIngredients($this->cur_recipes, $l['id_product'], $l['quantity'], -1, $id_item, $id_admin, $removal);
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

		$this->load->model('modifiers_mdl');

		return $this->modifiers_mdl->attachTexts($this->db->where('id_order', (int) $id_order)->order_by('course')->order_by('id_order_item')->get('order_items')->result());
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
			$this->cloneItemData($item->id_order_item, $this->db->insert_id());
		}

		if( $item->bill_status !== 'paid' ){
			$this->db->query('UPDATE products SET stock = stock + ? WHERE id_product = ? AND is_service = 0', array($qty, (int) $item->id_product));
			$this->restoreIngredients($item->id_product, $qty, $item->id_order_item, $id_admin);
		}

		return array(TRUE, 'Producto anulado', $item, $qty);
	}

	/** Void (whole line or $qty units). Locks stock rows first (same global order as orders) and retries on deadlock. Returns array(ok, message). */
	public function voidItem($id_item, $reason, $id_admin, $qty = 0, $is_admin = TRUE){

		$item = $this->item($id_item);
		if( !$item ){ return array(FALSE, 'El producto ya fue anulado'); }

		$res = $this->runTx(function() use ($item, $id_item, $reason, $id_admin, $qty, $is_admin){
			$this->lockFor(array($item->id_product), $this->snapshotIngredientIds($id_item));
			$r = $this->doVoid($id_item, $reason, $id_admin, $qty, $is_admin);
			return array($r[0], $r[1]);
		});

		if( $res[2] ){ return array(FALSE, 'Hay mucha actividad en este momento, intente de nuevo'); }

		return array($res[0], $res[1]);
	}

	/**
	 * Swap $qty units of a sent line for $new_qty of another product, atomically: the old units are voided and restocked, the new
	 * product is ordered (same bill, same course) with its own stock guard. Any failure leaves everything untouched.
	 * Returns array(ok, message, id_order).
	 */
	public function replaceItem($id_item, $qty, $new, $new_qty, $notes, $reason, $id_admin, $is_admin, $mods = array()){

		$first = $this->item($id_item);
		if( !$first ){ return array(FALSE, 'El producto ya fue anulado', 0); }

		$res = $this->runTx(function() use ($first, $id_item, $qty, $new, $new_qty, $notes, $reason, $id_admin, $is_admin, $mods){

			// lock old + new product (and both recipes' ingredients) up front, in the global order
			$this->lockFor(array($first->id_product, $new->id_product), $this->snapshotIngredientIds($id_item));

			$v = $this->doVoid($id_item, $reason, $id_admin, $qty, $is_admin, TRUE);
			if( !$v[0] ){ return array(FALSE, array($v[1], 0)); }

			$old = $v[2];
			$line = array(
				'id_product' => (int) $new->id_product, 'name' => $new->name, 'quantity' => (int) $new_qty, 'price' => (float) $new->price_sell,
				'notes' => (string) $notes, 'course' => (int) $old->course, 'prints_kitchen' => (int) $new->prints_kitchen, 'modifiers' => $mods,
			);

			$id_order = $this->insertOrder($old->id_tab, $id_admin, array($line), $old->id_bill);
			if( $id_order < 1 ){
				$msg = $id_order === -1 ? 'Stock insuficiente de '.$new->name : 'Ingredientes insuficientes para '.$new->name.' (falta '.$this->short_ingredient.')';
				return array(FALSE, array($msg, 0));
			}

			return array(TRUE, array('Producto cambiado', $id_order));
		});

		if( $res[2] ){ return array(FALSE, 'Hay mucha actividad en este momento, intente de nuevo', 0); }

		return array($res[0], $res[1][0], $res[1][1]);
	}
}
