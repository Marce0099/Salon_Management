<?php if( !defined('BASEPATH') ){ exit(); }

class Dollar_exchange extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('dollar_exchange_mdl');
	}

	public function index(){

		$this->form();
	}

	private function form(){

		$this->content_data['exchange'] = $this->dollar_exchange_mdl->getExchange();
		$this->renderPanel('form_dollar_exchange');
	}

	private function setFormRules(){

		$this->form_validation->set_rules('buy_price','required|numeric');
		$this->form_validation->set_rules('sell_price','required|numeric');
	}

	public function save(){

		$this->setFormRules();

		if( formPosted() && $this->form_validation->run() ){

			$data = $this->input->post();
			$this->dollar_exchange_mdl->save($data);

			$view_data['message'] = 'Tarífa de dollar actualizada';
			$view_data['type'] = 'success';
			echo json_encode($view_data);
		}
		else{

			$data['message'] = 'Datos invalidos';
			$data['type'] = 'error';
			echo json_encode($data);
		}
	}
}