<?php if( !defined('BASEPATH') ){ exit(); }

/** Modifier catalog (admin): "Sin queso", "Término medio"… Text for the kitchen + optional ingredient amounts removed from the portion's stock. */
class Modifiers extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('modifiers_mdl');
		$this->load->model('ingredients_mdl');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	private function ok($message, $extra = array()){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		$this->json(array_merge(array('type' => 'success', 'message' => $message, 'tk' => $tk), $extra));
	}

	private function token(){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		return $tk;
	}

	/** Plain text that is safe to store and print: no markup, no 4-byte characters. */
	private function cleanText($v, $max){

		$v = trim(preg_replace('/\s+/', ' ', (string) $v));
		if( $v === '' || mb_strlen($v, 'UTF-8') > $max || preg_match('/[<>]/', $v) || preg_match('/[\x{10000}-\x{10FFFF}]/u', $v) ){ return NULL; }

		return $v;
	}

	public function index(){

		$show_inactive = (int) $this->input->get('inactive') === 1;

		$this->footer_data = load_data($this->session);
		$this->content_data = array(
			'modifiers'     => $this->modifiers_mdl->all($show_inactive),
			'summaries'     => $this->modifiers_mdl->summaries(),
			'show_inactive' => $show_inactive,
		);

		$this->renderPanel('modifiers');
	}

	public function register(){

		$this->form(0);
	}

	public function edit($id = 0){

		$this->form((int) $id);
	}

	private function form($id){

		$view = array('tk' => $this->token(), 'ingredients' => $this->ingredients_mdl->allActive(), 'rows' => array());

		if( $id ){
			$m = $this->modifiers_mdl->get($id);
			if( !$m ){
				$this->session->set_userdata('view_data', array('status' => array('class' => 'error', 'message' => 'Modificador inválido')));
				redirect('modifiers');
			}
			$view['modifier'] = $m;
			$view['rows'] = $this->modifiers_mdl->ingredientsOf($id);
		}

		$this->content_data = $view;
		$this->renderPanel('form_modifier');
	}

	/** Create / edit (POST). items = JSON [{id_ingredient, quantity}] (blank quantity = remove the dish's whole amount of that ingredient). */
	public function save($id = 0){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }
		$id = (int) $id;

		$name = $this->cleanText($this->input->post('name'), 60);
		if( $name === NULL ){ $this->fail('Escriba el nombre (máx. 60 caracteres, sin símbolos < >)'); }

		$text = trim((string) $this->input->post('kitchen_text')) === '' ? mb_strtoupper($name, 'UTF-8') : $this->cleanText($this->input->post('kitchen_text'), 60);
		if( $text === NULL ){ $this->fail('El texto para cocina es inválido (máx. 60 caracteres, sin símbolos < >)'); }

		if( $id && !$this->modifiers_mdl->get($id) ){ $this->fail('Modificador inválido'); }
		if( $this->modifiers_mdl->nameTaken($name, $id) ){ $this->fail('Ya existe un modificador con ese nombre'); }

		$raw = json_decode((string) $this->input->post('items'), TRUE);
		if( !is_array($raw) || count($raw) > 20 ){ $this->fail('Lista de ingredientes inválida'); }

		$rows = array();
		foreach( $raw as $r ){

			$ing = $this->ingredients_mdl->get(isset($r['id_ingredient']) ? $r['id_ingredient'] : 0);
			if( !$ing || !$ing->active ){ $this->fail('Hay un ingrediente inválido'); }
			if( array_key_exists((int) $ing->id_ingredient, $rows) ){ $this->fail($ing->name.' está repetido'); }

			$q = isset($r['quantity']) ? trim((string) $r['quantity']) : '';
			if( $q === '' ){
				$rows[(int) $ing->id_ingredient] = NULL;
				continue;
			}

			$q = str_replace(',', '.', $q);
			if( !is_numeric($q) || (float) $q <= 0 || (float) $q > 99999999 ){ $this->fail('Cantidad inválida para '.$ing->name); }
			$rows[(int) $ing->id_ingredient] = round((float) $q, 3);
		}

		$new = $this->modifiers_mdl->save($id, $name, $text, $rows);
		if( !$new ){ $this->fail('No se pudo guardar el modificador'); }

		$this->ok($id ? 'Modificador actualizado' : 'Modificador creado', array('redirect' => base_url('modifiers')));
	}

	public function delete($id = 0){

		$m = $this->modifiers_mdl->get($id);
		if( $m ){ $this->modifiers_mdl->setActive($id, 0); }

		$this->session->set_userdata('view_data', array('status' => $m ? array('class' => 'success', 'message' => 'Modificador desactivado (los pedidos ya enviados conservan su texto)') : array('class' => 'warning', 'message' => 'Modificador inválido')));
		redirect('modifiers');
	}

	public function restore($id = 0){

		if( $this->modifiers_mdl->get($id) ){ $this->modifiers_mdl->setActive($id, 1); }

		$this->session->set_userdata('view_data', array('status' => array('class' => 'success', 'message' => 'Modificador reactivado')));
		redirect('modifiers');
	}

	/** Replaces the modifiers attached to a dish (ids = JSON list of active modifier ids). */
	public function attach(){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }

		$p = $this->db->where('id_product', (int) $this->input->post('id_product'))->where('active', 1)->get('products')->row();
		if( !$p ){ $this->fail('Platillo inválido'); }

		$raw = json_decode((string) $this->input->post('ids'), TRUE);
		if( !is_array($raw) || count($raw) > 40 ){ $this->fail('Lista inválida'); }

		$ids = array();
		foreach( $raw as $v ){
			$m = ( is_scalar($v) && preg_match('/^\d+$/', (string) $v) ) ? $this->modifiers_mdl->get($v) : NULL;
			if( !$m || !$m->active ){ $this->fail('Hay un modificador inválido'); }
			$ids[(int) $m->id_modifier] = (int) $m->id_modifier;
		}

		if( !$this->modifiers_mdl->setProduct($p->id_product, array_values($ids)) ){ $this->fail('No se pudo guardar'); }

		$this->ok(count($ids) ? 'Modificadores del platillo guardados' : 'Se quitaron los modificadores del platillo');
	}
}
