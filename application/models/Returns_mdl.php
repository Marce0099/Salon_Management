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

    // Everything below runs inside Returns::register()'s transaction; any Exception
    // makes the caller roll back (stock updates included).
    public function saveProductsReturn( $id_return, $id_products, $quantities, $id_invoice ){

    	$id_invoice = (int)$id_invoice;
    	$wanted = array();

    	// all returns against one invoice are serialized on its row, so two concurrent returns cannot both
    	// pass the "pending to return" check below; products are then locked in ascending id (kitchen/receiving order)
    	if( !$this->db->query('SELECT id_invoice FROM invoices WHERE id_invoice = ? FOR UPDATE', array($id_invoice))->row() ){
    		throw new Exception('La factura seleccionada no existe');
    	}

    	// one entry per product: repeated rows are merged, never checked one by one
    	foreach( (array)$id_products as $i => $raw_id ){

    		$raw_id  = trim((string)$raw_id);
    		$raw_qty = isset($quantities[$i]) ? trim((string)$quantities[$i]) : '';

    		if( $raw_id === '' || !ctype_digit($raw_id) || (int)$raw_id < 1 ){
    			throw new Exception('Producto inválido');
    		}
    		if( !ctype_digit($raw_qty) || (int)$raw_qty < 1 || (int)$raw_qty > 1000000 ){
    			throw new Exception('La cantidad a devolver debe ser un número entero mayor a cero');
    		}

    		$id = (int)$raw_id;
    		$wanted[$id] = ( isset($wanted[$id]) ? $wanted[$id] : 0 ) + (int)$raw_qty;
    	}

    	if( !$wanted ){
    		throw new Exception('Debe seleccionar al menos un producto');
    	}

    	$data = array();

    	ksort($wanted);

    	$this->db->query(
    		'SELECT id_product FROM products WHERE id_product IN ('.implode(',', array_map('intval', array_keys($wanted))).') ORDER BY id_product FOR UPDATE'
    	);

    	foreach( $wanted as $id_product => $qty ){

    		$product = $this->db->get_where('products', array( 'id_product' => $id_product ))->row();

    		if( !$product ){
    			throw new Exception('Producto inválido');
    		}
    		if( (int)$product->is_service === 1 ){
    			throw new Exception('No se puede devolver un servicio: ' . $product->name);
    		}

    		// the product must be on the chosen invoice
    		$invoiced = $this->db->query(
    			'SELECT SUM(quantity) AS qty FROM invoice_products WHERE id_invoice = ? AND id_product = ?',
    			array( $id_invoice, $id_product )
    		)->row('qty');

    		if( $invoiced === NULL ){
    			throw new Exception('El producto no pertenece a la factura seleccionada: ' . $product->name);
    		}

    		// ...and what is returned in total may not exceed what was invoiced
    		$already = (int)$this->db->query(
    			'SELECT COALESCE(SUM(RP.quantity), 0) AS qty FROM returns_products RP
    			 INNER JOIN returns R ON RP.id_return = R.id_return
    			 WHERE R.id_invoice = ? AND RP.id_product = ? AND R.id_return != ? LOCK IN SHARE MODE',
    			array( $id_invoice, $id_product, (int)$id_return )
    		)->row('qty');

    		$pending = (int)$invoiced - $already;

    		if( $qty > $pending ){
    			throw new Exception('La cantidad a devolver de ' . $product->name . ' excede lo facturado pendiente de devolver (' . max($pending, 0) . ')');
    		}

    		$ip = $this->db->query(
    			'SELECT price_cost, price_sell FROM invoice_products WHERE id_invoice = ? AND id_product = ? ORDER BY id_invoice_product DESC LIMIT 1',
    			array( $id_invoice, $id_product )
    		)->row();

    		// atomic stock check + decrement: never goes below zero, even under concurrency
    		$this->db->query(
    			'UPDATE products SET stock = stock - ? WHERE id_product = ? AND stock >= ?',
    			array( $qty, $id_product, $qty )
    		);

    		if( $this->db->affected_rows() < 1 ){
    			throw new Exception('Stock insuficiente para: ' . $product->name);
    		}

    		$data[] = array(
    			'id_return'  => $id_return,
    			'id_product' => $id_product,
    			'quantity'   => $qty,
    			'price_cost' => $ip->price_cost,
    			'price_sell' => $ip->price_sell,
    		);
    	}

    	$this->db->insert_batch('returns_products', $data);
    }

    public function getInvoicesByProvider( $id_provider ){

    	// '' / NULL = every invoice, 'none' = receipts without provider, otherwise that provider's invoices
    	$sql = 'SELECT I.id_invoice, I.invoice_no, I.date_time, I.id_provider, P.provider
    	        FROM invoices I LEFT OUTER JOIN providers P ON I.id_provider = P.id_provider';
    	$binds = array();

    	if( $id_provider === 'none' ){
    		$sql .= ' WHERE I.id_provider IS NULL';
    	}
    	elseif( $id_provider !== NULL && $id_provider !== '' ){
    		$sql .= ' WHERE I.id_provider = ?';
    		$binds[] = (int)$id_provider;
    	}

    	return $this->db->query($sql.' ORDER BY I.id_invoice DESC LIMIT 500', $binds)->result();
    }

    // Invoice header (+ provider name) used to preselect an invoice in the returns form. NULL when it does not exist.
    public function getInvoiceForReturn( $id_invoice ){

    	return $this->db->query(
    		'SELECT I.id_invoice, I.invoice_no, I.id_provider, P.provider FROM invoices I
    		 LEFT OUTER JOIN providers P ON I.id_provider = P.id_provider WHERE I.id_invoice = ?',
    		array((int)$id_invoice)
    	)->row();
    }

    public function getProductsByInvoice( $id_invoice ){

    	$sql = 'SELECT P.id_product, P.name, P.barcode, P.stock, IP.price_cost, IP.price_sell
    	        FROM invoice_products IP
    	        INNER JOIN products P ON IP.id_product = P.id_product
    	        WHERE IP.id_invoice = ? AND P.active = 1';

    	return $this->db->query($sql, array((int)$id_invoice))->result();
    }

    public function getReturnsDetails( $id_return ){

    	$id_return = (int)$id_return;

    	$sql = 'select R.*,P.provider from returns R left outer join providers P on R.id_provider = P.id_provider
    	where R.id_return = ?';

    	$return = $this->db->query( $sql, array($id_return) )->row();

    	if( $return ){

    		$sql = 'select RP.*,P.name,P.barcode from returns_products RP inner join products P on RP.id_product = P.id_product
    				where RP.id_return = ?';

    		$return->details = $this->db->query($sql, array($id_return))->result();
    	}

    	return $return;
    }
}