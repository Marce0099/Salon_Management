<?php if( !defined('BASEPATH') ){ exit(); }

class Providers extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select * from providers';
		$search['return_as'] = 'providers';
		$search['fields'] = array(
			'provider'       => array('column' => 'provider',       'type' => 'string'),
			'representative' => array('column' => 'representative', 'type' => 'string'),
			'phone_provider' => array('column' => 'phone_provider', 'type' => 'string'),
			'email_provider' => array('column' => 'email_provider', 'type' => 'string'),
		);
		$this->easysearch->setInitialWhere('active=1');

		$data = $this->easysearch->search($search);

		$this->content_data = $data;
		$this->content_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $this->content_data['tk']);
		$this->footer_data = load_data($this->session);

		$this->renderPanel('providers');
	}

	private function importColumns(){

		return array(
			array('key' => 'provider',       'label' => 'Empresa',       'required' => FALSE, 'text' => TRUE, 'width' => 30, 'note' => 'Texto, máximo 150 caracteres'),
			array('key' => 'representative', 'label' => 'Representante', 'required' => TRUE,  'text' => TRUE, 'width' => 30, 'note' => 'Texto, máximo 150 caracteres'),
			array('key' => 'phone_provider', 'label' => 'Teléfono',      'required' => FALSE, 'text' => TRUE, 'width' => 18, 'note' => 'Números, espacios, + o -; entre 3 y 20 caracteres'),
			array('key' => 'email_provider', 'label' => 'Email',         'required' => FALSE, 'text' => TRUE, 'width' => 32, 'note' => 'Correo válido, máximo 100 caracteres'),
		);
	}

	public function template(){

		$this->load->library('excel_import');
		$this->excel_import->sendTemplate('plantilla_proveedores.xlsx', $this->importColumns(), array(
			'Un proveedor se considera repetido cuando coinciden Empresa y Representante.',
		));
	}

	public function import(){

		$this->load->library('excel_import');

		if( !formSubmited() ){
			echo json_encode(array('type' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo'));
			return;
		}

		$db = $this->db;

		$key = function($d){ return mb_strtolower(trim($d['provider']).'|'.trim($d['representative']), 'UTF-8'); };

		$response = $this->excel_import->run(array(
			'columns' => $this->importColumns(),
			'db' => $db,
			'validate' => function(&$d){

				if( $d['representative'] === '' ) return 'El representante es obligatorio';
				if( mb_strlen($d['representative'], 'UTF-8') > 150 ) return 'El representante supera 150 caracteres';
				if( mb_strlen($d['provider'], 'UTF-8') > 150 ) return 'La empresa supera 150 caracteres';
				if( $d['phone_provider'] !== '' && !preg_match('/^[0-9 +\-]{3,20}$/', $d['phone_provider']) ) return 'Teléfono inválido';
				if( $d['email_provider'] !== '' && ( mb_strlen($d['email_provider'], 'UTF-8') > 100 || !filter_var($d['email_provider'], FILTER_VALIDATE_EMAIL) ) ) return 'Email inválido';

				return NULL;
			},
			'key' => $key,
			'existing' => function() use ($db, $key){

				$keys = array();
				foreach( $db->select('provider, representative')->get('providers')->result_array() as $r ){
					$keys[] = $key(array('provider' => (string) $r['provider'], 'representative' => (string) $r['representative']));
				}
				return $keys;
			},
			'insert' => function($rows) use ($db){

				$batch = array();
				foreach( $rows as $d ){
					$batch[] = array(
						'provider'       => $d['provider'] !== '' ? $d['provider'] : NULL,
						'representative' => $d['representative'],
						'phone_provider' => $d['phone_provider'] !== '' ? $d['phone_provider'] : NULL,
						'email_provider' => $d['email_provider'] !== '' ? $d['email_provider'] : NULL,
					);
				}

				if( $db->insert_batch('providers', $batch) !== count($batch) ){
					throw new Exception('insert_batch failed');
				}
			},
		));

		$new_tk = generateToken();
		$this->session->set_userdata('tk', $new_tk);
		$response['tk'] = $new_tk;

		echo json_encode($response);
	}

	private function set_rules(){
		$this->form_validation->set_rules('representative','','trim|required|max_length[150]');
		if(!empty($_POST['phone_provider']))
			$this->form_validation->set_rules('phone_provider','','regex_match[/^[0-9 -+]{3,20}$/]');
		if(!empty($_POST['email_provider']))
			$this->form_validation->set_rules('email_provider','','min_length[3]|max_length[100]|valid_email');
	}

	public function register(){

		if( formSubmited() ){

			$this->set_rules();

			if($this->form_validation->run() == FALSE){
				echo json_encode(array('message'=>'Por favor verifica los datos del formulario','type'=>'error'));
				return;
			}

			$data = array(
				'provider'       => $this->input->post('provider'),
				'representative' => $this->input->post('representative'),
				'phone_provider' => $this->input->post('phone_provider'),
				'email_provider' => $this->input->post('email_provider'),
			);

			try {
				$this->db->insert('providers',$data);
			} catch (\Throwable $th) {
				$message = $th->getMessage() ?? 'Ha ocurrido un error registrando proveedor';
				echo json_encode(array('message'=>$message,'type'=>'error'));
				return;
			}

			$new_tk = generateToken();
			$this->session->set_userdata('tk', $new_tk);

			echo json_encode(array('message'=>'Proveedor registrado correctamente','type'=>'success','tk'=>$new_tk));
		}
		else{

			$this->form_providers();
		}
	}

	public function edit( $id_provider = 0 ){

		$provider = $this->db->get_where('providers',array('id_provider'=>$id_provider))->row();

		if( !$provider ){

			$view_data['status'] = array( 'message' => 'El proveedor no existe', 'class' => 'error' );
			$this->session->set_userdata('view_data',$view_data);
			redirect('providers');
			exit();
		}

		if( formSubmited() ){

			$this->set_rules();

			if($this->form_validation->run() == FALSE){
				echo json_encode(array('message'=>'Por favor verifica los datos del formulario','type'=>'error'));
				return;
			}

			$data = array(
				'provider'       => $this->input->post('provider'),
				'representative' => $this->input->post('representative'),
				'phone_provider' => $this->input->post('phone_provider'),
				'email_provider' => $this->input->post('email_provider'),
			);

			try {
				$this->db->update('providers',$data,array('id_provider'=>$id_provider));
			} catch (\Throwable $th) {
				$message = $th->getMessage() ?? 'Ha ocurrido un error editando proveedor';
				echo json_encode(array('message'=>$message,'type'=>'error'));
				return;
			}

			$new_tk = generateToken();
			$this->session->set_userdata('tk', $new_tk);

			echo json_encode(array('message'=>'Proveedor editado correctamente','type'=>'success','edit'=>true,'tk'=>$new_tk));
		}
		else{

			$this->form_providers(array('provider'=>$provider));
		}
	}

	private function form_providers( $view_data = array() ){

		$view_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $view_data['tk']);
		$this->content_data = $view_data;
		$this->renderPanel('form_providers');
	}

	public function delete( $id_provider = 0 ){

		try {
			$provider = $this->db->get_where('providers',array('id_provider'=>$id_provider))->row();
	
			if( !$provider ){
				throw new Exception('El proveedor no existe');
			}
	
			$this->db->where('id_provider', $id_provider);
			$this->db->update('providers', ["active" => 0]);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Proveedor eliminado' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('providers');

		} catch (\Throwable $th) {
			$message = $th->getMessage() ?? "Ha ocurrido un error eliminando proveedor";

			$view_data['status'] = array( 'class' => 'error', 'message' => $message );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('providers');
		}

	}

	/* DISABLED: purchase orders (Pedidos) are no longer part of the project; kept as a backup, unreachable.
	public function order( $id_provider = 0 ){

		$provider = $this->db->get_where('providers',array('id_provider'=>$id_provider))->row();

		if( !$provider ){

			$view_data['status'] = array( 'message' => 'El proveedor no existe', 'class' => 'error' );
			$this->session->set_userdata('view_data',$view_data);
			redirect('providers');
			exit();
		}

		$data['provider'] = $provider;
		$this->content_data = $data;
		$this->renderPanel('form_orders');
	}
	*/
}