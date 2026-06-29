<?php if( !defined('BASEPATH') ){ exit(); }

class Products_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function barcodeExists( $barcode, $id_product = NULL ){

		$sql = 'select * from products where trim(upper(barcode)) = trim(upper("'.$barcode.'"))';

		if( $id_product ){

			$sql .= ' and id_product != '. $id_product;
		}

		return $this->db->query( $sql )->row();
	}

	public function barcodeExistsForProvider( $barcode, $id_provider ){

		$conditional = $id_provider == NULL? 'id_provider IS NULL' : 'id_provider = '.$id_provider;

		$sql = 'select * from products where trim(upper(barcode)) = trim(upper("'.$barcode.'")) and '.$conditional;/* id_provider = '.$id_provider; */

		return $this->db->query( $sql )->row();
	}

	public function save( $data ){

		$this->db->insert( 'products', $data );
	}

	public function getProduct( $id_product ){

		$sql = 'select P.*,PV.provider from products P left outer join providers PV on P.id_provider = PV.id_provider where id_product = '.$id_product;

		return $this->db->query( $sql )->row();
	}

	public function edit( $id_product, $data ){

		$data['last_update'] = NULL;
		$this->db->update('products',$data,array( 'id_product' => $id_product ));
	}

	public function edit_barcode( $data ){

		$data['last_update'] = NULL;
		$barcode = $data['barcode'];
		unset($data['barcode']);
		$this->db->update('products',$data,array( 'barcode' => $barcode ));
	}

	public function delete( $id_product ){

		$this->db->where('id_product', $id_product);
		$this->db->update('products', ["active" => 0]);

		//$this->db->delete( 'products', array( 'id_product' => $id_product ) );
	}

	public function getStock( $id_product ){

		return $this->db->get_where('products',array('id_product'=>$id_product))->row('stock');
	}

	public function addStock( $id_product, $quantity ){

		$sql = 'update products set stock = stock +'.$quantity.' where id_product = '.$id_product;

		$this->db->query($sql);

		return $this->getStock($id_product);
	}

	public function getTotalInventory($get, $query){

		if( $get ){

			return $this->db->query("select sum(T.stock * T.price_cost) as total_cost,
				sum(T.stock * T.price_sell) as total_sell from ($query) as T where T.is_service = 0")->row();
		}
		else{

			return $this->db->query("select sum(stock * price_cost) as total_cost,
				sum(stock * price_sell) as total_sell from products where is_service = 0")->row();
		}
	}
}