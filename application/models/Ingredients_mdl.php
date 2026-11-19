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

		if( $type !== 'order' && $type !== 'void' ){

			$ing = $this->db->select('name, unit')->where('id_ingredient', (int) $id_ingredient)->get('ingredients')->row();
			$this->audit->log('ingredient.'.$type, 'ingredient', $id_ingredient, array(
				'summary' => 'Ingrediente '.($ing ? $ing->name : $id_ingredient).': '.$type.' '.($qty >= 0 ? '+' : '').$qty.($ing ? ' '.$ing->unit : '').($reason ? ' ('.mb_substr($reason, 0, 80, 'UTF-8').')' : ''),
				'after' => array('type' => $type, 'quantity' => (float) $qty, 'reason' => $reason)
			));
		}
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

	// ---------- presentations (packs) ----------

	/** Presentations of one ingredient, largest first. */
	public function getPacks($id_ingredient){

		return $this->db->where('id_ingredient', (int) $id_ingredient)->order_by('size', 'DESC')->get('ingredient_packs')->result();
	}

	/** All presentations of the active ingredients, grouped: array(id_ingredient => array(pack rows)). */
	public function packsByIngredient(){

		$out = array();
		$rows = $this->db->query('SELECT p.* FROM ingredient_packs p JOIN ingredients i ON i.id_ingredient = p.id_ingredient WHERE i.active = 1 ORDER BY p.size DESC')->result();
		foreach( $rows as $r ){ $out[(int) $r->id_ingredient][] = $r; }

		return $out;
	}

	/**
	 * Replaces the presentations of an ingredient: rows with a known id are updated in place (ids stay stable), new ones inserted,
	 * the rest deleted (nothing references pack ids: purchase lines keep the pack name as text). NO transaction of its own.
	 * $packs = array(array('id_pack' => int|0, 'name' => string, 'size' => float)).
	 */
	public function savePacks($id_ingredient, $packs){

		$id_ingredient = (int) $id_ingredient;
		$current = array();
		foreach( $this->getPacks($id_ingredient) as $p ){ $current[(int) $p->id_pack] = $p; }
		$packs_before = array();
		foreach( $current as $cp ){ $packs_before[] = array('name' => $cp->name, 'size' => (float) $cp->size); }

		$keep = array();
		foreach( $packs as $p ){
			$id = (int) $p['id_pack'];
			if( $id && isset($current[$id]) ){
				$keep[$id] = TRUE;
			}
		}

		foreach( array_keys($current) as $id ){
			if( !isset($keep[$id]) ){ $this->db->where('id_pack', $id)->delete('ingredient_packs'); }
		}

		// two passes: renames first to a temporary unique name so swapping names between two packs cannot trip the unique key
		foreach( $packs as $p ){
			$id = (int) $p['id_pack'];
			if( $id && isset($keep[$id]) ){
				$this->db->where('id_pack', $id)->update('ingredient_packs', array('name' => '~'.$id.'~'));
			}
		}

		foreach( $packs as $p ){
			$id = (int) $p['id_pack'];
			if( $id && isset($keep[$id]) ){
				$this->db->where('id_pack', $id)->update('ingredient_packs', array('name' => $p['name'], 'size' => $p['size']));
			}
			else{
				$this->db->insert('ingredient_packs', array('id_ingredient' => $id_ingredient, 'name' => $p['name'], 'size' => $p['size']));
			}
		}

		$packs_after = array();
		foreach( $this->getPacks($id_ingredient) as $cp ){ $packs_after[] = array('name' => $cp->name, 'size' => (float) $cp->size); }
		if( json_encode($packs_before) !== json_encode($packs_after) ){
			$this->audit->log('ingredient.packs', 'ingredient', $id_ingredient, array('summary' => 'Presentaciones de ingrediente modificadas', 'before' => array('packs' => $packs_before), 'after' => array('packs' => $packs_after)));
		}
	}

	// ---------- centralized purchase / count ----------

	/** Runs $fn inside a transaction, retrying deadlocks/lock timeouts up to 3 times. $fn returns array(ok, message, ...). */
	private function runTx($fn){

		for( $try = 0; $try < 3; $try++ ){

			try{
				$this->db->trans_begin();
				$r = $fn();

				if( $r[0] && $this->db->trans_status() !== FALSE ){ $this->db->trans_commit(); return $r; }

				$this->db->trans_rollback();
				return $r[0] ? array(FALSE, 'No se pudo guardar, intente de nuevo') : $r;
			}
			catch( Exception $e ){

				log_message('error', 'Ingredient bulk transaction failed (try '.($try + 1).'): '.$e->getMessage());
				try{ $this->db->trans_rollback(); }catch( Exception $e2 ){ /* already rolled back by the server */ }
				usleep(mt_rand(20000, 90000));
			}
		}

		return array(FALSE, 'Hay mucha actividad en este momento, intente de nuevo');
	}

	/**
	 * Saves a purchase receipt: every line adds stock and a 'receive' movement, the cost per base unit becomes the weighted average
	 * of the existing stock and the purchase, all in ONE transaction (rows locked in ascending id, like the kitchen).
	 * $lines = array(array(id_ingredient, pack_name|NULL, pack_qty, base_qty, line_cost|NULL)); $header = array(id_provider|NULL, note, purchased_at).
	 * Returns array(ok, message, id_purchase).
	 */
	public function receivePurchase($lines, $header, $id_admin){

		$self = $this;

		return $this->runTx(function() use ($self, $lines, $header, $id_admin){

			$ids = array();
			foreach( $lines as $l ){ $ids[(int) $l['id_ingredient']] = TRUE; }
			$ids = array_keys($ids);
			sort($ids);

			$rows = $self->db->query('SELECT id_ingredient, name, stock, cost_unit, batch_yield, active FROM ingredients
				WHERE id_ingredient IN ('.implode(',', array_map('intval', $ids)).') ORDER BY id_ingredient FOR UPDATE')->result();

			$state = array();
			foreach( $rows as $r ){ $state[(int) $r->id_ingredient] = $r; }

			$total = 0;
			$total_cor = 0;
			foreach( $lines as $l ){
				$s = isset($state[(int) $l['id_ingredient']]) ? $state[(int) $l['id_ingredient']] : NULL;
				if( !$s || !$s->active ){ return array(FALSE, 'Un ingrediente de la compra ya no está disponible'); }
				if( $s->batch_yield !== NULL ){ return array(FALSE, $s->name.' se prepara en la cocina: use "Preparar lote"'); }
				if( $l['line_cost'] !== NULL ){ $total += $l['line_cost']; $total_cor += $l['line_cost_cor']; }
			}

			$self->db->insert('ingredient_purchases', array(
				'id_provider'  => $header['id_provider'],
				'note'         => $header['note'],
				'purchased_at' => $header['purchased_at'],
				'total_cost'   => round($total, 4),
				'dollar_ex_sell' => $header['dollar_ex_sell'],
				'total_cost_cor' => round($total_cor, 2),
				'id_admin'     => $id_admin ? (int) $id_admin : NULL,
			));
			$id_purchase = (int) $self->db->insert_id();
			$audit_lines = array();

			foreach( $lines as $l ){

				$id   = (int) $l['id_ingredient'];
				$s    = $state[$id];
				$base = (float) $l['base_qty'];
				$cost = $l['line_cost'];
				$new_cost = $s->cost_unit;

				if( $cost !== NULL && $cost > 0 && $base > 0 ){
					$unit_now = $cost / $base;
					$new_cost = ($s->cost_unit === NULL || (float) $s->stock <= 0)
						? $unit_now
						: ((float) $s->stock * (float) $s->cost_unit + $cost) / ((float) $s->stock + $base);
					$new_cost = round($new_cost, 6);
				}

				$audit_lines[] = array('id_ingredient' => $id, 'base_qty' => $base, 'pack' => $l['pack_name'], 'pack_qty' => $l['pack_qty'], 'line_cost_cor' => $l['line_cost_cor'],
					'stock_before' => (float) $s->stock, 'stock_after' => round((float) $s->stock + $base, 3), 'cost_unit_old' => $s->cost_unit, 'cost_unit_new' => $new_cost);

				$self->db->query('UPDATE ingredients SET stock = stock + ?, cost_unit = ? WHERE id_ingredient = ?', array($base, $new_cost, $id));
				$self->log($id, 'receive', $base, 'Compra #'.$id_purchase, $id_admin);

				$s->stock = (float) $s->stock + $base; // in-memory state so a repeated ingredient averages correctly
				$s->cost_unit = $new_cost;

				$self->db->insert('ingredient_purchase_lines', array(
					'id_purchase'   => $id_purchase,
					'id_ingredient' => $id,
					'pack_name'     => $l['pack_name'],
					'pack_qty'      => $l['pack_qty'],
					'base_qty'      => $base,
					'line_cost'     => $cost,
					'line_cost_cor' => $l['line_cost_cor'],
				));
			}

			$self->audit->log('ingredient.purchase', 'ingredient_purchase', $id_purchase, array(
				'summary' => 'Compra de ingredientes #'.$id_purchase.' ('.count($audit_lines).' linea(s))',
				'after' => array('id_purchase' => $id_purchase, 'header' => $header, 'lines' => $audit_lines)
			));

			return array(TRUE, 'Compra registrada', $id_purchase);
		});
	}

	/**
	 * Physical count of many ingredients at once. $counts = array(id_ingredient => counted quantity in base units).
	 * Only rows whose count differs from the (locked) stock are written, each as an 'adjustment' movement with the delta.
	 * Returns array(ok, message, changed, rows) where rows = array(array(name, unit, before, after, delta)).
	 */
	public function countMany($counts, $reason, $id_admin){

		$self = $this;

		return $this->runTx(function() use ($self, $counts, $reason, $id_admin){

			$ids = array_map('intval', array_keys($counts));
			sort($ids);

			$rows = $self->db->query('SELECT id_ingredient, name, unit, stock, active FROM ingredients
				WHERE id_ingredient IN ('.implode(',', $ids).') ORDER BY id_ingredient FOR UPDATE')->result();

			$changed = array();
			foreach( $rows as $r ){

				if( !$r->active ){ continue; }

				$counted = round((float) $counts[(int) $r->id_ingredient], 3);
				$delta   = round($counted - (float) $r->stock, 3);
				if( $delta == 0 ){ continue; }

				$self->db->where('id_ingredient', (int) $r->id_ingredient)->update('ingredients', array('stock' => $counted));
				$self->log($r->id_ingredient, 'adjustment', $delta, $reason, $id_admin);
				$changed[] = array('name' => $r->name, 'unit' => $r->unit, 'before' => (float) $r->stock, 'after' => $counted, 'delta' => $delta);
			}

			return array(TRUE, 'Conteo guardado', count($changed), $changed);
		});
	}

	/** Latest saved purchases with their line count. */
	public function purchases($limit = 100){

		return $this->db->query('SELECT p.*, pr.provider AS store, a.username, (SELECT COUNT(*) FROM ingredient_purchase_lines l WHERE l.id_purchase = p.id_purchase) AS n_lines
			FROM ingredient_purchases p LEFT JOIN admins a ON a.id_admin = p.id_admin LEFT JOIN providers pr ON pr.id_provider = p.id_provider ORDER BY p.id_purchase DESC LIMIT '.(int) $limit)->result();
	}

	public function purchase($id){

		return $this->db->query('SELECT p.*, pr.provider AS store, a.username FROM ingredient_purchases p LEFT JOIN admins a ON a.id_admin = p.id_admin LEFT JOIN providers pr ON pr.id_provider = p.id_provider WHERE p.id_purchase = ?', array((int) $id))->row();
	}

	public function purchaseLines($id){

		return $this->db->query('SELECT l.*, i.name, i.unit FROM ingredient_purchase_lines l JOIN ingredients i ON i.id_ingredient = l.id_ingredient
			WHERE l.id_purchase = ? ORDER BY l.id_line', array((int) $id))->result();
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
		$before = $this->db->select('id_ingredient, quantity')->where('id_product', (int) $id_product)->get('recipe_items')->result_array();
		$this->db->where('id_product', (int) $id_product)->delete('recipe_items');

		foreach( $rows as $id_ing => $qty ){
			$this->db->insert('recipe_items', array('id_product' => (int) $id_product, 'id_ingredient' => (int) $id_ing, 'quantity' => $qty));
		}

		$after = $this->db->select('id_ingredient, quantity')->where('id_product', (int) $id_product)->get('recipe_items')->result_array();
		if( json_encode($before) !== json_encode($after) ){
			$this->audit->log('recipe.update', 'product', $id_product, array('summary' => 'Receta modificada (producto #'.(int) $id_product.')', 'before' => array('recipe' => $before), 'after' => array('recipe' => $after)));
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

		$before = array('batch_yield' => $this->db->select('batch_yield')->where('id_ingredient', (int) $id)->get('ingredients')->row('batch_yield'),
			'components' => $this->db->select('id_component, quantity')->where('id_ingredient', (int) $id)->get('ingredient_components')->result_array());
		$this->db->where('id_ingredient', (int) $id)->delete('ingredient_components');
		$this->db->where('id_ingredient', (int) $id)->update('ingredients', array('batch_yield' => $yield));

		foreach( $rows as $id_comp => $qty ){
			$this->db->insert('ingredient_components', array('id_ingredient' => (int) $id, 'id_component' => (int) $id_comp, 'quantity' => $qty));
		}

		$after = array('batch_yield' => $yield, 'components' => $this->db->select('id_component, quantity')->where('id_ingredient', (int) $id)->get('ingredient_components')->result_array());
		if( json_encode($before) !== json_encode($after) ){
			$this->audit->log('ingredient.components', 'ingredient', $id, array('summary' => 'Sub-receta de ingrediente modificada', 'before' => $before, 'after' => $after));
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
