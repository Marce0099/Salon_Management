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

            return $this->db->query("select sum(T.quantity * T.price_cost_cor) as total_cost_cor from ($query) as T")->row();
        }
        else{

            return $this->db->query("select sum(quantity * price_cost_cor) as total_cost_cor from returns_products")->row();
        }
    }

    // Everything below runs inside Returns::register()'s transaction; any Exception
    // makes the caller roll back (stock updates included).
    // Lines are INVOICE LINES (invoice_products rows): $id_lines[i] + $modes[i] ('unit' | 'pack') + $quantities[i].
    // Stock and limits are always in base units. $modes[i] is 'unit' (always allowed) or the id_pack the line was
    // BOUGHT in (whole boxes/packs, from the line's own snapshot): units = quantity x pack_units. Nothing else is
    // offered: a line can only give units or the presentation it was purchased as.
    public function saveProductsReturn( $id_return, $id_lines, $modes, $quantities, $id_invoice ){

    	$id_invoice = (int)$id_invoice;

    	// all returns against one invoice are serialized on its row, so two concurrent returns cannot both
    	// pass the "pending to return" checks below; products are then locked in ascending id (kitchen/receiving order)
    	if( !$this->db->query('SELECT id_invoice FROM invoices WHERE id_invoice = ? FOR UPDATE', array($id_invoice))->row() ){
    		throw new Exception('La factura seleccionada no existe');
    	}

    	$lines = array(); // id_invoice_product => row of this invoice
    	foreach( $this->db->get_where('invoice_products', array( 'id_invoice' => $id_invoice ))->result() as $l ){
    		$lines[(int)$l->id_invoice_product] = $l;
    	}

    	$entries = array(); // one per (line, mode); repeated rows are merged
    	foreach( (array)$id_lines as $i => $raw_id ){

    		$raw_id  = trim((string)$raw_id);
    		$raw_qty = isset($quantities[$i]) ? trim((string)$quantities[$i]) : '';
    		$mode    = isset($modes[$i]) ? (string)$modes[$i] : 'unit';

    		if( !ctype_digit($raw_id) || !isset($lines[(int)$raw_id]) ){
    			throw new Exception('Línea de factura inválida');
    		}
    		if( !ctype_digit($raw_qty) || (int)$raw_qty < 1 || (int)$raw_qty > 1000000 ){
    			throw new Exception('La cantidad a devolver debe ser un número entero mayor a cero');
    		}
    		$line = $lines[(int)$raw_id];
    		$pack = NULL;

    		if( $mode !== 'unit' ){

    			if( !ctype_digit($mode) || (int)$mode < 1 ){
    				throw new Exception('Presentación inválida');
    			}

    			if( $line->id_pack === NULL || (int)$line->id_pack !== (int)$mode || (int)$line->pack_units < 2 ){
    				throw new Exception('Esa línea de la factura no se compró en esa presentación: devuelva unidades o la presentación comprada');
    			}

    			$pack = (object)array( 'name' => $line->pack_name, 'units' => (int)$line->pack_units );
    		}

    		$key = (int)$raw_id . ':' . $mode;
    		$entries[$key] = array(
    			'line'  => (int)$raw_id,
    			'pack'  => $pack,
    			'qty'   => ( isset($entries[$key]) ? $entries[$key]['qty'] : 0 ) + (int)$raw_qty,
    		);
    	}

    	if( !$entries ){
    		throw new Exception('Debe seleccionar al menos un producto');
    	}

    	$per_line = array(); // units requested per line
    	$per_prod = array(); // units requested per product

    	foreach( $entries as $k => $e ){

    		$line  = $lines[$e['line']];
    		$units = $e['pack'] ? $e['qty'] * (int)$e['pack']->units : $e['qty'];
    		$idp   = (int)$line->id_product;

    		$entries[$k]['units'] = $units;
    		$per_line[$e['line']] = ( isset($per_line[$e['line']]) ? $per_line[$e['line']] : 0 ) + $units;
    		$per_prod[$idp]       = ( isset($per_prod[$idp]) ? $per_prod[$idp] : 0 ) + $units;
    	}

    	ksort($per_prod);

    	$this->db->query(
    		'SELECT id_product FROM products WHERE id_product IN ('.implode(',', array_map('intval', array_keys($per_prod))).') ORDER BY id_product FOR UPDATE'
    	);

    	$names = array();

    	foreach( $per_prod as $id_product => $units ){

    		$product = $this->db->get_where('products', array( 'id_product' => $id_product ))->row();

    		if( !$product ){
    			throw new Exception('Producto inválido');
    		}
    		if( (int)$product->is_service === 1 ){
    			throw new Exception('No se puede devolver un servicio: ' . $product->name);
    		}

    		$names[$id_product] = $product->name;

    		// what is returned in total for the product may not exceed what the invoice received
    		$invoiced = (int)$this->db->query(
    			'SELECT SUM(quantity) AS qty FROM invoice_products WHERE id_invoice = ? AND id_product = ?',
    			array( $id_invoice, $id_product )
    		)->row('qty');

    		$already = (int)$this->db->query(
    			'SELECT COALESCE(SUM(RP.quantity), 0) AS qty FROM returns_products RP
    			 INNER JOIN returns R ON RP.id_return = R.id_return
    			 WHERE R.id_invoice = ? AND RP.id_product = ? AND R.id_return != ? LOCK IN SHARE MODE',
    			array( $id_invoice, $id_product, (int)$id_return )
    		)->row('qty');

    		if( $units > $invoiced - $already ){
    			throw new Exception('La cantidad a devolver de ' . $product->name . ' excede lo facturado pendiente de devolver (' . max($invoiced - $already, 0) . ' u)');
    		}

    		// atomic stock check + decrement: never goes below zero, even under concurrency
    		$this->db->query(
    			'UPDATE products SET stock = stock - ? WHERE id_product = ? AND stock >= ?',
    			array( $units, $id_product, $units )
    		);

    		if( $this->db->affected_rows() < 1 ){
    			throw new Exception('Stock insuficiente para: ' . $product->name . ' (se necesitan ' . $units . ' u; puede que ya se hayan vendido)');
    		}
    	}

    	// per invoice line: units already returned from THAT line
    	foreach( $per_line as $id_line => $units ){

    		$returned = (int)$this->db->query(
    			'SELECT COALESCE(SUM(RP.quantity), 0) AS qty FROM returns_products RP WHERE RP.id_invoice_product = ? AND RP.id_return != ? LOCK IN SHARE MODE',
    			array( $id_line, (int)$id_return )
    		)->row('qty');

    		$line = $lines[$id_line];

    		if( $units > (int)$line->quantity - $returned ){
    			throw new Exception('La cantidad a devolver de ' . $names[(int)$line->id_product] . ' excede lo pendiente de esa línea de la factura (' . max((int)$line->quantity - $returned, 0) . ' u)');
    		}
    	}

    	$data = array();

    	foreach( $entries as $e ){

    		$line = $lines[$e['line']];

    		$data[] = array(
    			'id_return'          => $id_return,
    			'id_product'         => (int)$line->id_product,
    			'id_invoice_product' => $e['line'],
    			// presentation actually returned; units taken loose out of a pack line keep that line's pack as context
    			'pack_name'          => $e['pack'] ? $e['pack']->name : $line->pack_name,
    			'pack_qty'           => $e['pack'] ? $e['qty'] : NULL,
    			'pack_units'         => $e['pack'] ? (int)$e['pack']->units : $line->pack_units,
    			'quantity'           => $e['units'],
    			'price_cost_cor'     => $line->price_cost_cor,
    			'price_sell_cor'     => $line->price_sell_cor,
    		);
    	}

    	$this->db->insert_batch('returns_products', $data);

    	$audit_lines = array();
    	foreach( $data as $d ){
    		$st = $this->db->select('name, stock')->where('id_product', $d['id_product'])->get('products')->row();
    		$audit_lines[] = array('id_product' => $d['id_product'], 'name' => $st ? $st->name : NULL, 'units' => (int) $d['quantity'], 'pack' => $d['pack_name'], 'stock_after' => $st ? (int) $st->stock : NULL, 'price_cost_cor' => $d['price_cost_cor']);
    	}
    	$this->audit->log('return.create', 'return', $id_return, array(
    		'summary' => 'Devolución a proveedor registrada (factura #'.(int) $id_invoice.', '.count($audit_lines).' linea(s))',
    		'after' => array('id_invoice' => (int) $id_invoice, 'lines' => $audit_lines)
    	));
    }

    // Lines of an invoice with what is still returnable from each (units), for the returns form.
    public function invoiceLines( $id_invoice ){

    	$rows = $this->db->query(
    		'SELECT IP.id_invoice_product, IP.id_product, P.name, P.barcode, P.stock,
    		        IP.quantity, IP.id_pack, IP.pack_name, IP.pack_qty, IP.pack_units, IP.price_cost_cor,
    		        COALESCE((SELECT SUM(RP.quantity) FROM returns_products RP WHERE RP.id_invoice_product = IP.id_invoice_product), 0) AS returned
    		 FROM invoice_products IP INNER JOIN products P ON IP.id_product = P.id_product
    		 WHERE IP.id_invoice = ? AND P.active = 1 AND P.is_service = 0
    		 ORDER BY IP.id_invoice_product',
    		array((int)$id_invoice)
    	)->result();

    	// returns made before migration 019 are not tied to a line: they reduce the product's total
    	$legacy = array();
    	foreach( $this->db->query(
    		'SELECT RP.id_product, SUM(RP.quantity) AS qty FROM returns_products RP INNER JOIN returns R ON RP.id_return = R.id_return
    		 WHERE R.id_invoice = ? AND RP.id_invoice_product IS NULL GROUP BY RP.id_product', array((int)$id_invoice)
    	)->result() as $l ){
    		$legacy[(int)$l->id_product] = (int)$l->qty;
    	}

    	foreach( $rows as $r ){

    		$pending = (int)$r->quantity - (int)$r->returned;

    		if( !empty($legacy[(int)$r->id_product]) && $pending > 0 ){
    			$take = min($pending, $legacy[(int)$r->id_product]);
    			$legacy[(int)$r->id_product] -= $take;
    			$pending -= $take;
    		}

    		$r->pending    = $pending;
    		$r->pack_units = $r->pack_units === NULL ? NULL : (int)$r->pack_units;
    		// the selector offers units plus the presentation this line was bought in (none for loose-unit lines)
    		$r->packs = array();
    		if( $r->id_pack !== NULL && $r->pack_units !== NULL && $r->pack_units >= 2 ){
    			$r->packs[] = array( 'id' => (int)$r->id_pack, 'name' => $r->pack_name, 'units' => $r->pack_units );
    		}
    	}

    	return $rows;
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

    	$sql = 'SELECT P.id_product, P.name, P.barcode, P.stock, IP.price_cost_cor, IP.price_sell_cor
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