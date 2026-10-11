<?php if( !defined('BASEPATH') ){ exit(); }
/*
 * DISABLED: the purchase-order (Pedidos) feature is no longer part of the project.
 * The original code is kept below as a backup and is intentionally unreachable:
 * the class is commented out, so /orders/* no longer resolves to a controller.
 * Do not re-enable without also restoring the Orders_mdl model, the orders views
 * and Providers::order().
 *
 */
/*

class Orders extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('orders_mdl');
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select O.*,P.provider from orders O inner join providers P
		on O.id_provider = P.id_provider';
		$search['order_by'] = array( 'id_order' => 'desc' );
		$search['return_as'] = 'orders';
		$search['limit'] = 50;
		
		$search['fields'] = array(

			'order_no' => array( 'column' => 'id_order', 'type' => 'numeric' ),
			'provider' => array( 'column' => 'P.id_provider', 'type' => 'numeric' )
		);

		$data = $this->easysearch->search($search);
		$data['providers'] = $this->db->get('providers')->result();

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);
		$this->renderPanel('orders');
	}

	public function register(){

		if( formPosted() ){

			$id_provider = $this->input->post('id_provider');
			$id_products = $this->input->post('id_products');
			$quantities = $this->input->post('quantities');

			$order['id_provider'] = $id_provider;

			$this->db->trans_begin();

			try{

				$id_order = $this->orders_mdl->save($order);

				for( $i=0; $i < count($id_products); $i++ ){

					$data['id_order'] = $id_order;
					$data['id_product'] = $id_products[$i];
					$data['quantity'] = $quantities[$i];
					$this->orders_mdl->saveOrderProduct($data);
				}

				if( $this->db->trans_status() === FALSE ){
					throw new Exception('No se ha podido registrar la orden de pedido');
				}

				$this->db->trans_commit();
			}
			catch( Exception $e ){

				$this->db->trans_rollback();

				$sess_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );
				$this->session->set_userdata('view_data',$sess_data);
				redirect('orders');
				return;
			}

			$file = $this->generatePdf($id_order, $id_provider,$id_products,$quantities);

			if( $file ){

				try{

					$this->orders_mdl->setFile($id_order,$file);
					$message = 'Orden de pedido registrada';
				}
				catch( Exception $e ){

					$message = 'Orden de pedido registrada, pero no pudo generarse el documento PDF';
				}
			}
			else
				$message = 'Orden de pedido registrada, pero no pudo generarse el documento PDF';

			$sess_data['status'] = array( 'class' => 'success', 'message' => $message );
			$this->session->set_userdata('view_data',$sess_data);
			redirect('orders');
		}
		else{

			$this->content_data['providers'] = $this->db->get('providers')->result();
			$this->renderPanel('form_orders');
		}
	}

	public function delete( $id_order = 0 ){

		try{

			$order = $this->orders_mdl->getOrder($id_order);

			if( !$order ){

				throw new Exception('Orden inválida',1);
			}

			$this->orders_mdl->delete( $id_order );

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Orden de pedido eliminada' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('orders');
		}	
		catch( Exception $e ){

			$sess_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $sess_data );
			redirect('orders');
		}
	}

	private function generatePdf( $id_order, $id_provider, $id_products, $quantities ){

		$provider = $this->db->get_where('providers',array('id_provider'=>$id_provider))->row();

		$logo = resolveLogoPath('logo_purchase_order', 'logo_invoice.jpg');

		require_once(FCPATH.'includes/html2pdf/html2pdf.class.php');

		ob_start();

		?>

		 <style type="text/css">
<!--
		table{
			border-collapse: collapse;
			width: 100%;
			margin-top: 20px;
			border: 0;
		}
		table td,table th{
			border: 1px solid #828282;
			padding: 7px;
		}
		table td{
			font-size: 10px;
		}
		.text-center{
			text-align: center;
		}
		h4{

			font-size: 12px;
			margin-bottom: 8px;
			padding-bottom: 8px;
		}
		._30{

			width: 30%;
		}
		._60{
			width: 60%;
		}
		._10{
			width: 10%;
		}
		#logo{

			width: 200px;
			height: auto;
		}
-->
		</style>
		<page backtop="8mm" backbottom="8mm" backleft="8mm" backright="8mm">
			<p class="text-center">
				<img src="<?php echo $logo; ?>" id="logo" alt="">
			</p>
			<h4 class="left"><?php echo date('d/m/Y'); ?> | Orden de pedido No. <?php echo $id_order; ?></h4>
			<h4>Proveedor: <?php echo $provider->provider; ?></h4>
			<table>
				<thead>
					<tr>
						<th class="_30">Códido de barra</th>
						<th class="_60">Nombre del producto</th>
						<th class="_10">Cantidad</th>
					</tr>
				</thead>
				<tbody>
					<?php
						for( $i=0; $i < count($id_products); $i++ ){

							$product = $this->db->get_where('products',array('id_product'=>$id_products[$i]))->row();

							echo '
							<tr>
								<td>'.$product->barcode.'</td>
								<td>'.$product->name.'</td>
								<td>'.$quantities[$i].'</td>
							</tr>
							';
						}
					?>
				</tbody>
			</table>
		</page>

		<?php

		$content = ob_get_clean();

		try{

			$pdf_name = md5(time()) .'.pdf';

			$pdf_path = FCPATH.'orders_pdf/'.$pdf_name;

	        $html2pdf = new HTML2PDF('P', 'A4', 'es', true, 'UTF-8',  array(0,0,0,0) );
	        $html2pdf->writeHTML($content);
	        $html2pdf->Output($pdf_path, 'F');

	        return $pdf_name;
		}
		catch( Exception $e ){

			return NULL;
		}
	}
}
*/
