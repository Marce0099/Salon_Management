<?php if( !defined('BASEPATH') ) exit();

class Internal_use_mdl extends CI_Model{

    public function __construct(){

        parent::__construct();
    }

    public function save( $id_admin, $reason ){

    	$this->db->insert( 'internal_uses', array(
    		'id_admin' => $id_admin,
    		'reason'   => $reason,
    	));

    	return $this->db->insert_id();
    }

    public function getTotalInternalUse($get, $query){

        if( $get ){

            return $this->db->query("select sum(T.quantity * T.price_cost) as total_cost from ($query) as T")->row();
        }
        else{

            return $this->db->query("select sum(quantity * price_cost) as total_cost from internal_use_products")->row();
        }
    }

    public function saveProducts( $id_internal_use, $id_products, $quantities ){

    	$data    = array();
    	$updates = array();

    	if( !empty($id_products) ){

    		for( $i=0; $i < count($id_products); $i++ ){

    			$product = $this->db->get_where('products', array( 'id_product' => $id_products[$i] ))->row();

    			if( !$product || !$product->active || $product->is_service ){
    				throw new Exception('Producto inválido: ' . ($product ? $product->name : 'ID '.$id_products[$i]));
    			}

    			if( $product->stock < $quantities[$i] ){
    				throw new Exception('Stock insuficiente para: ' . $product->name);
    			}

    			$data[] = array(
    				'id_internal_use' => $id_internal_use,
    				'id_product'      => $id_products[$i],
    				'quantity'        => $quantities[$i],
    				'price_cost'      => $product->price_cost,
    			);

    			$updates[] = array( 'id' => $id_products[$i], 'qty' => $quantities[$i] );
    		}

    		foreach( $updates as $u ){
    			$this->db->query(
    				'UPDATE products SET stock = stock - ? WHERE id_product = ?',
    				array( (int) $u['qty'], (int) $u['id'] )
    			);
    		}

    		$this->db->insert_batch('internal_use_products', $data);
    	}
    }

    public function getDetails( $id_internal_use ){

    	$sql = 'select IU.*, A.name as admin_name from internal_uses IU
    	left outer join admins A on IU.id_admin = A.id_admin
    	where IU.id_internal_use = '.$id_internal_use;

    	$internal_use = $this->db->query( $sql )->row();

    	if( $internal_use ){

    		$sql = 'select IUP.*, P.name, P.barcode from internal_use_products IUP inner join products P on IUP.id_product = P.id_product
    				where IUP.id_internal_use = '.$id_internal_use;

    		$internal_use->details = $this->db->query($sql)->result();
    	}

    	return $internal_use;
    }
}
