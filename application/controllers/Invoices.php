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

				$invoice['invoice_no'] = $this->input->post('invoice_no');
				$invoice['id_provider'] = $this->input->post('id_provider') ? $this->input->post('id_provider') : NULL;
				$dt = str_replace('/', '-', $this->input->post('date_time'));
				$dt .= ' '.date('H:i:s');
				$invoice['date_time'] = date('Y-m-d H:i:s', strtotime($dt));

				if( $this->invoices_mdl->invoiceNumberExists( $invoice['invoice_no'], NULL, $invoice['id_provider'] ) ){

					$view_data['type'] = 'error';
					$view_data['message'] = 'El numero de factura ingresado ya existe';

					echo json_encode($view_data);
					exit();
				}

				$id_invoice = $this->invoices_mdl->saveInvoice($invoice);

				$id_products = $this->input->post('id_products');
				$quantities = $this->input->post('quantities');
				$prices_cost = $this->input->post('prices_cost');
				$prices_sell = $this->input->post('prices_sell');

				for( $i=0; $i < count($id_products); $i++ ){

					if( !empty( $id_products[$i] ) ){
						
						$invoice_details['id_product'] = $id_products[$i];
						$invoice_details['id_invoice'] = $id_invoice;
						$invoice_details['quantity'] = $quantities[$i];
						$invoice_details['price_cost'] = $prices_cost[$i];
						$invoice_details['price_sell'] = $prices_sell[$i];

						$this->invoices_mdl->saveInvoiceDetails( $invoice_details );

						$this->invoices_mdl->updateStockPrice( $id_products[$i], $quantities[$i], $prices_sell[$i], $prices_cost[$i], $invoice['id_provider'] );
					}
				}

				$view_data['type'] = 'success';
				$view_data['message'] = 'Factura ingresada e inventario actualizado';

				echo json_encode($view_data);
			}
			else{

				$this->form();
			}
		}

		private function form( $view_data = array() ){

			$view_data['providers'] = $this->db->get_where('providers',["active" => 1])->result();
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

			$view_data['invoice'] = $this->invoices_mdl->invoiceIDExists( $id_invoice );

			if( !$view_data['invoice'] ){

				$sess_data['status'] = array( 'type' => 'error', 'message' => 'La factura especificada no existe' );

				$this->session->set_userdata('view_data', $view_data);

				redirect('invoices');
				exit();
			}

			$view_data['invoice']->products = $this->invoices_mdl->getProductsInvoice($id_invoice);

			$this->content_data = $view_data;
			$this->renderPanel('invoice_products');
		}
	}

?>