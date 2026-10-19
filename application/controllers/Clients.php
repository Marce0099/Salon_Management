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
		$this->form_validation->set_rules('name_client','','required|min_length[3]|max_length[50]|regex_match[/^[\p{L} ]*$/u]');
		$this->form_validation->set_rules('surname_client','','required|min_length[3]|max_length[50]|regex_match[/^[\p{L} ]*$/u]');

		if(!empty($_POST['phone_client']))
		$this->form_validation->set_rules('phone_client','','regex_match[/^[0-9 -]{3,20}$/]');

		if(!empty($_POST['email_client']))
		$this->form_validation->set_rules('email_client','','min_length[3]|max_length[100]|valid_email');
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = "select *, CONCAT(name_client, ' ', surname_client) as full_name_client from clients";
		$search['order_by'] = array( 'registered_client' => 'DESC'  );
		$search['return_as'] = 'clients';
		$search['limit'] = 100;
        $search['fields'] = array(
			'is_vip_client' => array( 'column' => 'is_vip_client', 'type' => 'numeric' ),
			'id_card_client' => array( 'column' => 'id_card_client', 'type' => 'string' ),
			'phone_client' => array( 'column' => 'phone_client', 'type' => 'string' ),
			'name_client' => array( 'column' => "CONCAT(name_client, ' ', surname_client)", 'type' => 'string' )
		);

		$this->easysearch->setInitialWhere('active=1');
		$data = $this->easysearch->search($search);
		
		$this->content_data = array_merge($data,$view_data);
		$this->content_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $this->content_data['tk']);

		$this->renderPanel('clients');
	}

	private function importColumns(){

		return array(
			array('key' => 'id_card_client', 'label' => 'Cédula',   'required' => FALSE, 'text' => TRUE, 'width' => 20, 'note' => 'Letras y números sin espacios ni guiones, máximo 50'),
			array('key' => 'name_client',    'label' => 'Nombre',   'required' => TRUE,  'text' => TRUE, 'width' => 22, 'note' => 'Solo letras y espacios, entre 3 y 50 caracteres'),
			array('key' => 'surname_client', 'label' => 'Apellido', 'required' => TRUE,  'text' => TRUE, 'width' => 22, 'note' => 'Solo letras y espacios, entre 3 y 50 caracteres'),
			array('key' => 'phone_client',   'label' => 'Teléfono', 'required' => FALSE, 'text' => TRUE, 'width' => 18, 'note' => 'Números, espacios o -; entre 3 y 20 caracteres'),
			array('key' => 'email_client',   'label' => 'Email',    'required' => FALSE, 'text' => TRUE, 'width' => 30, 'note' => 'Correo válido, máximo 100 caracteres'),
			array('key' => 'birth_client',   'label' => 'Fecha de nacimiento (dd/mm/aaaa)', 'required' => TRUE, 'text' => TRUE, 'date' => TRUE, 'width' => 32, 'note' => 'Formato dd/mm/aaaa, no puede ser futura'),
		);
	}

	public function template(){

		$this->load->library('excel_import');
		$this->excel_import->sendTemplate('plantilla_clientes.xlsx', $this->importColumns(), array(
			'Un cliente se considera repetido cuando coincide la Cédula; si no tiene cédula, cuando coinciden Nombre, Apellido y Fecha de nacimiento.',
		));
	}

	public function import(){

		$this->load->library('excel_import');

		if( !formSubmited() ){
			echo json_encode(array('type' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo'));
			return;
		}

		$db = $this->db;

		$nameKey = function($name, $surname, $birth){ return 'nm:'.mb_strtolower(trim($name).'|'.trim($surname).'|'.$birth, 'UTF-8'); };
		$key = function($d) use ($nameKey){
			return $d['id_card_client'] !== '' ? 'id:'.$d['id_card_client'] : $nameKey($d['name_client'], $d['surname_client'], $d['birth_client']);
		};

		$response = $this->excel_import->run(array(
			'columns' => $this->importColumns(),
			'db' => $db,
			'validate' => function(&$d){

				$d['id_card_client'] = strtolower($d['id_card_client']);

				if( $d['id_card_client'] !== '' && ( strlen($d['id_card_client']) > 50 || !preg_match('/^[a-z0-9]+$/', $d['id_card_client']) ) ) return 'Cédula inválida (solo letras y números, máximo 50)';
				if( !preg_match('/^[\p{L} ]{3,50}$/u', $d['name_client']) ) return 'Nombre inválido (solo letras y espacios, entre 3 y 50 caracteres)';
				if( !preg_match('/^[\p{L} ]{3,50}$/u', $d['surname_client']) ) return 'Apellido inválido (solo letras y espacios, entre 3 y 50 caracteres)';
				if( $d['phone_client'] !== '' && !preg_match('/^[0-9 -]{3,20}$/', $d['phone_client']) ) return 'Teléfono inválido';
				if( $d['email_client'] !== '' && ( strlen($d['email_client']) > 100 || !filter_var($d['email_client'], FILTER_VALIDATE_EMAIL) ) ) return 'Email inválido';

				if( $d['birth_client'] === '' ) return 'La fecha de nacimiento es obligatoria';
				$birth = Excel_import::parseDate($d['birth_client']);
				if( $birth === NULL ) return 'Fecha de nacimiento inválida (use dd/mm/aaaa)';
				if( $birth > date('Y-m-d') || $birth < '1900-01-01' ) return 'Fecha de nacimiento fuera de rango';
				$d['birth_client'] = $birth;

				return NULL;
			},
			'key' => $key,
			'existing' => function() use ($db, $nameKey){

				$keys = array();
				foreach( $db->select('id_card_client, name_client, surname_client, birth_client')->get('clients')->result_array() as $r ){
					if( $r['id_card_client'] !== NULL && $r['id_card_client'] !== '' ){
						$keys[] = 'id:'.strtolower($r['id_card_client']);
					}
					$keys[] = $nameKey((string) $r['name_client'], (string) $r['surname_client'], $r['birth_client']);
				}
				return $keys;
			},
			'insert' => function($rows) use ($db){

				$batch = array();
				foreach( $rows as $d ){
					$batch[] = array(
						'id_card_client' => $d['id_card_client'] !== '' ? $d['id_card_client'] : NULL,
						'name_client'    => $d['name_client'],
						'surname_client' => $d['surname_client'],
						'phone_client'   => $d['phone_client'] !== '' ? $d['phone_client'] : NULL,
						'email_client'   => $d['email_client'] !== '' ? $d['email_client'] : NULL,
						'birth_client'   => $d['birth_client'],
					);
				}

				if( $db->insert_batch('clients', $batch) !== count($batch) ){
					throw new Exception('insert_batch failed');
				}
			},
		));

		$new_tk = generateToken();
		$this->session->set_userdata('tk', $new_tk);
		$response['tk'] = $new_tk;

		echo json_encode($response);
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