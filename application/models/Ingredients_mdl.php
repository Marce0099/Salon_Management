<?php if( !defined('BASEPATH') ){ exit(); }

/** Ingredients, recipes and the stock movement log. Stock is one DECIMAL per ingredient in its base unit (g, ml, u). */
class Ingredients_mdl extends CI_Model{

	public static $units = array('g' => 'g', 'ml' => 'ml', 'u' => 'unid.');

	public function get($id){

		return $this->db->where('id_ingredient', (int) $id)->get('ingredients')->row();
	}

	public function nameTaken($name, $exclude = 0){

		$this->db->where('name', $name);
		if( $exclude ){ $this->db->where('id_ingredient !=', (int) $exclude); }

		return $this->db->get('ingredients')->row();
	}

	/**
	 * SQL for the cost per base unit: the stored cost for raw ingredients; for prepared ones the live roll-up
	 * sum(component cost * qty) / batch_yield (NULL when a component has no cost, so callers flag it as missing).
	 */
	public static function costSql($a = 'i'){

		return 'CASE WHEN '.$a.'.batch_yield IS NOT NULL AND '.$a.'.batch_yield > 0 THEN
			(CASE WHEN EXISTS(SELECT 1 FROM ingredient_components ic JOIN ingredients ci ON ci.id_ingredient = ic.id_component WHERE ic.id_ingredient = '.$a.'.id_ingredient AND ci.cost_unit IS NULL)
				OR NOT EXISTS(SELECT 1 FROM ingredient_components ic WHERE ic.id_ingredient = '.$a.'.id_ingredient) THEN NULL
			ELSE (SELECT SUM(ci.cost_unit * ic.quantity) FROM ingredient_components ic JOIN ingredients ci ON ci.id_ingredient = ic.id_component WHERE ic.id_ingredient = '.$a.'.id_ingredient) / '.$a.'.batch_yield END)
			ELSE '.$a.'.cost_unit END';
	}

	/** Active ingredients with eff_cost (the effective cost per base unit, see costSql). */
	public function allActive(){

		return $this->db->query('SELECT i.*, '.self::costSql('i').' AS eff_cost FROM ingredients i WHERE i.active = 1 ORDER BY i.name')->result();
	}

	/** Writes a movement row; $qty is signed (base units). */
	public function log($id_ingredient, $type, $qty, $reason, $id_admin, $id_order_item = NULL){

		$this->db->insert('ingredient_movements', array(
			'id_ingredient' => (int) $id_ingredient,
			'type'          => $type,
			'quantity'      => $qty,
			'reason'        => $reason !== NULL && $reason !== '' ? mb_substr($reason, 0, 255, 'UTF-8') : NULL,
			'id_admin'      => $id_admin ? (int) $id_admin : NULL,
			'id_order_item' => $id_order_item ? (int) $id_order_item : NULL,
		));
	}

