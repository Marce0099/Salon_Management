<?php if( !defined('BASEPATH') ){ exit(); }

class Clients extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('clients_mdl');
	}

    function formPosted(){

		return !empty($_POST);
	}

	public function setFormRules(){
		if( preg_match("/^([0-9]{2})\/([0-9]{2})\/([0-9]{4})$/", $_POST['birth_client'], $matches) ){
			$day = (int)$matches[1];
			$month = (int)$matches[2];
			$year = (int)$matches[3];

			if (checkdate($month, $day, $year)) {
				// Convert to YYYY-MM-DD format
				$_POST['birth_client'] = "{$year}-{$month}-{$day}";
			} else {
				// Invalid date (e.g., 31/02/2024)
				$response = array('type' => 'error', 'message' => 'Fecha no válida');
				echo json_encode($response);
				exit;
			}
		}else{

			$response = array( 'type' => 'error', 'message' => 'Formato de fecha no valido' );
			echo json_encode( $response );
			exit;
		}
	   
		if (!empty($_POST['id_card_client'])) {
			$this->form_validation->set_rules('id_card_client', '', 'max_length[50]|regex_match[/^[a-zA-Z0-9]+$/]');
		}
		$this->form_validation->set_rules('name_client','','required|min_length[3]|max_length[50]|regex_match[/^[a-zA-Z ]*$/]');
		$this->form_validation->set_rules('surname_client','','required|min_length[3]|max_length[50]|regex_match[/^[a-zA-Z ]*$/]');

		if(!empty($_POST['phone_client']))
		$this->form_validation->set_rules('phone_client','','regex_match[/^[0-9 -]{3,20}$/]');

		if(!empty($_POST['email_client']))
		$this->form_validation->set_rules('email_client','','min_length[3]|max_length[100]|valid_email');
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = 'select * from clients';
		$search['order_by'] = array( 'registered_client' => 'DESC'  );
		$search['return_as'] = 'clients';
		$search['limit'] = 100;
        $search['fields'] = array(
			'is_vip_client' => array( 'column' => 'is_vip_client', 'type' => 'numeric' ),
			'id_card_client' => array( 'column' => 'id_card_client', 'type' => 'string' ),
			'phone_client' => array( 'column' => 'phone_client', 'type' => 'string' )
		);

		$this->easysearch->setInitialWhere('active=1');
		$data = $this->easysearch->search($search);
		
		$this->content_data = array_merge($data,$view_data);

		$this->renderPanel('clients');
	}

    public function form_clients( $view_data = array() ){

		$this->content_data = $view_data;
		$this->renderPanel('form_clients');
	}

	public function register(){

		if( formPosted() ){

			$this->setFormRules();

			try{ 

				if( $this->form_validation->run() ){

					$data = $this->input->post();

					$val = strtolower(trim($data['id_card_client'] ?? ''));
					$data['id_card_client'] = $val !== '' ? $val : NULL;

					$employee_code = $this->clients_mdl->save( $data );

					$response = array( 'type' => 'success', 'message' => 'Cliente registrado con el código: '.$data['id_card_client'] );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');	
				}
			}
			catch( Exception $e ){

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode( $response );
			}
		}
		else{

			$this->form_clients();
		}
	}

	public function edit($id_client = 0){

		try{

			$view_data['client'] = $this->clients_mdl->getClient($id_client);
			
			if( !$view_data['client'] ){

				throw new Exception('Cliente inválido',1);
			}

			if( formPosted() ){

				$this->setFormRules();

				if( $this->form_validation->run() ){

					$data = $this->input->post();

					$val = strtolower(trim($data['id_card_client'] ?? ''));
					$data['id_card_client'] = $val !== '' ? $val : NULL;

					$this->clients_mdl->edit( $id_client, $data );

					$response = array( 'type' => 'success', 'message' => 'Cliente editado', 'edit' => true );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');
				}

			}
			else{

				$this->form_clients($view_data);
			}
		}	
		catch( Exception $e ){

			if( $e->getCode() == 1 ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

				$this->session->set_userdata( 'view_data', $sess_data );
				redirect('clients');
			}
			else{

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode($response);
			}
		}
	}

	public function delete( $id_client = 0 ){

		try{

			$client = $this->clients_mdl->getClient( $id_client );

			if( !$client ){

				throw new Exception('Cliente inválido');
			}

			$this->clients_mdl->delete($id_client);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Cliente eliminado' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients');

		}
		catch( Exception $e ){

			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients');
		}
	}

	public function recover(){

		$view_data = load_data($this->session);
		$this->load->library('easysearch');

		$search['query'] = 'select * from clients';
		$search['order_by'] = array( 'registered_client' => 'DESC' );
		$search['return_as'] = 'clients';
		$search['limit'] = 100;
		$search['fields'] = array(
			'id_card_client' => array( 'column' => 'id_card_client', 'type' => 'string' ),
			'phone_client'   => array( 'column' => 'phone_client',   'type' => 'string' )
		);

		$this->easysearch->setInitialWhere('active=0');
		$data = $this->easysearch->search($search);
		$this->content_data = array_merge($data, $view_data);
		$this->renderPanel('clients_recover');
	}

	public function reactivate( $id_client = 0 ){

		try{

			$client = $this->clients_mdl->getInactiveClient( $id_client );

			if( !$client ){

				throw new Exception('Cliente inválido');
			}

			$this->clients_mdl->reactivate($id_client);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Cliente reactivado' );
			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients/recover');

		}
		catch( Exception $e ){

			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );
			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients/recover');
		}
	}

	public function toggleVip( $id_client = 0 ){

		try{

			$client = $this->clients_mdl->getClient( $id_client );

			if( !$client ){

				throw new Exception('Cliente inválido');
			}

			$this->clients_mdl->toggleVip($id_client);

			$new_status = $client->is_vip_client == 1 ? 'removido de VIP' : 'marcado como VIP';
			$view_data['status'] = array( 'class' => 'success', 'message' => "Cliente $new_status" );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients');

		}
		catch( Exception $e ){

			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients');
		}
	}

	//API
	public function getByIdCard(){
		if($_SERVER["REQUEST_METHOD"] !== "POST"){
			echo json_encode(["error" => "Error en solicitar la informacion"]);
			exit;
		}

		$data = $this->clients_mdl->getClientByIdentification($_POST["id_client"]);

		if(!$data){
			echo json_encode(["error" => "Cliente no registrado"]);
			exit;
		}

		echo json_encode($data);
	}

	public function searchClients(){

		$term = $this->input->post('term');

		if( !empty($term) ){
			$data['clients'] = $this->clients_mdl->searchActiveByTerm($term);
			echo json_encode($data);
			return;
		}

		$id_card_client = $this->input->post('id_card_client');
		$name_client    = $this->input->post('name_client');
		$surname_client = $this->input->post('surname_client');
		$phone_client   = $this->input->post('phone_client');
		$email_client   = $this->input->post('email_client');

		$data['clients'] = $this->clients_mdl->searchActive($id_card_client, $name_client, $surname_client, $phone_client, $email_client);
		echo json_encode($data);
	}
}