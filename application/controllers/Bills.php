<?php if( !defined('BASEPATH') ){ exit(); }

/** Floating bills of an open tab: view, new bill, rename, move items (split), merge, equal split. JSON {type,message}. */
class Bills extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('bills_mdl');
		$this->load->model('kitchen_mdl');
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

	private function openBill($id_bill){

		$bill = $this->bills_mdl->get($id_bill);
		if( !$bill ){
			$this->fail('Cuenta no encontrada');
		}
		$tab = $this->kitchen_mdl->tab($bill->id_tab);
		if( !$tab || $tab->status !== 'open' ){
			$this->fail('La mesa ya no está abierta');
		}
		return $bill;
	}

	public function tab($id_tab = 0){

		$tab = $this->kitchen_mdl->tab($id_tab);
		if( !$tab || $tab->status !== 'open' ){
			redirect('floor');
		}

		$tk = $this->session->userdata('tk');
		if( !$tk ){
			$tk = generateToken();
			$this->session->set_userdata('tk', $tk);
		}

		$rates = $this->bills_mdl->rates();
		$all   = $this->bills_mdl->forTab($id_tab);
		$tops  = array();
		$kids  = array();

		foreach( $all as $b ){
			$b->items  = $b->parent_bill ? array() : $this->bills_mdl->items($b->id_bill);
			$b->totals = $this->bills_mdl->totals($b, $rates);
			if( $b->parent_bill ){ $kids[(int) $b->parent_bill][] = $b; } else { $tops[] = $b; }
		}

		$this->content_data = array_merge($this->content_data, array(
			'tk' => $tk, 'tab' => $tab, 'bills' => $tops, 'kids' => $kids, 'rates' => $rates, 'rate' => $this->bills_mdl->sellRate(),
		));

		$this->renderPanel('bills');
	}

	public function create(){

		$this->requirePost();
		$tab = $this->kitchen_mdl->tab($this->input->post('id_tab'));
		if( !$tab || $tab->status !== 'open' ){
			$this->fail('La mesa ya no está abierta');
		}

		$label = trim((string) $this->input->post('label'));
		if( mb_strlen($label, 'UTF-8') > 60 ){
			$this->fail('Nombre demasiado largo');
		}

		$this->bills_mdl->create($tab->id_tab, $label);
		$this->json(array('type' => 'success', 'message' => 'Cuenta creada'));
	}

	public function rename(){

		$this->requirePost();
		$bill  = $this->openBill($this->input->post('id_bill'));
		$label = trim((string) $this->input->post('label'));

		if( $label === '' || mb_strlen($label, 'UTF-8') > 60 ){
			$this->fail('Nombre inválido');
		}

		$this->bills_mdl->rename($bill->id_bill, $label);
		$this->json(array('type' => 'success', 'message' => 'Cuenta renombrada'));
	}

	public function move_item(){

		$this->requirePost();
		$item = $this->kitchen_mdl->item($this->input->post('id_order_item'));
		if( !$item ){
			$this->fail('Producto no encontrado');
		}
		$this->openBill($item->id_bill);

		list($ok, $msg) = $this->bills_mdl->moveItem($item->id_order_item, (int) $this->input->post('id_target'), (int) $this->input->post('quantity'));
		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $msg));
	}

	public function merge(){

		$this->requirePost();
		$this->openBill($this->input->post('id_from'));

		list($ok, $msg) = $this->bills_mdl->merge($this->input->post('id_from'), $this->input->post('id_into'));
		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $msg));
	}

	public function split_equal(){

		$this->requirePost();
		$this->openBill($this->input->post('id_bill'));

		$parts = (int) $this->input->post('parts');
		if( $parts < 2 || $parts > 20 ){
			$this->fail('Indique entre 2 y 20 partes');
		}

		list($ok, $msg) = $this->bills_mdl->splitEqual($this->input->post('id_bill'), $parts);
		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $msg));
	}

	public function undo_split(){

		$this->requirePost();
		$this->openBill($this->input->post('id_bill'));

		list($ok, $msg) = $this->bills_mdl->undoSplit($this->input->post('id_bill'));
		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $msg));
	}
}
