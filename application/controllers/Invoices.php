<?php if( !defined('BASEPATH') ){ exit(); } 
		
	class Invoices extends Admin_Controller{

		public function __construct(){

			parent::__construct();
			$this->load->model('invoices_mdl');
		}

		public function index(){

			$this->load->library('easysearch');

			$search['query'] = 'select I.*,count(IP.id_invoice_product) as num_products,sum(IP.quantity * IP.price_cost) as total, P.provider from invoices I
			inner join invoice_products IP on I.id_invoice = IP.id_invoice
			left outer join providers P on I.id_provider = P.id_provider';
			$search['order_by'] = array( 'id_invoice' => 'desc' );
			$search['group_by'] = array( 'I.id_invoice' );
			$search['return_as'] = 'invoices';
			$search['limit'] = 100;

			$search['fields'] = array(

				'invoice_no' => array( 'column' => 'invoice_no', 'type' => 'string' ),
				'id_provider' => array( 'column' => 'P.id_provider', 'type' => 'numeric' ),
				'date_time' => array( 'column' => 'date_time', 'type' => 'string' ),
				'date_start' => array( 'column' => 'I.date_time', 'type' => 'date' )
			);

			$view_data['providers'] = $this->db->get('providers')->result();
			$data = $this->easysearch->search($search);
			$this->content_data = array_merge($data,$view_data);

			$this->renderPanel('invoices');
		}

		public function register(){

			if( formPosted() ){

				// every POST must carry the session form token (CSRF)
				if( !formSubmited() ){

					$this->registerError('Sesión inválida, recargue la página e intente de nuevo');
				}

				$invoice_no = trim((string)$this->input->post('invoice_no'));
				$raw_provider = trim((string)$this->input->post('id_provider'));

				if( $invoice_no === '' || strlen($invoice_no) > 50 ){

					$this->registerError('Número de factura inválido');
				}

				$id_provider = NULL;
				if( $raw_provider !== '' ){

					if( !ctype_digit($raw_provider) || (int)$raw_provider < 1 || !$this->db->get_where('providers',array('id_provider'=>(int)$raw_provider,'active'=>1))->row() ){

						$this->registerError('Proveedor inválido');
					}
					$id_provider = (int)$raw_provider;
				}

				$date = DateTime::createFromFormat('d/m/Y', trim((string)$this->input->post('date_time')));
				$date_errors = DateTime::getLastErrors();
				if( !$date || ($date_errors && ($date_errors['warning_count'] || $date_errors['error_count'])) ){

					$this->registerError('Fecha inválida');
				}

				$invoice['invoice_no'] = $invoice_no;
				$invoice['id_provider'] = $id_provider;
				$invoice['date_time'] = $date->format('Y-m-d').' '.date('H:i:s');

				$lines = $this->validatedLines(
					(array)$this->input->post('id_products'),
					(array)$this->input->post('quantities'),
					(array)$this->input->post('prices_cost'),
					(array)$this->input->post('prices_sell')
				);

				if( $this->invoices_mdl->invoiceNumberExists( $invoice['invoice_no'], NULL, $invoice['id_provider'] ) ){

					$this->registerError('El numero de factura ingresado ya existe');
				}

				$this->db->trans_begin();

				try{

					$id_invoice = $this->invoices_mdl->saveInvoice($invoice);

					foreach( $lines as $line ){

						$invoice_details = array(
							'id_invoice' => $id_invoice,
							'id_product' => $line['id_product'],
							'quantity' => $line['quantity'],
							'price_cost' => $line['price_cost'],
							'price_sell' => $line['price_sell']
						);

						$this->invoices_mdl->saveInvoiceDetails( $invoice_details );

						$this->invoices_mdl->updateStockPrice( $line['id_product'], $line['quantity'], $line['price_sell'], $line['price_cost'], $line['is_service'] );
					}

					if( $this->db->trans_status() === FALSE ){
						throw new Exception('No se ha podido registrar la factura');
					}

					$this->db->trans_commit();

					$new_tk = generateToken();
					$this->session->set_userdata('tk', $new_tk);

					echo json_encode(array( 'type' => 'success', 'message' => 'Factura ingresada e inventario actualizado', 'tk' => $new_tk ));
				}
				catch( Exception $e ){

					$this->db->trans_rollback();

					echo json_encode(array( 'type' => 'error', 'message' => 'No se ha podido registrar la factura' ));
				}
			}
			else{

				$this->form();
			}
		}

		private function registerError( $message ){

			echo json_encode(array( 'type' => 'error', 'message' => $message ));
			exit();
		}

		// Validates the posted line arrays; returns clean lines or answers with an error and exits.
		private function validatedLines( $ids, $quantities, $costs, $sells ){

			$lines = array();

			for( $i = 0; $i < count($ids); $i++ ){

				$id = isset($ids[$i]) ? trim((string)$ids[$i]) : '';

				if( $id === '' ){
					continue;
				}

				$qty = isset($quantities[$i]) ? trim((string)$quantities[$i]) : '';
				$cost = isset($costs[$i]) ? trim((string)$costs[$i]) : '';
				$sell = isset($sells[$i]) ? trim((string)$sells[$i]) : '';

				if( !ctype_digit($id) || (int)$id < 1 ){
					$this->registerError('Producto inválido');
				}
				if( !ctype_digit($qty) || (int)$qty < 1 || (int)$qty > 1000000 ){
					$this->registerError('La cantidad debe ser un número entero mayor a cero');
				}
				if( $sell === '' || !is_numeric($sell) || (float)$sell < 0 || (float)$sell > 99999999 ){
					$this->registerError('El precio de venta debe ser un número válido');
				}
				if( $cost !== '' && ( !is_numeric($cost) || (float)$cost < 0 || (float)$cost > 99999999 ) ){
					$this->registerError('El precio de costo debe ser un número válido');
				}

				$product = $this->db->get_where('products',array('id_product'=>(int)$id,'active'=>1))->row();
				if( !$product ){
					$this->registerError('El producto seleccionado no existe');
				}

				$lines[] = array(
					'id_product' => (int)$id,
					'quantity' => (int)$qty,
					'price_cost' => $cost,
					'price_sell' => $sell,
					'is_service' => (int)$product->is_service
				);
			}

			if( !$lines ){
				$this->registerError('Debe agregar al menos un producto a la factura');
			}

			return $lines;
		}

		private function form( $view_data = array() ){

			$view_data['providers'] = $this->db->get_where('providers',["active" => 1])->result();
			$view_data['tk'] = generateToken();
			$this->session->set_userdata('tk', $view_data['tk']);
			$this->content_data = $view_data;
			$this->renderPanel('form_invoice');
		}

		public function getInvoiceByNumber(){

			$invoice_no = $this->input->post('invoice_no');

			if( $invoice_no ){

				$data['invoice'] = $this->invoices_mdl->invoiceNumberExists( $invoice_no );

				echo json_encode($data);
			}
		}

		public function details( $id_invoice = 0 ){

			$id_invoice = (int)$id_invoice;
			$view_data['invoice'] = $this->invoices_mdl->invoiceIDExists( $id_invoice );

			if( !$view_data['invoice'] ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => 'La factura especificada no existe' );

				$this->session->set_userdata('view_data', $sess_data);

				redirect('invoices');
				exit();
			}

			$view_data['invoice']->products = $this->invoices_mdl->getProductsInvoice($id_invoice);

			$this->content_data = $view_data;
			$this->renderPanel('invoice_products');
		}
	}

?>