<?php if( !defined('BASEPATH') ){ exit(); }

/** Storage places for the shift inventory report (super only). */
class Storages extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('storages_mdl');
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

	private function cleanText($v, $max, $required = TRUE){

		$v = trim(preg_replace('/\s+/', ' ', (string) $v));
		if( $v === '' ){ return $required ? NULL : ''; }
		if( mb_strlen($v, 'UTF-8') > $max || preg_match('/[<>]/', $v) || preg_match('/[\x{10000}-\x{10FFFF}]/u', $v) ){ return NULL; }

		return $v;
	}

	public function index(){

		$show_inactive = (int) $this->input->get('inactive') === 1;

		$this->footer_data = load_data($this->session);
		$this->content_data = array('storages' => $this->storages_mdl->all($show_inactive), 'show_inactive' => $show_inactive);
		$this->renderPanel('storages');
	}

	public function register(){

		$this->form(0);
	}

	public function edit($id = 0){

		$this->form((int) $id);
	}

	private function form($id){

		$view = array('tk' => $this->token());

		if( $id ){
			$s = $this->storages_mdl->get($id);
			if( !$s ){
				$this->session->set_userdata('view_data', array('status' => array('class' => 'error', 'message' => 'Almacén inválido')));
				redirect('storages');
			}
			$view['storage'] = $s;
		}

		$this->content_data = $view;
		$this->renderPanel('form_storage');
	}

	public function save($id = 0){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }
		$id = (int) $id;

		$name = $this->cleanText($this->input->post('name'), 60);
		if( $name === NULL ){ $this->fail('Escriba el nombre (máx. 60 caracteres, sin símbolos < >)'); }

		$desc = $this->cleanText($this->input->post('description'), 150, FALSE);
		if( $desc === NULL ){ $this->fail('La descripción es inválida (máx. 150 caracteres, sin símbolos < >)'); }

		$sort = (string) $this->input->post('sort_order');
		if( $sort === '' ){ $sort = '0'; }
		if( !preg_match('/^\d{1,4}$/', $sort) ){ $this->fail('El orden debe ser un número entre 0 y 9999'); }

		if( $id && !$this->storages_mdl->get($id) ){ $this->fail('Almacén inválido'); }
		if( $this->storages_mdl->nameTaken($name, $id) ){ $this->fail('Ya existe un almacén con ese nombre'); }

		$new = $this->storages_mdl->save($id, $name, $desc === '' ? NULL : $desc, $sort);
		if( !$new ){ $this->fail('No se pudo guardar el almacén'); }

		$this->ok($id ? 'Almacén actualizado' : 'Almacén creado', array('redirect' => base_url('storages')));
	}

	public function delete($id = 0){

		$s = $this->storages_mdl->get($id);
		if( $s ){ $this->storages_mdl->setActive($id, 0); }

		$this->session->set_userdata('view_data', array('status' => $s ? array('class' => 'success', 'message' => 'Almacén desactivado (los reportes anteriores lo conservan)') : array('class' => 'warning', 'message' => 'Almacén inválido')));
		redirect('storages');
	}

	public function restore($id = 0){

		if( $this->storages_mdl->get($id) ){ $this->storages_mdl->setActive($id, 1); }

		$this->session->set_userdata('view_data', array('status' => array('class' => 'success', 'message' => 'Almacén reactivado')));
		redirect('storages');
	}
}
