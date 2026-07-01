<?php if( !defined('BASEPATH') ) exit();

class Returns_mdl extends CI_Model{

    public function __construct(){

        parent::__construct();
    }

    public function save( $id_provider, $date, $id_invoice ){

    	$this->db->insert( 'returns', array(
    		'id_provider' => $id_provider,
    		'id_invoice'  => $id_invoice,
    		'date_time'   => $date,
    	));

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

    public function saveProductsReturn( $id_return, $id_products, $quantities, $id_invoice ){

    	$data    = array();
    	$updates = array();

    	if( !empty($id_products) ){

    		for( $i=0; $i < count($id_products); $i++ ){

    			$product = $this->db->get_where('products', array( 'id_product' => $id_products[$i] ))->row();

    			if( !$product || $product->stock < $quantities[$i] ){
    				throw new Exception('Stock insuficiente para: ' . ($product ? $product->name : 'ID '.$id_products[$i]));
    			}

    			$ip = $this->db->get_where('invoice_products', array(
    				'id_invoice' => $id_invoice,
    				'id_product' => $id_products[$i],
    			))->row();

    			$data[] = array(
    				'id_return'  => $id_return,
    				'id_product' => $id_products[$i],
    				'quantity'   => $quantities[$i],
    				'price_cost' => $ip ? $ip->price_cost : $product->price_cost,
    				'price_sell' => $ip ? $ip->price_sell : $product->price_sell,
    			);

    			$updates[] = array( 'id' => $id_products[$i], 'qty' => $quantities[$i] );
    		}

    		foreach( $updates as $u ){
    			$this->db->query('UPDATE products SET stock = stock - '.$u['qty'].' WHERE id_product = '.$u['id']);
    		}

    		$this->db->insert_batch('returns_products', $data);
    	}
    }

    public function getInvoicesByProvider( $id_provider ){

    	$sql = 'SELECT id_invoice, invoice_no, date_time FROM invoices
    	        WHERE id_provider = '.(int)$id_provider.' ORDER BY id_invoice DESC';

    	return $this->db->query($sql)->result();
    }

    public function getProductsByInvoice( $id_invoice ){

    	$sql = 'SELECT P.id_product, P.name, P.barcode, P.stock, IP.price_cost, IP.price_sell
    	        FROM invoice_products IP
    	        INNER JOIN products P ON IP.id_product = P.id_product
    	        WHERE IP.id_invoice = '.(int)$id_invoice.' AND P.active = 1';

    	return $this->db->query($sql)->result();
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