<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Order modifiers: a global catalog ("Sin queso", "Término medio") attached per dish. A modifier prints its kitchen text on the
 * comanda and may REMOVE ingredient amounts from the portion's stock deduction. It never changes the price.
 * Each order line keeps a snapshot (order_item_modifiers) so voids restock exactly what was taken.
 */
class Modifiers_mdl extends CI_Model{

	public function get($id){

		return $this->db->where('id_modifier', (int) $id)->get('modifiers')->row();
	}

	public function all($inactive = FALSE){

		return $this->db->where('active', $inactive ? 0 : 1)->order_by('name')->get('modifiers')->result();
	}

	public function nameTaken($name, $exclude = 0){

		$this->db->where('name', $name);
		if( $exclude ){ $this->db->where('id_modifier !=', (int) $exclude); }

		return $this->db->get('modifiers')->row();
	}

	/** Ingredient removals of a modifier: rows[id_ingredient, quantity (NULL = all), name, unit]. */
	public function ingredientsOf($id){

		return $this->db->query('SELECT m.id_ingredient, m.quantity, i.name, i.unit FROM modifier_ingredients m
			JOIN ingredients i ON i.id_ingredient = m.id_ingredient WHERE m.id_modifier = ? ORDER BY i.name', array((int) $id))->result();
	}

	/** Removal summary per modifier for the catalog list: array(id_modifier => "Queso (todo), Arroz (50 g)"). */
	public function summaries(){

		$out = array();
		$rows = $this->db->query('SELECT m.id_modifier, m.quantity, i.name, i.unit FROM modifier_ingredients m JOIN ingredients i ON i.id_ingredient = m.id_ingredient ORDER BY i.name')->result();
		foreach( $rows as $r ){
			$q = $r->quantity === NULL ? 'todo' : rtrim(rtrim(number_format((float) $r->quantity, 3, '.', ''), '0'), '.').' '.$r->unit;
			$out[(int) $r->id_modifier][] = $r->name.' ('.$q.')';
		}

		return $out;
	}

	/** Creates/updates a modifier and replaces its ingredient list atomically. $rows = array(id_ingredient => qty|NULL). Returns the id or 0. */
	public function save($id, $name, $text, $rows){

		$this->db->trans_begin();

		if( $id ){
			$this->db->where('id_modifier', (int) $id)->update('modifiers', array('name' => $name, 'kitchen_text' => $text));
		}
		else{
			$this->db->insert('modifiers', array('name' => $name, 'kitchen_text' => $text));
			$id = (int) $this->db->insert_id();
		}

		$this->db->where('id_modifier', (int) $id)->delete('modifier_ingredients');
		foreach( $rows as $id_ing => $qty ){
			$this->db->insert('modifier_ingredients', array('id_modifier' => (int) $id, 'id_ingredient' => (int) $id_ing, 'quantity' => $qty));
		}

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return 0;
		}

		$this->db->trans_commit();
		return (int) $id;
	}

	public function setActive($id, $active){

		$this->db->where('id_modifier', (int) $id)->update('modifiers', array('active' => $active ? 1 : 0));
	}

	// ---------- attach to dishes ----------

	/** Ids of the modifiers attached to a product. */
	public function attachedIds($id_product){

		$out = array();
		foreach( $this->db->where('id_product', (int) $id_product)->get('product_modifiers')->result() as $r ){ $out[] = (int) $r->id_modifier; }

		return $out;
	}

	/** Replaces the modifiers attached to a product. */
	public function setProduct($id_product, $ids){

		$this->db->trans_begin();
		$this->db->where('id_product', (int) $id_product)->delete('product_modifiers');
		foreach( $ids as $id ){ $this->db->insert('product_modifiers', array('id_product' => (int) $id_product, 'id_modifier' => (int) $id)); }

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return TRUE;
	}

	/** Active modifiers per product for the order screen: array(id_product => rows[id, name]). */
	public function menuMap(){

		$out = array();
		$rows = $this->db->query('SELECT pm.id_product, m.id_modifier AS id, m.name FROM product_modifiers pm JOIN modifiers m ON m.id_modifier = pm.id_modifier AND m.active = 1 ORDER BY m.name')->result();
		foreach( $rows as $r ){ $out[(int) $r->id_product][] = array('id' => (int) $r->id, 'name' => $r->name); }

		return $out;
	}

	// ---------- order lines ----------

