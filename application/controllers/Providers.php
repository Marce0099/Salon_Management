<?php if( !defined('BASEPATH') ){ exit(); }

class Providers extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select * from providers where active="1"';
		$search['return_as'] = 'providers';
		$search['fields'] = array(
			'provider'       => array('column' => 'provider',       'type' => 'string'),
			'representative' => array('column' => 'representative', 'type' => 'string'),
			'phone_provider' => array('column' => 'phone_provider', 'type' => 'string'),
			'email_provider' => array('column' => 'email_provider', 'type' => 'string'),
		);

		$data = $this->easysearch->search($search);

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);
		
		$this->renderPanel('providers');
	}

	public function register(){

		if( formSubmited() ){

			$this->form_validation->set_rules('representative','','trim|required|max_length[150]');
			if(!empty($_POST['phone_provider']))
				$this->form_validation->set_rules('phone_provider','','regex_match[/^[0-9 -]{3,20}$/]');
			if(!empty($_POST['email_provider']))
				$this->form_validation->set_rules('email_provider','','min_length[3]|max_length[100]|valid_email');

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
			$this->db->insert('providers',$data);

			echo json_encode(array('message'=>'Proveedor registrado correctamente','type'=>'success'));
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

			$this->form_validation->set_rules('representative','','trim|required|max_length[150]');
			if(!empty($_POST['phone_provider']))
				$this->form_validation->set_rules('phone_provider','','regex_match[/^[0-9 -]{3,20}$/]');
			if(!empty($_POST['email_provider']))
				$this->form_validation->set_rules('email_provider','','min_length[3]|max_length[100]|valid_email');

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
			$this->db->update('providers',$data,array('id_provider'=>$id_provider));

			echo json_encode(array('message'=>'Proveedor editado correctamente','type'=>'success','edit'=>true));
		}
		else{

			$this->form_providers(array('provider'=>$provider));
		}
	}

	private function form_providers( $view_data = array() ){

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
}