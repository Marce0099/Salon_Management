<?php if( !defined('BASEPATH') ) exit();
	
	class Invoices_mdl extends CI_Model{

		public function __construct(){

			parent::__construct();
		}

		public function getInvoices(){

			$this->db->order_by('id_invoice','desc');
			return $this->db->get('invoices')->result();
		}

		public function saveInvoice( $data ){

			$this->db->insert( 'invoices', $data );

			return $this->db->insert_id();
		}

		public function invoiceIDExists( $id_invoice ){

			$sql = 'select I.*,P.provider from invoices I left outer join providers P
			on I.id_provider = P.id_provider where I.id_invoice = '.$id_invoice;

			return $this->db->query($sql)->row();
		}

		public function invoiceNumberExists( $invoice_no, $id_invoice = NULL ){

			$sql = 'select * from invoices where invoice_no = "'.$invoice_no.'"';
			if( $id_invoice ){

				$sql .= ' and id_invoice !='.$id_invoice;
			}
			return $this->db->query( $sql )->row();
		}

		public function saveInvoiceDetails( $data ){

			return $this->db->insert( 'invoice_products', $data );
		}

		public function updateStockPrice( $id_product, $quantity, $price_sell, $price_cost, $id_provider ){

			if( empty($price_cost) ){

				$sql = 'update products set stock=stock+'.$quantity.', price_sell = '.$price_sell.'   where id_product = '.$id_product;
				$this->db->query($sql);
			}
			else{

				$sql = 'update products set stock=stock+'.$quantity.', price_cost = '.$price_cost.', price_sell = '.$price_sell.'   where id_product = '.$id_product;
				$this->db->query($sql);
			}

			/*$product = $this->db->get_where( 'products', array( 'id_product' => $id_product ) )->row();

			if( !empty($price_cost) ){

				$actual_balance = $product->stock * $product->price_sell;

				if( $price_sell == $product->price_sell ){

					$in_balance = $quantity * $price_sell;
					$new_price = number_format( ( $actual_balance + $in_balance ) / ( $quantity + $product->stock ), 2);
				}
				else{
					$new_price = $price_sell;
				}
				
				$sql = 'update products set stock=stock+'.$quantity.', price_sell = '.$new_price.',
				price_cost = '.$price_cost.' where id_product = '.$id_product;
			}
			else{

				$sql = 'update products set stock=stock+'.$quantity.', price_sell = '.$price_sell.'   where id_product = '.$id_product;

			}

			$this->db->query($sql);

			if( $id_provider ){

				$this->db->update('products',array('id_provider'=>$id_provider),array('id_product'=>$id_product));
			}*/
		}

		public function getProductsInvoice( $id_invoice ){

			$sql = 'select P.name,P.barcode,IP.* from products P inner join invoice_products IP
			on P.id_product = IP.id_product where IP.id_invoice = '.$id_invoice;

			return $this->db->query($sql)->result();
		}

		/*public function updateEditStockPrice( $id_invoice, $id_product, $quantity, $price ){

			$ip = $this->db->get_where( 'invoice_products',
				array( 'id_invoice' => $id_invoice, 'id_product' => $id_product )
				)->row();

			if( $ip ){
				if( $quantity > $ip->quantity ){

					$stock_sum = $quantity - $ip->quantity;
					$this->db->query('update products set stock = stock+'.$stock_sum.' where id_product = '.$id_product);
				}
				else if( $quantity < $ip->quantity ){

					$stock_rest = $ip->quantity - $quantity;
					$this->db->query('update products set stock = stock-'.$stock_rest.' where id_product = '.$id_product);
				}
			}
			else{

				$sql = 'update products set stock=stock+'.$quantity.' where
				id_product = '.$id_product;

				$this->db->query($sql);
			}

			$this->db->update('products',array('price'=>$price),array('id_product'=>$id_product));
		}*/
	}

?>