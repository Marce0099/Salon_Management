<?php if( !defined('BASEPATH') ){ exit(); }

class Loyalty_points extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('loyalty_points_mdl');
		$this->load->model('clients_mdl');
	}

	public function index(){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->content_data['settings'] = $this->loyalty_points_mdl->getSettings();
		$this->renderPanel('form_loyalty_settings');
	}

	public function save(){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( !formPosted() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		$this->form_validation->set_rules('points_ceiling','','required|numeric|greater_than[0]');
		$this->form_validation->set_rules('tier1_discount_rate','','required|numeric');
		$this->form_validation->set_rules('tier2_discount_rate','','required|numeric');
		$this->form_validation->set_rules('tier3_discount_rate','','required|numeric');
		$this->form_validation->set_rules('tier4_discount_rate','','required|numeric');

		if( !$this->form_validation->run() ){

			$response = array( 'type' => 'error', 'message' => 'Datos invalidos' );
			echo json_encode($response);
			return;
		}

		foreach( array('tier1_discount_rate','tier2_discount_rate','tier3_discount_rate','tier4_discount_rate') as $field ){

			$rate = $this->input->post($field);

			if( $rate < 0 || $rate > 100 ){

				$response = array( 'type' => 'error', 'message' => 'Los descuentos deben estar entre 0 y 100' );
				echo json_encode($response);
				return;
			}
		}

		$data = array(
			'enabled' => $this->input->post('enabled') ? 1 : 0,
			'points_ceiling' => (int) $this->input->post('points_ceiling'),
			'tier1_discount_rate' => $this->input->post('tier1_discount_rate'),
			'tier2_discount_rate' => $this->input->post('tier2_discount_rate'),
			'tier3_discount_rate' => $this->input->post('tier3_discount_rate'),
			'tier4_discount_rate' => $this->input->post('tier4_discount_rate'),
		);

		try {
			$this->loyalty_points_mdl->saveSettings($data);
		} catch (\Throwable $th) {
			$response = array( 'type' => 'error', 'message' => 'Ha ocurrido un error actualizando el programa de puntos' );
			echo json_encode($response);
			return;
		}

		$response = array( 'type' => 'success', 'message' => 'Programa de puntos actualizado' );
		echo json_encode($response);
	}

	// AJAX — no admin gate, any logged-in user registering a sale needs this
	public function getClientBalance(){

		$id_client = (int) $this->input->post('id_client');
		$client = $this->clients_mdl->getClient($id_client);

		if( !$client ){
			echo json_encode( array( 'error' => 'Cliente inválido' ) );
			return;
		}

		$settings = $this->loyalty_points_mdl->getSettings();

		if( !$settings || !$settings->enabled ){
			echo json_encode( array( 'enabled' => false ) );
			return;
		}

		$balance = $this->loyalty_points_mdl->getClientBalance($id_client);

		$tiers = array();
		$max_eligible_tier = 0;

		for( $n = 1; $n <= 4; $n++ ){

			$threshold = round( $settings->points_ceiling * $n / 4, 2 );
			$rate = $settings->{"tier{$n}_discount_rate"};
			$eligible = $balance >= $threshold;

			if( $eligible ){
				$max_eligible_tier = $n;
			}

			$tiers[] = array(
				'tier' => $n,
				'threshold' => $threshold,
				'rate' => $rate,
				'eligible' => $eligible,
			);
		}

		echo json_encode( array(
			'enabled' => true,
			'balance' => $balance,
			'ceiling' => $settings->points_ceiling,
			'tiers' => $tiers,
			'max_eligible_tier' => $max_eligible_tier,
		) );
	}

	public function registry( $id_client = 0 ){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$client = $this->db->get_where('clients', array( 'id_client' => $id_client ))->row();

		if( !$client ){

			$view_data['status'] = array( 'class' => 'error', 'message' => 'Cliente inválido' );
			$this->session->set_userdata( 'view_data', $view_data );
			redirect('clients');
		}

		$this->load->library('easysearch');

		$search['query'] = 'select * from loyalty_point_movements';
		$search['order_by'] = array( 'id_loyalty_movement' => 'desc' );
		$search['return_as'] = 'movements';
		$search['limit'] = 100;
		$search['fields'] = array(
			'movement_type' => array( 'column' => 'movement_type', 'type' => 'string' ),
			'date_start' => array( 'column' => 'date_time', 'type' => 'date' ),
		);

		$this->easysearch->setInitialWhere('id_client = '.(int) $id_client);
		$data = $this->easysearch->search($search);

		$data['client'] = $client;
		$data['balance'] = $this->loyalty_points_mdl->getClientBalance($id_client);

		$this->content_data = $data;
		$this->renderPanel('loyalty_registry');
	}
}
