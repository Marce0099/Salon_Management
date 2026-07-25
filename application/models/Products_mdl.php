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

		$sql = 'select P.*, PV.provider, SC.name_category
		        from products P
		        left outer join providers PV on P.id_provider = PV.id_provider
		        left outer join service_category SC on P.id_service_category = SC.id_service_category
		        where id_product = '.$id_product;

		return $this->db->query( $sql )->row();
	}

	public function edit( $id_product, $data ){

		$data['last_update'] = NULL;
		$this->db->update('products',$data,array( 'id_product' => $id_product ));
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

	public function getLowStockCount(){

		$sql = 'SELECT COUNT(*) AS total FROM products
		        WHERE active = 1 AND is_service = 0
		          AND stock_alert_threshold IS NOT NULL
		          AND stock <= stock_alert_threshold';

		return (int) $this->db->query($sql)->row('total');
	}

	public function getLowStockProducts(){

		$sql = 'SELECT id_product, name, stock, stock_alert_threshold FROM products
		        WHERE active = 1 AND is_service = 0
		          AND stock_alert_threshold IS NOT NULL
		          AND stock <= stock_alert_threshold
		        ORDER BY name ASC';

		return $this->db->query($sql)->result();
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

	public function searchActive($barcode = '', $name = '', $type = 'all'){

		$sql = 'SELECT id_product, barcode, name, price_cost, price_sell, is_service
		        FROM products WHERE active = 1';

		if( !empty($barcode) ){
			$safe = $this->db->escape_like_str($barcode);
			$sql .= ' AND barcode LIKE "%'.$safe.'%"';
		}
		if( !empty($name) ){
			$safe = $this->db->escape_like_str($name);
			$sql .= ' AND name LIKE "%'.$safe.'%"';
		}
		if( $type === 'products' ){
			$sql .= ' AND is_service = 0';
		}
		elseif( $type === 'services' ){
			$sql .= ' AND is_service = 1';
		}

		$sql .= ' ORDER BY name ASC LIMIT 50';

		return $this->db->query($sql)->result();
	}

	public function searchAvailable($barcode = '', $name = '', $type = 'all'){

		$sql = 'SELECT products.id_product, products.barcode, products.name, products.price_sell,
		               products.is_service, products.commisssion_rate, service_category.discount_rate AS category_discount_rate
		        FROM products
		        LEFT JOIN service_category USING(id_service_category)
		        WHERE products.active = 1 AND (products.is_service = 1 OR products.stock > 0)';

		if( !empty($barcode) ){
			$safe = $this->db->escape_like_str($barcode);
			$sql .= ' AND products.barcode LIKE "%'.$safe.'%"';
		}
		if( !empty($name) ){
			$safe = $this->db->escape_like_str($name);
			$sql .= ' AND products.name LIKE "%'.$safe.'%"';
		}
		if( $type === 'products' ){
			$sql .= ' AND products.is_service = 0';
		}
		elseif( $type === 'services' ){
			$sql .= ' AND products.is_service = 1';
		}

		$sql .= ' ORDER BY products.name ASC LIMIT 50';

		return $this->db->query($sql)->result();
	}
}