<?php if( !defined('BASEPATH') ) exit();

class Returns_mdl extends CI_Model{

    public function __construct(){

        parent::__construct();
    }

    public function save( $id_provider, $date ){

    	$this->db->insert( 'returns', array( 'id_provider' => $id_provider, 'date_time' => $date ) );

    	return $this->db->insert_id();
    }

    public function getTotalReturns($get, $query){

        if( $get ){

            return $this->db->query("select sum(T.quantity * T.price_cost) as total_cost from ($query) as T")->row();
        }
        else{

            return $this->db->query("select sum(quantity * price_cost) as total_cost from returns_products")->row();
        }
    }

    public function saveProductsReturn( $id_return, $id_products, $quantities ){

    	$data = array();

    	if( !empty($id_products) ){

    		for( $i=0; $i < count($id_products); $i++ ){

    			$product = $this->db->get_where('products',array( 'id_product' => $id_products[$i] ))->row();

    			$return['id_return'] = $id_return;
    			$return['id_product'] = $id_products[$i];
    			$return['quantity'] = $quantities[$i];
    			$return['price_cost'] = $product->price_cost;
    			$return['price_sell'] = $product->price_sell;

    			$data[] = $return;

    			$sql = 'update products set stock = stock - '.$quantities[$i].' where id_product = '.$id_products[$i];
    			$this->db->query($sql);
    		}

    		$this->db->insert_batch('returns_products',$data);
    	}
    }

    public function getReturnsDetails( $id_return ){

    	$sql = 'select R.*,P.provider from returns R left outer join providers P on R.id_provider = P.id_provider
    	where R.id_return = '.$id_return;

    	$return = $this->db->query( $sql )->row();

    	if( $return ){

    		$sql = 'select RP.*,P.name,P.barcode from returns_products RP inner join products P on RP.id_product = P.id_product
    				where RP.id_return = '.$id_return;
    		
    		$return->details = $this->db->query($sql)->result();
    	}

    	return $return;
    }
}