	/** Adds (or removes, if negative) stock atomically and logs it. A removal never drops below zero. Returns TRUE/FALSE. */
	public function move($id_ingredient, $type, $qty, $reason, $id_admin){

		$this->db->trans_begin();

		if( $qty >= 0 ){
			$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ?', array($qty, (int) $id_ingredient));
		}
		else{
			$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ? AND stock >= ?', array($qty, (int) $id_ingredient, abs($qty)));
		}

		if( $this->db->affected_rows() < 1 || $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->log($id_ingredient, $type, $qty, $reason, $id_admin);
		$this->db->trans_commit();

		return TRUE;
	}

	/** Receiving: also refreshes the cost per base unit when the purchase cost is given. */
	public function receive($ing, $qty_base, $total_cost, $reason, $id_admin){

		if( !$this->move($ing->id_ingredient, 'receive', $qty_base, $reason, $id_admin) ){
			return FALSE;
		}

		if( $total_cost !== NULL && $total_cost > 0 && $qty_base > 0 ){
			$this->db->where('id_ingredient', (int) $ing->id_ingredient)->update('ingredients', array('cost_unit' => round($total_cost / $qty_base, 6)));
		}

		return TRUE;
	}


	/** Physical count: sets the stock to $counted and logs the difference as an adjustment. Returns the delta or FALSE. */
	public function count($id_ingredient, $counted, $reason, $id_admin){

		$this->db->trans_begin();
		$row = $this->db->query('SELECT stock FROM ingredients WHERE id_ingredient = ? FOR UPDATE', array((int) $id_ingredient))->row();

		if( !$row ){
			$this->db->trans_rollback();
			return FALSE;
		}

		$delta = round($counted - (float) $row->stock, 3);
		$this->db->where('id_ingredient', (int) $id_ingredient)->update('ingredients', array('stock' => $counted));
		$this->log($id_ingredient, 'adjustment', $delta, $reason, $id_admin);

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $delta;
	}

	public function movements($id_ingredient, $limit = 100){

		return $this->db->query('SELECT m.*, a.username FROM ingredient_movements m LEFT JOIN admins a ON a.id_admin = m.id_admin
			WHERE m.id_ingredient = ? ORDER BY m.id_movement DESC LIMIT '.(int) $limit, array((int) $id_ingredient))->result();
	}

	// ---------- recipes ----------

	public function recipe($id_product){

		return $this->db->query('SELECT r.id_ingredient, r.quantity, i.name, i.unit, '.self::costSql('i').' AS cost_unit, i.stock
			FROM recipe_items r JOIN ingredients i ON i.id_ingredient = r.id_ingredient
			WHERE r.id_product = ? ORDER BY i.name', array((int) $id_product))->result();
	}

	/** Replaces the whole recipe atomically. $rows = array(id_ingredient => quantity). */
	public function saveRecipe($id_product, $rows){

		$this->db->trans_begin();
		$this->db->where('id_product', (int) $id_product)->delete('recipe_items');

		foreach( $rows as $id_ing => $qty ){
			$this->db->insert('recipe_items', array('id_product' => (int) $id_product, 'id_ingredient' => (int) $id_ing, 'quantity' => $qty));
		}

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}

	/** Cost of one portion from the current ingredient costs (ingredients without cost count as 0 and are flagged). */
	public function portionCost($recipe){

		$cost = 0.0;
		$missing = FALSE;

		foreach( $recipe as $r ){
			if( $r->cost_unit === NULL ){ $missing = TRUE; continue; }
			$cost += (float) $r->cost_unit * (float) $r->quantity;
		}

		return array($cost, $missing);
	}

	/** Recipe rows of many products at once: array(id_product => rows[id_ingredient, quantity, name, stock]). */
	public function recipesFor($ids){

		$out = array();
		if( !$ids ){ return $out; }

		$ids = array_map('intval', $ids);
		$rows = $this->db->query('SELECT r.id_product, r.id_ingredient, r.quantity, i.name, i.stock, i.unit
			FROM recipe_items r JOIN ingredients i ON i.id_ingredient = r.id_ingredient
			WHERE r.id_product IN ('.implode(',', $ids).')')->result();

		foreach( $rows as $r ){
			$out[(int) $r->id_product][] = $r;
		}

		return $out;
	}

	/** Portions that the current ingredient stock covers (min across the recipe); NULL when the product has no recipe. */
	public static function portions($recipe_rows){

		if( !$recipe_rows ){ return NULL; }

		$min = NULL;
		foreach( $recipe_rows as $r ){
			$q = (float) $r->quantity;
			$p = $q > 0 ? (int) floor(((float) $r->stock + 0.0005) / $q) : 0;
			$min = $min === NULL ? $p : min($min, $p);
		}

		return max(0, (int) $min);
	}

	// ---------- sub-recipes (prepared ingredients) ----------

	/** Components of a prepared ingredient: rows[id_component, quantity, name, unit, stock, cost_unit, active]. */
	public function components($id){

		return $this->db->query('SELECT c.id_component, c.quantity, i.name, i.unit, i.stock, i.cost_unit, i.active
			FROM ingredient_components c JOIN ingredients i ON i.id_ingredient = c.id_component
			WHERE c.id_ingredient = ? ORDER BY i.name', array((int) $id))->result();
	}

	/** components() of every prepared ingredient: array(id_ingredient => rows). */
	public function allComponents(){

		$out = array();
		$rows = $this->db->query('SELECT c.id_ingredient, c.id_component, c.quantity, i.name, i.unit, i.stock
			FROM ingredient_components c JOIN ingredients i ON i.id_ingredient = c.id_component ORDER BY i.name')->result();
		foreach( $rows as $r ){ $out[(int) $r->id_ingredient][] = $r; }

		return $out;
	}

	/** Active prepared ingredients that use $id_component. */
	public function usedAsComponentBy($id_component){

		return $this->db->query('SELECT i.name FROM ingredient_components c JOIN ingredients i ON i.id_ingredient = c.id_ingredient
			WHERE c.id_component = ? AND i.active = 1', array((int) $id_component))->result();
	}

	/**
	 * Makes $id a prepared ingredient (yield + components) or, with $yield NULL, a raw one again. NO transaction of its own:
	 * the caller wraps it with the ingredient row write. $rows = array(id_component => quantity per batch).
	 */
	public function applyPrepared($id, $yield, $rows){

		$this->db->where('id_ingredient', (int) $id)->delete('ingredient_components');
		$this->db->where('id_ingredient', (int) $id)->update('ingredients', array('batch_yield' => $yield));

		foreach( $rows as $id_comp => $qty ){
			$this->db->insert('ingredient_components', array('id_ingredient' => (int) $id, 'id_component' => (int) $id_comp, 'quantity' => $qty));
		}
	}

	/**
	 * Cooks $batches batches of prepared ingredient $id: components leave, the prepared stock enters, all in one transaction.
	 * Rows are locked in ascending id (the same global order the kitchen uses) and the whole batch is checked against the locked stock.
	 * Returns array(ok, message).
	 */
	public function prepare($id, $batches, $id_admin){

		for( $try = 0; $try < 3; $try++ ){

			try{
				$this->db->trans_begin();
				$r = $this->doPrepare((int) $id, (float) $batches, $id_admin);

				if( $r[0] ){ $this->db->trans_commit(); } else { $this->db->trans_rollback(); }

				return $r;
			}
			catch( Exception $e ){

				log_message('error', 'Prepare batch failed (try '.($try + 1).'): '.$e->getMessage());
				try{ $this->db->trans_rollback(); }catch( Exception $e2 ){ /* already rolled back by the server */ }
				usleep(mt_rand(20000, 90000));
			}
		}

		return array(FALSE, 'Hay mucha actividad en este momento, intente de nuevo');
	}

	private function doPrepare($id, $batches, $id_admin){

		$comps = $this->db->query('SELECT id_component, quantity FROM ingredient_components WHERE id_ingredient = ?', array($id))->result();
		if( !$comps ){ return array(FALSE, 'Este ingrediente no tiene componentes definidos'); }

		$ids = array($id);
		foreach( $comps as $c ){ $ids[] = (int) $c->id_component; }
		sort($ids);

		$locked = array();
		foreach( $this->db->query('SELECT id_ingredient, name, unit, stock, active, batch_yield FROM ingredients WHERE id_ingredient IN ('.implode(',', $ids).') ORDER BY id_ingredient FOR UPDATE')->result() as $row ){
			$locked[(int) $row->id_ingredient] = $row;
		}

		$target = isset($locked[$id]) ? $locked[$id] : NULL;
		if( !$target || !$target->active || $target->batch_yield === NULL || (float) $target->batch_yield <= 0 ){
			return array(FALSE, 'Ingrediente inválido o no se prepara en la cocina');
		}

		// The component list could have been edited between the first read and the locks: read it again under the lock.
		$fresh = $this->db->query('SELECT id_component, quantity FROM ingredient_components WHERE id_ingredient = ?', array($id))->result();
		$a = array(); foreach( $comps as $c ){ $a[(int) $c->id_component] = round((float) $c->quantity, 3); }
		$b = array(); foreach( $fresh as $c ){ $b[(int) $c->id_component] = round((float) $c->quantity, 3); }
		ksort($a); ksort($b);
		if( $a !== $b ){ return array(FALSE, 'La receta del lote cambió, intente de nuevo'); }

		$need = array();
		foreach( $comps as $c ){
			$cid = (int) $c->id_component;
			$q = round((float) $c->quantity * $batches, 3);
			$comp = isset($locked[$cid]) ? $locked[$cid] : NULL;
			if( !$comp || !$comp->active ){ return array(FALSE, 'El componente "'.($comp ? $comp->name : '?').'" está desactivado'); }
			if( round((float) $comp->stock, 3) < $q ){
				$short = rtrim(rtrim(number_format($q - (float) $comp->stock, 3, '.', ''), '0'), '.');
				return array(FALSE, 'No alcanza '.$comp->name.' (faltan '.$short.' '.$comp->unit.')');
			}
			$need[$cid] = $q;
		}

		ksort($need);
		$label = rtrim(rtrim(number_format($batches, 2, '.', ''), '0'), '.');
		foreach( $need as $cid => $q ){
			$this->db->query('UPDATE ingredients SET stock = stock - ? WHERE id_ingredient = ? AND stock >= ?', array($q, $cid, $q));
			if( $this->db->affected_rows() < 1 ){ return array(FALSE, 'No alcanza '.$locked[$cid]->name); }
			$this->log($cid, 'prepare_out', -$q, 'Lote de '.$target->name.' x'.$label, $id_admin);
		}

		$made = round((float) $target->batch_yield * $batches, 3);
		$this->db->query('UPDATE ingredients SET stock = stock + ? WHERE id_ingredient = ?', array($made, $id));
		$this->log($id, 'prepare_in', $made, $label.' lote(s)', $id_admin);

		return array(TRUE, 'Lote preparado: +'.rtrim(rtrim(number_format($made, 3, '.', ''), '0'), '.').' '.$target->unit.' de '.$target->name);
	}

	/** Low-stock ingredients (stock <= threshold, threshold > 0). */
	public function lowStock(){

		return $this->db->query('SELECT * FROM ingredients WHERE active = 1 AND alert_threshold > 0 AND stock <= alert_threshold ORDER BY name')->result();
	}

	public function lowStockCount(){

		return (int) $this->db->query('SELECT COUNT(*) AS n FROM ingredients WHERE active = 1 AND alert_threshold > 0 AND stock <= alert_threshold')->row()->n;
	}

	/**
	 * Usage per ingredient in [from, to] (Y-m-d) from the movement log, all in base units.
	 * consumed = orders net of voids (theoretical, by recipe); waste; counted = signed physical-count adjustments
	 * (negative = less on the shelf than the system said); received. Sorted by consumption, most used first.
	 */
	public function usage($from, $to){

		$rows = $this->db->query('SELECT i.id_ingredient, i.name, i.unit, i.stock, '.self::costSql('i').' AS cost_unit,
				IFNULL(SUM(CASE WHEN m.type IN ("order","void") THEN -m.quantity END), 0) AS consumed,
				IFNULL(SUM(CASE WHEN m.type = "waste" THEN -m.quantity END), 0) AS waste,
				IFNULL(SUM(CASE WHEN m.type = "adjustment" THEN m.quantity END), 0) AS counted,
				IFNULL(SUM(CASE WHEN m.type = "receive" THEN m.quantity END), 0) AS received,
				IFNULL(SUM(CASE WHEN m.type = "prepare_out" THEN -m.quantity END), 0) AS used_in_batches,
				IFNULL(SUM(CASE WHEN m.type = "prepare_in" THEN m.quantity END), 0) AS produced
			FROM ingredients i
			JOIN ingredient_movements m ON m.id_ingredient = i.id_ingredient AND DATE(m.created_at) BETWEEN ? AND ?
			GROUP BY i.id_ingredient, i.name, i.unit, i.stock, i.cost_unit, i.batch_yield
			ORDER BY consumed DESC, i.name', array($from, $to))->result();

		foreach( $rows as $r ){
			foreach( array('stock','consumed','waste','counted','received','used_in_batches','produced') as $k ){ $r->$k = (float) $r->$k; }
			$r->cost_unit = $r->cost_unit === NULL ? NULL : (float) $r->cost_unit;
		}

		return $rows;
	}
}
