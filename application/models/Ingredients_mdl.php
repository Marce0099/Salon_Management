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

	public function allActive(){

		return $this->db->where('active', 1)->order_by('name')->get('ingredients')->result();
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

		return $this->db->query('SELECT r.id_ingredient, r.quantity, i.name, i.unit, i.cost_unit, i.stock
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

	/** Low-stock ingredients (stock <= threshold, threshold > 0). */
	public function lowStock(){

		return $this->db->query('SELECT * FROM ingredients WHERE active = 1 AND alert_threshold > 0 AND stock <= alert_threshold ORDER BY name')->result();
	}
}