	/**
	 * Validates the modifier ids picked for a dish and works out the stock effect. The ids must be distinct, active and attached to the
	 * product. Removals are clamped so that, summed over every modifier, an ingredient never loses more than the dish's recipe amount
	 * (a modifier on an ingredient the recipe lacks has no stock effect, only text).
	 * $recipe = Ingredients_mdl::recipesFor() rows of the product (or NULL).
	 * Returns array(error|NULL, mods) with mods = list of array(id, name, kitchen_text, removals => array(id_ingredient => qty per portion)).
	 */
	public function resolve($id_product, $ids, $recipe){

		if( !is_array($ids) ){ return array('Modificadores inválidos', array()); }
		if( !$ids ){ return array(NULL, array()); }
		if( count($ids) > 10 ){ return array('Demasiados modificadores', array()); }

		$clean = array();
		foreach( $ids as $id ){
			if( !is_scalar($id) || !preg_match('/^\d+$/', (string) $id) ){ return array('Modificador inválido', array()); }
			if( isset($clean[(int) $id]) ){ return array('Modificador repetido', array()); }
			$clean[(int) $id] = (int) $id;
		}

		$rows = $this->db->query('SELECT m.id_modifier, m.name, m.kitchen_text FROM modifiers m
			JOIN product_modifiers pm ON pm.id_modifier = m.id_modifier AND pm.id_product = ?
			WHERE m.active = 1 AND m.id_modifier IN ('.implode(',', $clean).') ORDER BY m.id_modifier', array((int) $id_product))->result();

		if( count($rows) !== count($clean) ){ return array('Hay un modificador que no corresponde a este platillo', array()); }

		$recipe_qty = array();
		foreach( (array) $recipe as $r ){ $recipe_qty[(int) $r->id_ingredient] = (float) $r->quantity; }

		$left = $recipe_qty; // what each ingredient can still lose
		$mods = array();

		foreach( $rows as $m ){

			$removals = array();
			foreach( $this->db->where('id_modifier', (int) $m->id_modifier)->get('modifier_ingredients')->result() as $mi ){

				$ing = (int) $mi->id_ingredient;
				if( !isset($left[$ing]) ){ continue; }

				$want = $mi->quantity === NULL ? $recipe_qty[$ing] : (float) $mi->quantity;
				$take = round(min($want, $left[$ing]), 3);
				if( $take <= 0 ){ continue; }

				$left[$ing] = round($left[$ing] - $take, 3);
				$removals[$ing] = $take;
			}

			$mods[] = array('id' => (int) $m->id_modifier, 'name' => $m->name, 'kitchen_text' => $m->kitchen_text, 'removals' => $removals);
		}

		return array(NULL, $mods);
	}

	/** Per-portion total removal of a line from its modifier snapshot: array(id_ingredient => qty). */
	public static function removalMap($mods){

		$out = array();
		foreach( (array) $mods as $m ){
			foreach( $m['removals'] as $ing => $q ){ $out[(int) $ing] = round((isset($out[(int) $ing]) ? $out[(int) $ing] : 0) + $q, 3); }
		}

		return $out;
	}

	/** Writes the snapshot rows of a freshly inserted order line. */
	public function saveForItem($id_order_item, $mods){

		foreach( (array) $mods as $m ){

			if( !$m['removals'] ){
				$this->db->insert('order_item_modifiers', array('id_order_item' => (int) $id_order_item, 'id_modifier' => $m['id'], 'name' => $m['name'], 'kitchen_text' => $m['kitchen_text']));
				continue;
			}

			foreach( $m['removals'] as $ing => $q ){
				$this->db->insert('order_item_modifiers', array('id_order_item' => (int) $id_order_item, 'id_modifier' => $m['id'], 'name' => $m['name'],
					'kitchen_text' => $m['kitchen_text'], 'id_ingredient' => (int) $ing, 'qty_removed' => $q));
			}
		}
	}

	/** Per-portion removal snapshot of one order line: array(id_ingredient => qty). */
	public function snapshotRemoval($id_order_item){

		$out = array();
		$rows = $this->db->query('SELECT id_ingredient, SUM(qty_removed) AS q FROM order_item_modifiers WHERE id_order_item = ? AND id_ingredient IS NOT NULL GROUP BY id_ingredient', array((int) $id_order_item))->result();
		foreach( $rows as $r ){ $out[(int) $r->id_ingredient] = round((float) $r->q, 3); }

		return $out;
	}

	/** Copies the snapshot rows of a line to its clone (partial void / partial move). */
	public function cloneRows($from_item, $to_item){

		$this->db->query('INSERT INTO order_item_modifiers (id_order_item, id_modifier, name, kitchen_text, id_ingredient, qty_removed)
			SELECT ?, id_modifier, name, kitchen_text, id_ingredient, qty_removed FROM order_item_modifiers WHERE id_order_item = ?', array((int) $to_item, (int) $from_item));
	}

	/** Kitchen texts per order line: array(id_order_item => array of distinct texts). */
	public function textsFor($item_ids){

		$out = array();
		if( !$item_ids ){ return $out; }

		$ids = implode(',', array_map('intval', $item_ids));
		$rows = $this->db->query('SELECT id_order_item, kitchen_text FROM order_item_modifiers WHERE id_order_item IN ('.$ids.') GROUP BY id_order_item, id_modifier, kitchen_text ORDER BY MIN(id_oim)')->result();
		foreach( $rows as $r ){ $out[(int) $r->id_order_item][] = $r->kitchen_text; }

		return $out;
	}

	/** Adds ->mods (array of kitchen texts) to each order line object. */
	public function attachTexts($items){

		$ids = array();
		foreach( $items as $i ){ $ids[] = (int) $i->id_order_item; }
		$map = $this->textsFor($ids);

		foreach( $items as $i ){ $i->mods = isset($map[(int) $i->id_order_item]) ? $map[(int) $i->id_order_item] : array(); }

		return $items;
	}
}
