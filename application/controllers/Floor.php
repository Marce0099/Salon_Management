<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Floor plan: waiter view (tables + states), admin editor (areas, drag/resize tables) and tab open/close.
 * All AJAX answers are JSON {type, message, ...}; POSTs carry the session token `tk` (not rotated, many calls per page).
 */
class Floor extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('floor_mdl');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	private function requireAdmin(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}
	}

	private function requirePost(){

		if( !formSubmited() ){
			$this->fail('Sesión inválida, recargue la página e intente de nuevo');
		}
	}

	private function pageData(){

		$tk = $this->session->userdata('tk');
		if( !$tk ){
			$tk = generateToken();
			$this->session->set_userdata('tk', $tk);
		}

		$areas = $this->floor_mdl->areas();

		return array(
			'tk'        => $tk,
			'areas'     => $areas,
			'is_admin'  => $this->is_admin(),
			'employees' => $this->db->select('id_employee, name')->where('active', 1)->order_by('name')->get('employees')->result(),
		);
	}

	/* ---------- waiter view ---------- */

	public function index(){

		$this->content_data = array_merge($this->content_data, $this->pageData());
		$this->renderPanel('floor');
	}

	public function state($id_area = 0){

		if( !$this->floor_mdl->area($id_area) ){
			$this->fail('Área no encontrada');
		}

		$this->json(array('type' => 'success', 'tables' => $this->floor_mdl->tablesWithState($id_area), 'now' => date('Y-m-d H:i:s')));
	}

	public function open_tab(){

		$this->requirePost();

		$id_table = (int) $this->input->post('id_table');
		$guests   = (int) $this->input->post('guests');
		$id_emp   = $this->input->post('id_employee');

		if( !$this->floor_mdl->table($id_table) ){
			$this->fail('Mesa no encontrada');
		}
		if( $guests < 1 || $guests > 99 ){
			$this->fail('Número de comensales inválido');
		}
		if( !$id_emp ){
			$this->fail('Debe seleccionar un mesero');
		}
		if( !$this->db->where('id_employee', (int) $id_emp)->where('active', 1)->count_all_results('employees') ){
			$this->fail('Mesero inválido');
		}

		$id_tab = $this->floor_mdl->openTab($id_table, $id_emp, $guests, $this->id_admin);

		if( !$id_tab ){
			$this->fail('La mesa ya está ocupada');
		}

		$this->json(array('type' => 'success', 'message' => 'Mesa abierta', 'id_tab' => $id_tab));
	}

	public function close_tab(){

		$this->requirePost();

		$tab = $this->floor_mdl->tab($this->input->post('id_tab'));

		if( !$tab || $tab->status !== 'open' ){
			$this->fail('La cuenta ya no está abierta');
		}

		// Stage 5+ will refuse to release a table that already has consumption; for now only empty tabs exist.
		$this->floor_mdl->closeTab($tab->id_tab, 'void');

		$this->json(array('type' => 'success', 'message' => 'Mesa liberada'));
	}

	/* ---------- admin editor ---------- */

	public function editor(){

		$this->requireAdmin();
		$this->content_data = array_merge($this->content_data, $this->pageData());
		$this->renderPanel('floor_editor');
	}

	public function area_save(){

		$this->requireAdmin();
		$this->requirePost();

		$name = trim((string) $this->input->post('name'));
		$id   = (int) $this->input->post('id_area');

		if( $name === '' || mb_strlen($name, 'UTF-8') > 100 ){
			$this->fail('Nombre de área inválido');
		}
		if( $id && !$this->floor_mdl->area($id) ){
			$this->fail('Área no encontrada');
		}

		$id = $this->floor_mdl->saveArea($id, $name);
		$this->json(array('type' => 'success', 'message' => 'Área guardada', 'id_area' => $id, 'name' => $name));
	}

	public function area_delete(){

		$this->requireAdmin();
		$this->requirePost();

		$id = (int) $this->input->post('id_area');

		if( !$this->floor_mdl->area($id) ){
			$this->fail('Área no encontrada');
		}
		if( count($this->floor_mdl->areas()) <= 1 ){
			$this->fail('Debe existir al menos un área');
		}
		if( $this->floor_mdl->areaHasOpenTabs($id) ){
			$this->fail('El área tiene mesas ocupadas');
		}

		$this->floor_mdl->deleteArea($id);
		$this->json(array('type' => 'success', 'message' => 'Área eliminada'));
	}

	private function clamp($v, $min, $max){

		return max($min, min($max, (float) $v));
	}

	/** Create (no id_table) or update a table: label/seats/shape and/or geometry. */
	public function table_save(){

		$this->requireAdmin();
		$this->requirePost();

		$id      = (int) $this->input->post('id_table');
		$current = $id ? $this->floor_mdl->table($id) : NULL;

		if( $id && !$current ){
			$this->fail('Mesa no encontrada');
		}

		$id_area = $current ? (int) $current->id_area : (int) $this->input->post('id_area');
		if( !$this->floor_mdl->area($id_area) ){
			$this->fail('Área no encontrada');
		}

		$data = array();

		if( $this->input->post('label') !== NULL || !$id ){
			$label = trim((string) $this->input->post('label'));
			if( $label === '' || mb_strlen($label, 'UTF-8') > 30 ){
				$this->fail('Etiqueta de mesa inválida');
			}
			if( $this->floor_mdl->labelExists($id_area, $label, $id) ){
				$this->fail('Ya existe una mesa con esa etiqueta en el área');
			}
			$data['label'] = $label;
		}

		if( $this->input->post('seats') !== NULL ){
			$seats = (int) $this->input->post('seats');
			if( $seats < 1 || $seats > 50 ){
				$this->fail('Número de asientos inválido');
			}
			$data['seats'] = $seats;
		}

		if( $this->input->post('shape') !== NULL ){
			$shape = $this->input->post('shape');
			if( !in_array($shape, array('square', 'round'), TRUE) ){
				$this->fail('Forma inválida');
			}
			$data['shape'] = $shape;
		}

		foreach( array('x' => array(0, 100), 'y' => array(0, 100), 'w' => array(4, 100), 'h' => array(4, 100)) as $k => $range ){
			if( $this->input->post($k) !== NULL && is_numeric($this->input->post($k)) ){
				$data[$k] = round($this->clamp($this->input->post($k), $range[0], $range[1]), 3);
			}
		}

		if( !$id ){
			$data['id_area'] = $id_area;
			$data += array('x' => 5, 'y' => 5, 'w' => 12, 'h' => 12);
			if( !isset($data['seats']) ){ $data['seats'] = 4; }
		}

		$id = $this->floor_mdl->saveTable($id, $data);

		$this->json(array('type' => 'success', 'message' => 'Mesa guardada', 'id_table' => $id));
	}

	public function table_delete(){

		$this->requireAdmin();
		$this->requirePost();

		$id = (int) $this->input->post('id_table');

		if( !$this->floor_mdl->table($id) ){
			$this->fail('Mesa no encontrada');
		}
		if( $this->floor_mdl->tabHasOpenTable($id) ){
			$this->fail('La mesa está ocupada');
		}

		$this->floor_mdl->deleteTable($id);
		$this->json(array('type' => 'success', 'message' => 'Mesa eliminada'));
	}
}
