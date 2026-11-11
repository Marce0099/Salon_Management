<?php if( !defined('BASEPATH') ){ exit(); }

/** Ingredient catalog (admin): CRUD, receiving, waste/count adjustments, movement history and per-dish recipes. */
class Ingredients extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('ingredients_mdl');
		$this->load->model('modifiers_mdl');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	private function requirePost(){

		if( !formSubmited() ){
			$this->fail('Sesión inválida, recargue la página e intente de nuevo');
		}
	}

	private function ok($message, $extra = array()){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		$this->json(array_merge(array('type' => 'success', 'message' => $message, 'tk' => $tk), $extra));
	}

	/** Positive decimal from user text ("1,5" ok); NULL when invalid. */
	private function num($v, $allow_zero = FALSE){

		$v = str_replace(',', '.', trim((string) $v));
		if( $v === '' || !is_numeric($v) ){ return NULL; }

		$f = (float) $v;
		if( $f < 0 || ($f == 0 && !$allow_zero) || $f > 99999999 ){ return NULL; }

		return round($f, 3);
	}

	private function token(){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		return $tk;
	}

	public function index(){

		$show_inactive = (int) $this->input->get('inactive') === 1;
		$q = trim((string) $this->input->get('name'));

		$this->db->select('ingredients.*, '.Ingredients_mdl::costSql('ingredients').' AS eff_cost', FALSE)->from('ingredients')->where('active', $show_inactive ? 0 : 1);
		if( $q !== '' ){ $this->db->like('name', $q); }
		$rows = $this->db->order_by('name')->get()->result();

		$this->footer_data = load_data($this->session);
		$this->content_data = array_merge(array(
			'ingredients'   => $rows,
			'low'           => $this->ingredients_mdl->lowStock(),
			'show_inactive' => $show_inactive,
			'q'             => $q,
			'tk'            => $this->token(),
			'units'         => Ingredients_mdl::$units,
			'components'    => $this->ingredients_mdl->allComponents(),
		), array());

		$this->renderPanel('ingredients');
	}

	/** Ingredient usage report: theoretical consumption (orders - voids) vs waste and physical-count differences. */
	public function usage(){

		$ok = function($v, $d){ return ( is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== FALSE ) ? $v : $d; };
		$from = $ok($this->input->get('from'), date('Y-m-01'));
		$to   = $ok($this->input->get('to'), date('Y-m-d'));
		if( $from > $to ){ list($from, $to) = array($to, $from); }

		$this->footer_data = load_data($this->session);
		$this->content_data = array('rows' => $this->ingredients_mdl->usage($from, $to), 'from' => $from, 'to' => $to);
		$this->renderPanel('ingredient_usage');
	}

	/** "Preparar lote": converts the raw components into the prepared ingredient (batches > 0, up to 2 decimals). */
	public function prepare(){

		$this->requirePost();

		$ing = $this->ingredients_mdl->get($this->input->post('id_ingredient'));
		if( !$ing || !$ing->active || $ing->batch_yield === NULL ){ $this->fail('Ingrediente inválido'); }

		$t = str_replace(',', '.', trim((string) $this->input->post('batches')));
		if( !preg_match('/^\d{1,4}(\.\d{1,2})?$/', $t) || (float) $t <= 0 ){ $this->fail('Cantidad de lotes inválida (mayor que 0, hasta 9999)'); }

		$r = $this->ingredients_mdl->prepare($ing->id_ingredient, (float) $t, $this->id_admin);
		if( !$r[0] ){ $this->fail($r[1]); }

		$this->ok($r[1]);
	}

	public function register(){

		$this->form(0);
	}

	public function edit($id = 0){

		$this->form((int) $id);
	}

	private function form($id){

		$view = array('tk' => $this->token(), 'units' => Ingredients_mdl::$units, 'raw_choices' => $this->db->query('SELECT id_ingredient, name, unit, cost_unit FROM ingredients WHERE active = 1 AND batch_yield IS NULL AND id_ingredient != ? ORDER BY name', array((int) $id))->result(), 'components' => array());

		if( $id ){
			$ing = $this->ingredients_mdl->get($id);
			if( !$ing ){
				$this->session->set_userdata('view_data', array('status' => array('class' => 'error', 'message' => 'Ingrediente inválido')));
				redirect('ingredients');
			}
			$view['ingredient'] = $ing;
			$view['components'] = $this->ingredients_mdl->components($id);
		}

		$this->content_data = $view;
		$this->renderPanel('form_ingredients');
	}

	/** Create / edit (POST). Stock is only set on creation; later changes go through receive/waste/count so they are logged. */
	public function save($id = 0){

		$this->requirePost();
		$id = (int) $id;

		$name = trim(preg_replace('/\s+/', ' ', (string) $this->input->post('name')));
		$unit = (string) $this->input->post('unit');

		if( $name === '' || mb_strlen($name, 'UTF-8') > 100 ){ $this->fail('Escriba el nombre del ingrediente (máx. 100 caracteres)'); }
		if( preg_match('/[<>]/', $name) || preg_match('/[\x{10000}-\x{10FFFF}]/u', $name) ){ $this->fail('El nombre contiene caracteres no permitidos'); }
		if( !isset(Ingredients_mdl::$units[$unit]) ){ $this->fail('Unidad inválida'); }

		$alert = trim((string) $this->input->post('alert_threshold')) === '' ? 0.0 : $this->num($this->input->post('alert_threshold'), TRUE);
		if( $alert === NULL ){ $this->fail('El nivel de alerta es inválido'); }

		$cost = NULL;
		if( trim((string) $this->input->post('cost_unit')) !== '' ){
			$cost = $this->num($this->input->post('cost_unit'), TRUE);
			if( $cost === NULL ){ $this->fail('El costo es inválido'); }
			$cost = (float) str_replace(',', '.', trim((string) $this->input->post('cost_unit')));
		}

		$pack_name = trim((string) $this->input->post('pack_name'));
		$pack_size = NULL;
		if( trim((string) $this->input->post('pack_size')) !== '' || $pack_name !== '' ){
			$pack_size = $this->num($this->input->post('pack_size'));
			if( $pack_size === NULL || $pack_name === '' || mb_strlen($pack_name, 'UTF-8') > 60 || preg_match('/[<>]/', $pack_name) ){
				$this->fail('Presentación inválida: indique nombre y tamaño en unidad base');
			}
		}
		else{
			$pack_name = NULL;
		}

		if( $this->ingredients_mdl->nameTaken($name, $id) ){ $this->fail('Ya existe un ingrediente con ese nombre'); }

		// Prepared ingredient (sub-recipe): yield per batch + raw components consumed per batch.
		$prepared = (string) $this->input->post('prepared') === '1';
		$yield = NULL;
		$comps = array();

		if( $prepared ){
			$yield = $this->num($this->input->post('batch_yield'));
			if( $yield === NULL ){ $this->fail('Indique cuánto rinde un lote (mayor que 0)'); }

			$raw = json_decode((string) $this->input->post('components'), TRUE);
			if( !is_array($raw) || count($raw) < 1 ){ $this->fail('Agregue al menos un componente del lote'); }
			if( count($raw) > 40 ){ $this->fail('Demasiados componentes'); }

			foreach( $raw as $r ){
				$cid = (int) ( isset($r['id_ingredient']) ? $r['id_ingredient'] : 0 );
				$comp = $cid ? $this->ingredients_mdl->get($cid) : NULL;
				$q = $this->num(isset($r['quantity']) ? $r['quantity'] : '');
				if( !$comp || !$comp->active ){ $this->fail('Hay un componente inválido en el lote'); }
				if( $id && $cid === $id ){ $this->fail('Un ingrediente no puede ser componente de sí mismo'); }
				if( $comp->batch_yield !== NULL ){ $this->fail($comp->name.' es un ingrediente preparado: solo se permiten ingredientes simples como componentes'); }
				if( $q === NULL ){ $this->fail('Cantidad inválida para '.$comp->name); }
				if( isset($comps[$cid]) ){ $this->fail($comp->name.' está repetido en el lote'); }
				$comps[$cid] = $q;
			}

			// An ingredient that other prepared ingredients use as a component must stay a simple one.
			if( $id && ($users = $this->ingredients_mdl->usedAsComponentBy($id)) ){
				$this->fail('No puede convertirse en preparado: es componente de '.$users[0]->name);
			}
		}

		$data = array('name' => $name, 'unit' => $unit, 'alert_threshold' => $alert, 'cost_unit' => $cost, 'pack_name' => $pack_name, 'pack_size' => $pack_size);

		if( $id ){
			$ing = $this->ingredients_mdl->get($id);
			if( !$ing ){ $this->fail('Ingrediente inválido'); }
			$this->db->trans_begin();
			$this->db->where('id_ingredient', $id)->update('ingredients', $data);
			$this->ingredients_mdl->applyPrepared($id, $yield, $comps);
			if( $this->db->trans_status() === FALSE ){
				$this->db->trans_rollback();
				$this->fail('No se pudo guardar el ingrediente');
			}
			$this->db->trans_commit();
			$this->ok('Ingrediente actualizado', array('redirect' => base_url('ingredients')));
		}

		$stock = trim((string) $this->input->post('stock')) === '' ? 0.0 : $this->num($this->input->post('stock'), TRUE);
		if( $stock === NULL ){ $this->fail('El stock inicial es inválido'); }

		$this->db->trans_begin();
		$data['stock'] = 0;
		$this->db->insert('ingredients', $data);
		$new = (int) $this->db->insert_id();
		if( $stock > 0 ){
			$this->db->query('UPDATE ingredients SET stock = ? WHERE id_ingredient = ?', array($stock, $new));
			$this->ingredients_mdl->log($new, 'adjustment', $stock, 'Stock inicial', $this->id_admin);
		}
		if( $prepared ){ $this->ingredients_mdl->applyPrepared($new, $yield, $comps); }
		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			$this->fail('No se pudo guardar el ingrediente');
		}
		$this->db->trans_commit();

		$this->ok('Ingrediente registrado', array('redirect' => base_url('ingredients')));
	}

	public function delete($id = 0){

		$ing = $this->ingredients_mdl->get($id);
		$flash = array('class' => 'warning', 'message' => 'Ingrediente inválido');

		if( $ing ){
			$used = $this->db->query('SELECT COUNT(*) AS c FROM recipe_items r JOIN products p ON p.id_product = r.id_product WHERE r.id_ingredient = ? AND p.active = 1', array((int) $id))->row();
			if( (int) $used->c > 0 ){
				$flash = array('class' => 'error', 'message' => 'No se puede desactivar: lo usan '.$used->c.' receta(s) de platillos activos');
			}
			elseif( ($users = $this->ingredients_mdl->usedAsComponentBy($id)) ){
				$flash = array('class' => 'error', 'message' => 'No se puede desactivar: es componente de '.$users[0]->name);
			}
			else{
				$this->db->where('id_ingredient', (int) $id)->update('ingredients', array('active' => 0));
				$flash = array('class' => 'success', 'message' => 'Ingrediente desactivado');
			}
		}

		$this->session->set_userdata('view_data', array('status' => $flash));
		redirect('ingredients');
	}

	public function restore($id = 0){

		$this->db->where('id_ingredient', (int) $id)->update('ingredients', array('active' => 1));
		$this->session->set_userdata('view_data', array('status' => array('class' => 'success', 'message' => 'Ingrediente reactivado')));
		redirect('ingredients');
	}

	/** Receiving: quantity in base units or in packs (if the ingredient has a pack), optional total cost. */
	public function receive(){

		$this->requirePost();

		$ing = $this->ingredients_mdl->get($this->input->post('id_ingredient'));
		if( !$ing || !$ing->active ){ $this->fail('Ingrediente inválido'); }

		if( $ing->batch_yield !== NULL ){ $this->fail('Este ingrediente se prepara en la cocina: use "Preparar lote"'); }

		$qty = $this->num($this->input->post('quantity'));
		if( $qty === NULL ){ $this->fail('Cantidad inválida'); }

		$mode = (string) $this->input->post('mode');
		if( $mode === 'pack' ){
			if( !$ing->pack_size ){ $this->fail('Este ingrediente no tiene presentación definida'); }
			$qty = round($qty * (float) $ing->pack_size, 3);
		}

		$total = NULL;
		if( trim((string) $this->input->post('total_cost')) !== '' ){
			$t = str_replace(',', '.', trim((string) $this->input->post('total_cost')));
			if( !is_numeric($t) || (float) $t < 0 || (float) $t > 99999999 ){ $this->fail('El costo total es inválido'); }
			$total = (float) $t;
		}

		if( !$this->ingredients_mdl->receive($ing, $qty, $total, $this->input->post('reason'), $this->id_admin) ){
			$this->fail('No se pudo registrar la entrada');
		}

		$this->ok('Entrada registrada: +'.rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.').' '.$ing->unit.' de '.$ing->name);
	}

	/** Waste (removes stock, reason required) or physical count (sets stock). */
	public function adjust(){

		$this->requirePost();

		$ing = $this->ingredients_mdl->get($this->input->post('id_ingredient'));
		if( !$ing || !$ing->active ){ $this->fail('Ingrediente inválido'); }

		$kind   = (string) $this->input->post('kind');
		$reason = trim((string) $this->input->post('reason'));
		if( mb_strlen($reason, 'UTF-8') > 255 ){ $this->fail('El motivo es demasiado largo'); }

		if( $kind === 'waste' ){
			$qty = $this->num($this->input->post('quantity'));
			if( $qty === NULL ){ $this->fail('Cantidad inválida'); }
			if( $reason === '' ){ $this->fail('Indique el motivo de la merma'); }
			if( !$this->ingredients_mdl->move($ing->id_ingredient, 'waste', -$qty, $reason, $this->id_admin) ){
				$this->fail('No hay suficiente existencia para descontar esa cantidad');
			}
			$this->ok('Merma registrada: -'.rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.').' '.$ing->unit);
		}

		if( $kind === 'count' ){
			$counted = $this->num($this->input->post('quantity'), TRUE);
			if( $counted === NULL ){ $this->fail('Cantidad inválida'); }
			$delta = $this->ingredients_mdl->count($ing->id_ingredient, $counted, $reason !== '' ? $reason : 'Conteo físico', $this->id_admin);
			if( $delta === FALSE ){ $this->fail('No se pudo registrar el conteo'); }
			$this->ok('Conteo guardado (diferencia: '.($delta > 0 ? '+' : '').rtrim(rtrim(number_format($delta, 3, '.', ''), '0'), '.').' '.$ing->unit.')');
		}

		$this->fail('Acción inválida');
	}

	public function movements($id = 0){

		$ing = $this->ingredients_mdl->get($id);
		if( !$ing ){ redirect('ingredients'); }

		$this->content_data = array('ingredient' => $ing, 'movements' => $this->ingredients_mdl->movements($id, 200));
		$this->renderPanel('ingredient_movements');
	}

	// ---------- recipes ----------

	public function recipe($id_product = 0){

		$p = $this->db->where('id_product', (int) $id_product)->where('active', 1)->get('products')->row();
		if( !$p ){
			$this->session->set_userdata('view_data', array('status' => array('class' => 'error', 'message' => 'Platillo inválido')));
			redirect('services');
		}

		$recipe = $this->ingredients_mdl->recipe($id_product);
		list($cost, $missing) = $this->ingredients_mdl->portionCost($recipe);

		$this->content_data = array(
			'product'     => $p,
			'recipe'      => $recipe,
			'cost'        => $cost,
			'cost_missing' => $missing,
			'ingredients' => $this->ingredients_mdl->allActive(),
			'mods_all'    => $this->modifiers_mdl->all(),
			'mods_on'     => $this->modifiers_mdl->attachedIds($p->id_product),
			'tk'          => $this->token(),
			'rate'        => $this->db->query('SELECT sell_price FROM dollar_exchange LIMIT 1')->row(),
		);

		$this->renderPanel('form_recipe');
	}

	/** Replaces the recipe of a product. items = JSON [{id_ingredient, quantity}]; an empty list removes the recipe. */
	public function save_recipe(){

		$this->requirePost();

		$p = $this->db->where('id_product', (int) $this->input->post('id_product'))->where('active', 1)->get('products')->row();
		if( !$p ){ $this->fail('Platillo inválido'); }

		$raw = json_decode((string) $this->input->post('items'), TRUE);
		if( !is_array($raw) || count($raw) > 40 ){ $this->fail('Receta inválida'); }

		$rows = array();
		foreach( $raw as $r ){
			$ing = $this->ingredients_mdl->get(isset($r['id_ingredient']) ? $r['id_ingredient'] : 0);
			$qty = $this->num(isset($r['quantity']) ? $r['quantity'] : '');
			if( !$ing || !$ing->active ){ $this->fail('Hay un ingrediente inválido en la receta'); }
			if( $qty === NULL ){ $this->fail('Cantidad inválida para '.$ing->name); }
			if( isset($rows[(int) $ing->id_ingredient]) ){ $this->fail($ing->name.' está repetido en la receta'); }
			$rows[(int) $ing->id_ingredient] = $qty;
		}

		if( !$this->ingredients_mdl->saveRecipe($p->id_product, $rows) ){ $this->fail('No se pudo guardar la receta'); }

		$this->ok(count($rows) ? 'Receta guardada' : 'Receta eliminada');
	}
}
