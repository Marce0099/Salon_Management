<?php if( !defined('BASEPATH') ){ exit(); }

class Cash extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('cash_mdl');
	}

	public function index(){

		$this->historial();
	}

	public function historial(){

		$data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = 'select C.*,A1.name as open_admin,A2.name as close_admin from cash C inner join admins A1
		on C.open_id_admin = A1.id_admin left outer join admins A2 on
		A2.id_admin = C.close_id_admin';
		$search['order_by'] = array( 'id_cash' => 'desc' );
		$search['return_as'] = 'historial';
		$search['limit'] = 100;

		$search['fields'] = array(

			//'open_date_time' => array( 'column' => 'open_date_time', 'type' => 'string' ),
			'date_start' => array( 'column' => 'open_date_time', 'type' => 'date' )
		);

		$view_data = $this->easysearch->search($search);

		foreach( $view_data['historial'] as $h ){

			$h->outcomes = $this->db->query('select sum(quantity) as outcomes from outcomes where date(date_time) = date("'.$h->open_date_time.'")')->row('outcomes');
		}

		$this->content_data = array_merge($view_data,$data);

		$this->renderPanel('cash_historial');
	}

	public function state(){

		$data = load_data($this->session);
		$cash = $this->cash_mdl->getOpenedCash();
		
		if( !$cash ){

			$message = '<p>No hay caja abierta para el dia actual</p>';

			$sess_data['status'] = array( 'message' => $message, 'class' => 'warning' );
			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		$state = $this->cash_mdl->getCashMovements();
		$data['payment_methods'] = [
			'cash' => 'Efectivo',
			'pos' => 'POS',
			'transfer' => 'Transferencia',
			'cupon' => 'Cupón'
		];

		$this->content_data = array_merge($data,$state);

		$this->renderPanel('cash_state');
	}

	public function open(){

		if( $this->cash_mdl->getDayCash() ){

			$sess_data['status'] = array( 'message' => 'Ya existe una caja registrada para el dia actual', 'class' => 'warning' );

			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		if( formSubmited() ){

			$this->form_validation->set_rules('quantity','','required|numeric|greater_than[-1]');
			$this->form_validation->set_rules('quantity_dollar','','required|numeric|greater_than[-1]');

			if( $this->form_validation->run() ){

				$data['open_quantity'] = str_replace(',', '', number_format($this->input->post('quantity'),2));
				$data['open_quantity_dollar'] = str_replace(',', '', number_format($this->input->post('quantity_dollar'),2));
				$data['open_id_admin'] = $this->id_admin;

				try {
					
					$this->cash_mdl->save_open( $data );
				} catch (\Throwable $th) {
					$sess_data['status'] = array( 'message' => 'Ha ocurrido un error al abrir caja', 'class' => 'error' );
					$this->session->set_userdata('view_data',$sess_data);
					redirect('cash/open');
					exit;
				}

				$query = "SELECT * FROM `clients` WHERE active=1 AND DAY(birth_client) = DAY(CURRENT_TIMESTAMP) AND MONTH(birth_client) = MONTH(CURRENT_TIMESTAMP);";

				$data['birth'] = $this->db->query($query)->result();

				try {

					$this->load->library('my_phpmailer');
					$email_html = $this->load->view('open_cash_report', $data, TRUE); // Render as string

					$this->my_phpmailer->sendOpenCash($email_html);
				} catch (\Throwable $th) {
					log_message('error', 'sendOpenCash failed: ' . $th->getMessage());
				}

				$sess_data['status'] = array( 'message' => 'La caja ha sido abierta con C$'.number_format($data['open_quantity']).' y $'.number_format($data['open_quantity_dollar']), 'class' => 'success' );

				$this->session->set_userdata('view_data',$sess_data);
				redirect('cash/state');
			}
			else{

				$sess_data['status'] = array( 'message' => 'Datos invalidos para apertura de caja', 'class' => 'error' );
				$this->session->set_userdata('view_data',$sess_data);
				redirect('cash/open');
			}
		}
		else{

			$this->content_data = load_data($this->session);
			$this->content_data['tk'] = generateToken();
			$this->session->set_userdata('tk', $this->content_data['tk']);
			$this->renderPanel('form_open_cash');
		}
	}

	public function register_outcome(){

		if( !$this->cash_mdl->getOpenedCash() ){

			$sess_data['status'] = array( 'message' => 'No hay caja abierta para el dia actual', 'class' => 'warning' );

			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		if( formSubmited() ){

			$this->form_validation->set_rules('quantity','','required|numeric|greater_than[0]');
			$this->form_validation->set_rules('description','','required|trim');

			if( $this->form_validation->run() ){

				$data['quantity'] = str_replace(',', '', number_format($this->input->post('quantity'),2));
				$data['id_admin'] = $this->id_admin;
				$data['description'] = $this->input->post('description');

				try {
					$this->cash_mdl->save_outcome( $data );
				} catch (\Throwable $th) {
					$sess_data['status'] = array( 'message' => 'Ha ocurrido un error al registrar el egreso', 'class' => 'error' );
					$this->session->set_userdata('view_data',$sess_data);
					redirect('cash/register_outcome');
					exit;
				}

				$sess_data['status'] = array( 'message' => 'Registro de egreso registrado', 'class' => 'success' );

				$this->session->set_userdata('view_data',$sess_data);
				redirect('cash/state');
			}
			else{

				$sess_data['status'] = array( 'message' => 'Datos invalidos de egreso', 'class' => 'error' );
				$this->session->set_userdata('view_data',$sess_data);
				redirect('cash/register_outcome');
			}
		}
		else{

			$this->content_data = load_data($this->session);
			$this->content_data['tk'] = generateToken();
			$this->session->set_userdata('tk', $this->content_data['tk']);
			$this->renderPanel('form_outcomes');
		}
	}

	public function close(){

		if( !$this->cash_mdl->getOpenedCash() ){

			$sess_data['status'] = array( 'message' => 'No hay caja abierta para el dia actual', 'class' => 'warning' );

			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		if( formSubmited() ){

			$this->form_validation->set_rules('user_cordobas','','required');
			$this->form_validation->set_rules('user_dolares','','required');

			if( $this->form_validation->run() ){

				$cash = $this->cash_mdl->getCashMovements();

				$data['close_quantity_cordobas'] = $cash['sales_total_cordobas'] + $cash['current']->open_quantity - $cash['outcomes_total'];
				$data['close_quantity_pos'] = $cash['sales_total_pos'];
				$data['close_quantity_transfer'] = $cash['sales_total_transfer'];
				$data['close_quantity_cupon'] = $cash['sales_total_cupon'];
				$data['close_quantity_cancelled_deposits'] = $cash['cancelled_deposits_total'];
				$data['close_quantity_dollar'] = $cash['sales_total_dollar'] + $cash['current']->open_quantity_dollar;
				$data['close_user_quantity_cordobas'] = str_replace(',', '', $this->input->post('user_cordobas'));
				$data['close_user_quantity_dollar'] = str_replace(',', '', $this->input->post('user_dolares'));
				$data['close_id_admin'] = $this->id_admin;
				$data['close_date_time'] = date('Y-m-d H:i:s');

				try {
					$this->cash_mdl->save_close( $data );

				} catch (\Throwable $th) {
					$sess_data['status'] = array( 'message' => 'No se pudo cerrar caja', 'class' => 'error' );

					$this->close_form($sess_data);
					exit;
				}

				foreach($data as $key => $value){
					$cash['current']->$key = $value;
				}

				$data = $cash;
				$data['cash'] = $cash['current'];
				$data['payment_methods'] = [
					'cash' => 'Efectivo',
					'pos' => 'POS',
					'transfer' => 'Transferencia',
					'cupon' => 'Cupón'
				];
				$data['implements'] = [
					'print_btn' => false,
					'borders' => 'border: 1px solid black'
				];

				try {

					$this->load->library('my_phpmailer');
					$email_html = $this->load->view('cash_details', $data, TRUE); // Render as string

					$this->my_phpmailer->sendCloseCash($email_html);
				} catch (\Throwable $th) {
					log_message('error', 'sendCloseCash failed: ' . $th->getMessage());
				}

				$sess_data['status'] = array( 'message' => 'La caja ha sido cerrada', 'class' => 'success' );

				$this->session->set_userdata('view_data',$sess_data);
				redirect('cash/historial');
			}
			else{

				$sess_data['status'] = array( 'message' => 'Datos invalidos para el cierre de caja', 'class' => 'error' );

				$this->close_form($sess_data);
			}
		}
		else{

			$this->close_form();
		}
	}

	private function close_form( $view_data = array() ){

		$view_data['cash'] = $this->cash_mdl->getCashMovements();
		$view_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $view_data['tk']);
		$this->content_data = $view_data;

		$this->renderPanel('form_close_cash');
	}

	public function details( $id_cash = 0 ){
	
		$cash = $this->cash_mdl->getCash($id_cash);

		if( !$cash ){

			$sess_data['status'] = array( 'message' => 'El registro de caja seleccionado no existe', 'class' => 'error' );

			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		$data = $this->cash_mdl->getCashMovements( $cash );
		$data['cash'] = $cash;
		$data['payment_methods'] = [
			'cash' => 'Efectivo',
			'pos' => 'POS',
			'transfer' => 'Transferencia',
			'cupon' => 'Cupón'
		];

		$this->content_data = $data;
		$this->renderPanel('cash_details');
	}
}