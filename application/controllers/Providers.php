<?php if( !defined('BASEPATH') ){ exit(); }

class Providers extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select * from providers where active="1"';
		$search['return_as'] = 'providers';

		$data = $this->easysearch->search($search);

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);
		
		$this->renderPanel('providers');
	}

	public function register(){

		if( formSubmited() ){

			$data['provider'] = $this->input->post('provider');
			$this->db->insert('providers',$data);

			$view_data['message'] = 'Proveedor registrado correctamente';
			$view_data['type'] = 'success';
			echo json_encode($view_data);
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

			$data['provider'] = $this->input->post('provider');
			$this->db->update('providers',$data,array('id_provider'=>$id_provider));

			$view_data['message'] = 'Proveedor editado correctamente';
			$view_data['type'] = 'success';
			$view_data['edit'] = true;
			echo json_encode($view_data);
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