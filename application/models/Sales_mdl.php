<?php if( !defined('BASEPATH') ){ exit(); }

class Sales_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function saveSale( $data ){

		$this->db->insert( 'sales', $data );

		return $this->db->insert_id();
	}

	public function saveSaleProduct( $data ){

		$product = $this->db->get_where( 'products', array( 'id_product' => $data['id_product'] ) )->row();

		$this->db->insert( 'sale_products', $data );

		if( !$product->is_service ){

			$sql = 'update products set stock = stock - '.$data['quantity'].' where id_product = '.$data['id_product'];
			$this->db->query($sql);

			$sale_balance = $data['quantity'] * $data['price'];
			$actual_balance = $product->stock * $product->price_sell;

			if( $product->stock - $data['quantity'] > 0 ){
				
				$new_price = ( $actual_balance - $sale_balance ) / ( $product->stock - $data['quantity'] );
				$new_price = str_replace(",", ".", $new_price);
				
				$sql = 'update products set price_sell = "'.$new_price.'" where id_product = '.$data['id_product'];

				$this->db->query($sql);
			}
		}
	}

	public function getDetails($id_sale){

		$sql = 'select P.*,SP.*,SP.price as sale_price from sale_products SP inner join products P
		on SP.id_product = P.id_product where SP.id_sale = '.$id_sale;

		return $this->db->query($sql)->result();
	}

	public function getByAppointment( $id_appointment ){

		return $this->db->get_where( 'sales', array( 'id_appointment' => $id_appointment ) )->row();
	}

	public function getSale($id_sale){

		/*$sql = 'select S.*,E.* from sales S left outer join employees E on
		S.id_employee = E.id_employee where S.id_sale = '.$id_sale;*/
		$sql = 'select S.id_sale, S.id_employee, S.date_time, CONCAT(C.name_client, \' \', C.surname_client) as client ,S.dollar_ex_buy, S.dollar_ex_sell,E.name from sales S left outer join employees E on S.id_employee = E.id_employee left outer join clients C on C.id_client = S.client where S.id_sale = '.$id_sale;

		return $this->db->query($sql)->row();
	}
}