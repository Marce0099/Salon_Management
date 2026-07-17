<?php if( !defined('BASEPATH') ){ exit(); }

class Bank_account extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('bank_account_mdl');
	}

	public function index(){
		$view_data = load_data($this->session);
		$this->content_data['accounts'] = $this->bank_account_mdl->getAccounts();
		$this->content_data['privilege'] = $this->is_admin();
		$this->content_data = array_merge($this->content_data, $view_data);
		$this->renderPanel('form_bank_account');
	}

	private function setFormRules(){

		$this->form_validation->set_rules('token_account','', 'required|numeric|max_length[20]');
		$this->form_validation->set_rules('name_account','',[
			'required',
			'max_length[60]',
			'regex_match[/^[\p{L}\s\+\&\.\-]+$/u]'
		]);
		$this->form_validation->set_rules('type','', 'required|in_list[cordobas,dolares]');
		$this->form_validation->set_rules('buy_rate','',[
			'required',
			'regex_match[/^\d+(\.\d{1,2})?$/]'
		]);
		$this->form_validation->set_rules('sell_rate','',[
			'required',
			'regex_match[/^\d+(\.\d{1,2})?$/]'
		]);
	}

	private function setEditFormRules(){

		$this->form_validation->set_rules('token_account','', 'required');
		$this->form_validation->set_rules('buy_rate','',[
			'required',
			'regex_match[/^\d+(\.\d{1,2})?$/]'
		]);
		$this->form_validation->set_rules('sell_rate','',[
			'required',
			'regex_match[/^\d+(\.\d{1,2})?$/]'
		]);
	}

	public function save(){

		$this->setFormRules();

		try {
			if( formPosted() && $this->form_validation->run() ){

				$data = $this->input->post();
				$this->bank_account_mdl->save($data);

				$view_data['status'] = array( 'class' => 'success', 'message' => 'Cuenta bancaria agregada' );
				$this->session->set_userdata( 'view_data', $view_data );
				

				echo json_encode($view_data);
			}
			else{
	
				throw new \Exception('Datos inválidos');
			}
		} catch (\Throwable $th) {
			$message = $th->getMessage() == 'Datos inválidos' ? 'Datos inválidos' : 'Error al guardar cuenta bancaria'; 

			$view_data['status'] = array( 'class' => 'error', 'message' => $message );
			echo json_encode($view_data);
		}

	}

	public function update(){

		$this->setEditFormRules();

		try {
			if( formPosted() && $this->form_validation->run() ){

				$account = $this->bank_account_mdl->getAccount( $this->input->post('token_account') );
				if( !$account ){
					throw new \Exception('No existe cuenta');
				}

				$data = [
					'buy_rate'  => $this->input->post('buy_rate'),
					'sell_rate' => $this->input->post('sell_rate'),
				];
				$this->bank_account_mdl->update($account->id_account, $data);

				$view_data['status'] = array( 'class' => 'success', 'message' => 'Cuenta bancaria actualizada' );
				$this->session->set_userdata( 'view_data', $view_data );

				echo json_encode($view_data);
			}
			else{

				throw new \Exception('Datos inválidos');
			}
		} catch (\Throwable $th) {
			$message = $th->getMessage() == 'Datos inválidos' ? 'Datos inválidos' : 'Error al actualizar cuenta bancaria';

			$view_data['status'] = array( 'class' => 'error', 'message' => $message );
			echo json_encode($view_data);
		}
	}

	public function dishabilitate($token_account){
		try {

			$account = $this->bank_account_mdl->getAccount( $token_account );
			if( !$account ){
				throw new Exception('No existe cuenta');
			}

			$this->bank_account_mdl->dishabilitate($account->id_account);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Cuenta bancaria eliminada' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('bank_account');

		} catch (\Throwable $e) {
			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('bank_account');
		}
	}
}