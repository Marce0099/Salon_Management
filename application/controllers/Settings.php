<?php if( !defined('BASEPATH') ){ exit(); }

class Settings extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('settings_mdl');
	}

	public function index(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->content_data['vip_discount_rate'] = $this->settings_mdl->get('vip_discount_rate', 15);
		$this->renderPanel('form_settings');
	}

	public function save(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( !formPosted() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		$this->form_validation->set_rules('vip_discount_rate','','required|numeric');

		if( !$this->form_validation->run() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		$rate = $this->input->post('vip_discount_rate');

		if( $rate < 0 || $rate > 100 ){

			$response = array( 'type' => 'error', 'message' => 'El descuento debe estar entre 0 y 100' );
			echo json_encode($response);
			return;
		}

		try {
			$this->settings_mdl->save('vip_discount_rate', $rate);
		} catch (\Throwable $th) {
			$response = array( 'type' => 'error', 'message' => 'Ha ocurrido un error actualizando la configuración' );
			echo json_encode($response);
			return;
		}

		$response = array( 'type' => 'success', 'message' => 'Configuración actualizada' );
		echo json_encode($response);
	}
}
