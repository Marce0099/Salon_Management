<?php
require 'print/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

if( !defined('BASEPATH') ){ exit(); }


class Sales extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('sales_mdl');
		$this->load->model('cash_mdl');
		$this->load->model('payments_mdl');
		$this->load->model('bank_account_mdl');
	}

	private function format_columns( $col_1, $col_2, $col_3 ){

	    $ww = wordwrap($col_2,27);
	    $strings = explode("\n",$ww);
	    $line = str_pad( $col_1, 6, " ", STR_PAD_RIGHT );

	    for($i=0; $i<count($strings); $i++){

	        if( $i == 0 )
	            $line .= str_pad($strings[$i],27," ", STR_PAD_RIGHT) . $col_3;
	        else
	            $line .= str_pad($strings[$i], strlen($strings[$i]) + 6, " ", STR_PAD_LEFT);

	        $line .= "\n";
	    }

	    $line .= "------------------------------------------\n";

	    return $line;
	}


	public function index(){}

	public function historial(){
		
		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		/*$search['query'] = 'select S.*,E.name as employee from sales S left outer join employees E on
		S.id_employee = E.id_employee';*/
		$search['query'] = 'select S.id_sale, S.id_employee, S.date_time, CONCAT(C.name_client, \' \', C.surname_client) as client ,S.dollar_ex_buy, S.dollar_ex_sell,E.name as employee from sales S left outer join employees E on S.id_employee = E.id_employee left outer join clients C on C.id_client = S.client';
		$search['order_by'] = array( 'id_sale' => 'desc' );
		$search['return_as'] = 'sales';
		$search['limit'] = 150;

		$search['fields'] = array(

			'invoice_no' => array( 'column' => 'id_sale', 'type' => 'numeric' ),
			'id_employee' => array( 'column' => 'S.id_employee', 'type' => 'numeric' ),
			'date_time' => array( 'column' => 'date_time', 'type' => 'string' ),
			'date_start' => array( 'column' => 'S.date_time', 'type' => 'date' ),
			'client' => array( 'column' => 'CONCAT(C.name_client, \' \', C.surname_client)', 'type' => 'string' ),
			'employee' => array( 'column' => 'employee', 'type' => 'string' )
		);

		$view_data['employees'] = $this->db->get('employees')->result();
		$data = $this->easysearch->search($search);
		$this->content_data = array_merge($view_data,$data);

		$this->renderPanel('sales',$data);
	}

	public function register(){

		if( !$this->cash_mdl->getOpenedCash() ){

			$sess_data['status'] = array( 'message' => 'Para registrar ventas debe primero abrir la caja del día', 'class' => 'warning' );
			$this->session->set_userdata('view_data',$sess_data);
			redirect('cash/historial');
			exit();
		}

		if( formSubmited() ){
			
			$id_products = $this->input->post('id_products');
			$quantities = $this->input->post('quantities');
			$prices = $this->input->post('prices');
			$discounts = $this->input->post('discounts');
			$commission_rate = $this->input->post('commission_rate');
			$my_product = $this->input->post('my_product');

			$sale['client'] = $this->input->post('id_client');
			$sale['id_employee'] = $this->input->post('id_employee') ? $this->input->post('id_employee') : NULL;
			//$sale['pos_paid'] = $this->input->post('pos_paid') ? 1 : 0;

			if( !$sale['client'] ){

				$response['type'] = 'error';
				$response['message'] = 'Debe seleccionar un cliente';
				echo json_encode($response);
				exit();
			}

			if( !$sale['id_employee'] ){

				$response['type'] = 'error';
				$response['message'] = 'Debe seleccionar un vendedor';
				echo json_encode($response);
				exit();
			}

			$payment_methods = $this->input->post('payment_method');
			$cordobas_inputs = $this->input->post('cordobas_input');
			$dolar_inputs = $this->input->post('dolar_input');
			$account_numbers = $this->input->post('account_number');
			$cupon_codes = $this->input->post('cupon_code');
			$cupon_amounts = $this->input->post('cupon_amount');
			$cupon_discounts = $this->input->post('cupon_discount_rate'); // plumbed through for a future feature, never stored

			foreach( $payment_methods as $pm ){
				if( !in_array($pm, array('cash','pos','transfer','cupon')) ){

					$response['type'] = 'error';
					$response['message'] = 'Metodo de pago no válido';
					echo json_encode($response);
					exit();
				}
			}

			$cash_count = 0;
			foreach( $payment_methods as $pm ){
				if( $pm === 'cash' ) $cash_count++;
			}

			if( $cash_count > 1 ){

				$response['type'] = 'error';
				$response['message'] = 'No se permite más de un método de pago en efectivo por venta';
				echo json_encode($response);
				exit();
			}

			/*if( !$sale['pos_paid'] ){

				$sale['cash_cordobas'] = str_replace(',', '',$this->input->post('cash_cordobas'));
				$sale['cash_dollar'] = str_replace(',','',$this->input->post('cash_dollar'));
			}*/

			$dt = str_replace('/', '-', $this->input->post('date_time'));
			$dt .= ' '.date('H:i:s');
			$sale['date_time'] = date('Y-m-d H:i:s', strtotime($dt));

			$exchange = $this->db->get('dollar_exchange')->row();
			$sale['dollar_ex_buy'] = $exchange->buy_price;
			$sale['dollar_ex_sell'] = $exchange->sell_price;

			/* invoice total, needed unconditionally for the coupon overpay check below */
			$total_cordobas = 0;
			for( $i=0; $i < count($id_products); $i++ ){

				$price = str_replace(',', '', $prices[$i] );
				$subtotal = $price * $quantities[$i];

				if( $discounts[$i] )
					$subtotal -= $subtotal * $discounts[$i] / 100;

				$total_cordobas += $subtotal * $exchange->sell_price;
			}

			/* coupon uniqueness + aggregate non-cash overpay rejection (mirrors the JS check) */
			$seen_codes = array();
			$non_cash_total = 0;

			for( $i=0; $i < count($payment_methods); $i++ ){

				$method = $payment_methods[$i];

				if( $method === 'cupon' ){

					$code = trim($cupon_codes[$i]);
					$amount = floatval(str_replace(',', '', $cupon_amounts[$i]));

					if( $code === '' || $amount <= 0 ){

						$response['type'] = 'error';
						$response['message'] = 'Debe ingresar el código y el monto del cupón';
						echo json_encode($response);
						exit();
					}

					if( isset($seen_codes[$code]) ){

						$response['type'] = 'error';
						$response['message'] = 'El cupón "'.$code.'" ya fue usado en otro metodo de pago de esta venta';
						echo json_encode($response);
						exit();
					}
					$seen_codes[$code] = TRUE;

					$non_cash_total += $amount;

				}else if( $method !== 'cash' ){

					$amt = floatval(str_replace(',', '', $cordobas_inputs[$i]));

					if( $dolar_inputs[$i] )
						$amt += floatval(str_replace(',', '', $dolar_inputs[$i])) * floatval($exchange->sell_price);

					$non_cash_total += $amt;
				}
			}

			/* only cash may cover an overpayment (it gives change); POS/transfer/cupon combined can't exceed the total */
			if( $non_cash_total - $total_cordobas > 0.01 ){

				$response['type'] = 'error';
				$response['message'] = 'El total de pagos con POS, transferencia y/o cupón no puede exceder el total de la factura. El efectivo debe cubrir cualquier excedente.';
				echo json_encode($response);
				exit();
			}

			/* check cash available */

			//if( !$sale['pos_paid'] ){
			$has_cash = in_array('cash', $payment_methods);

			if( $has_cash ){

				$_total_cordobas = $total_cordobas;
				$cash_cordobas = 0;

				for( $i=0; $i < count($payment_methods); $i++ ){

					$method = $payment_methods[$i];

					if( $method === 'cupon' ){
						$amt = floatval(str_replace(',', '', $cupon_amounts[$i]));
					}else{
						$amt = floatval(str_replace(',', '', $cordobas_inputs[$i]));

						if( $dolar_inputs[$i] )
							$amt += floatval(str_replace(',', '', $dolar_inputs[$i])) * floatval($exchange->sell_price);
					}

					if( $method === 'cash' ){
						$cash_cordobas += $amt;
					}else{
						$_total_cordobas -= $amt;
					}
				}

				/*$cash_cordobas = floatval($sale['cash_cordobas']);

				if( $sale['cash_dollar'] )
					$cash_cordobas += round( floatval($sale['cash_dollar']) * floatval($exchange->buy_price ));*/

				$change = $cash_cordobas - $_total_cordobas;
				$current_cash = $this->cash_mdl->getCashMovements();
				$current_cash = $current_cash['sales_total_cordobas'] + $current_cash['current']->open_quantity - $current_cash['outcomes_total'];

				if( $change > $current_cash ){

					$response['type'] = 'warning';
					$response['message'] = 'No hay suficiente efectivo en caja para el cambio';

					echo json_encode($response);
					exit();
				}
			}

			/*******/

			for( $i=0; $i < count($id_products); $i++ ){

				$product = $this->db->get_where('products', array( 'id_product' => $id_products[$i] ))->row();

				if( !$product->is_service && $product->stock < $quantities[$i] ){

					$response['type'] = 'error';
					$response['message'] = 'Stock insuficiente para "'.$product->name.'" (disponible: '.$product->stock.')';
					echo json_encode($response);
					exit();
				}
			}

			$this->db->trans_start();
			$id_sale = $this->sales_mdl->saveSale( $sale );

			$payment = array();

			for( $i=0; $i < count($payment_methods); $i++ ){

				$method = $payment_methods[$i];

				if( $method === 'cupon' ){

					array_push($payment, array(
						"id_sale" => $id_sale,
						"payment_method" => $method,
						"account" => null,
						"cordoba" => floatval(str_replace(',', '', $cupon_amounts[$i])),
						"dolar" => null,
						"tax_rate" => null,
					));
					// cupon_code / cupon_discount_rate intentionally not persisted

				}else{

					array_push($payment, array(
						"id_sale" => $id_sale,
						"payment_method" => $method,
						"account" => $method === 'transfer' ? $account_numbers[$i] : null,
						"cordoba" => str_replace(',', '', $cordobas_inputs[$i]),
						"dolar" => str_replace(',', '', $dolar_inputs[$i]),
						"tax_rate" => $method === 'pos' ? 6.75 : null,
					));
				}
			}

			// $payment_to_save holds the amounts actually persisted to sale_payments (converted
			// to match each transfer account's currency); $payment itself is left with the
			// raw customer-typed amounts, since the residual/change calculations below still
			// need those against the sale-wide dollar_exchange rate.
			$payment_to_save = $payment;

			foreach($payment_to_save as &$pay){
				if($pay['payment_method'] === 'transfer'){
					$res = $this->bank_account_mdl->getAccount( $pay['account'] );
					if( !$res ){
						$response['type'] = 'error';
						$response['message'] = 'Cuenta bancaria no existe';
						$this->db->trans_rollback();
						echo json_encode($response);
						exit();
					}
					if( (int) $res->active !== 1 ){
						$response['type'] = 'error';
						$response['message'] = 'Cuenta bancaria ya no está disponible';
						$this->db->trans_rollback();
						echo json_encode($response);
						exit();
					}

					$cordobaAmt = floatval( str_replace(',', '', $pay['cordoba']) );
					$dolarAmt   = floatval( str_replace(',', '', $pay['dolar']) );

					if( $res->type === 'dolares' && $cordobaAmt > 0 && (float) $res->sell_rate <= 0 ){
						$response['type'] = 'error';
						$response['message'] = 'La cuenta no tiene una tasa de venta válida configurada';
						$this->db->trans_rollback();
						echo json_encode($response);
						exit();
					}
					if( $res->type === 'cordobas' && $dolarAmt > 0 && (float) $res->buy_rate <= 0 ){
						$response['type'] = 'error';
						$response['message'] = 'La cuenta no tiene una tasa de compra válida configurada';
						$this->db->trans_rollback();
						echo json_encode($response);
						exit();
					}

					if( $res->type === 'dolares' ){
						$converted = $cordobaAmt > 0 ? ($cordobaAmt / $res->sell_rate) : 0;
						$pay['dolar']   = $dolarAmt + $converted;
						$pay['cordoba'] = 0;
					}else{
						$converted = $dolarAmt > 0 ? ($dolarAmt * $res->buy_rate) : 0;
						$pay['cordoba'] = $cordobaAmt + $converted;
						$pay['dolar']   = 0;
					}

					$pay['account'] = $res->id_account;

				}
			}unset($pay);

			$this->payments_mdl->savePayment( $payment_to_save );
			
			$total_cordobas = 0;

			for( $i=0; $i < count($id_products); $i++ ){

				$sale_product['id_sale'] = $id_sale;
				$sale_product['id_product'] = $id_products[$i];
				$sale_product['quantity'] = $quantities[$i];
				$sale_product['price'] = str_replace(',', '', $prices[$i] );
				$sale_product['discount'] = $discounts[$i];
				$cr = $commission_rate[$i] === "" ? NULL : $commission_rate[$i];
				$sale_product['commission_rate'] = $cr;
				$sale_product['my_product'] = $my_product[$i];

				$subtotal = $sale_product['price'] * $sale_product['quantity'];

				if( $sale_product['discount'] )
					$subtotal -= $subtotal * $sale_product['discount'] / 100;

				$total_cordobas += $subtotal * $exchange->sell_price;

				$stock = $this->sales_mdl->saveSaleProduct($sale_product);
			}

			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE) {
				$response['type'] = 'error';
				$response['message'] = 'No se ha podido guardar la venta';

				echo json_encode($response);
				exit();
			}

			ob_start();
			$prnt = $this->printInvoice( $id_sale );
			ob_end_clean();

			ob_start();
			$prnt = $this->printInvoice( $id_sale, TRUE );
			ob_end_clean();

			$message = '<p>Venta registrada</p>';

			$residual_cordobas = $total_cordobas;
			foreach ($payment as $p) {
				if ($p["payment_method"] !== "cash") {
					$residual_cordobas -= floatval($p["cordoba"]) + floatval($p["dolar"]) * floatval($exchange->sell_price);
				}
			}

			foreach ($payment as $p) {
				if($p["payment_method"] === "cash"){
					$cash_cordobas = floatval($p["cordoba"]);

					if( $p['dolar'] )
					$cash_cordobas += floatval($p['dolar']) * floatval($exchange->sell_price);

					$message .= '<p>Cambio: C$'.number_format($cash_cordobas - $residual_cordobas, 2 ).'</p>';
				}
			}

			/*if( !$sale['pos_paid'] ){

				$cash_cordobas = floatval($sale['cash_cordobas']);

				if( $sale['cash_dollar'] )
					$cash_cordobas += round( floatval($sale['cash_dollar']) * floatval($exchange->buy_price ));

				$message .= '<p>Cambio: C$'.number_format($cash_cordobas - $total_cordobas, 2 ).'</p>';
			}*/

			if( !$prnt )
				$message .= '<p>No ha sido posible imprimir la factura</p>';

			$response['message'] = $message;
			$response['type'] = 'success';
			echo json_encode($response);
		}
		else{
			$this->sale_form();
		}
	}

	private function printInvoice( $id_sale = 0, $copy = FALSE ){

		$sale = $this->sales_mdl->getSale( $id_sale );
		$details = $this->sales_mdl->getDetails( $id_sale );
		$payment = $this->payments_mdl->getPayment( $id_sale );

		try{

			$logo_path = str_replace("\\",'/', FCPATH . 'assets/img/logo_invoice.png');

			$connector = new WindowsPrintConnector('CAJA');
			$printer = new Printer($connector);
			$printer->initialize();
			
			//logo
			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$logo = EscposImage::load($logo_path, false);
			//$printer->graphics($logo);
			$printer->bitImage($logo);
			$printer->selectPrintMode(Printer::MODE_UNDERLINE);
			$printer->text("\n\"Siente la Diferencia\"");
			$printer->selectPrintMode();
			$printer->feed(2);

			if( !$copy ){

				$printer->setTextSize(1,1);
				$printer->text("RUC: 2811201760006N\n");
				//$printer->text("Dirección: Centro Comercial Paseo Real \nMódulo B-2B\nLeón-Nicaragua.");
				$printer->text("Direccion: Costado sur funeraria monte\nlos olivos 2 1/2c al este,\nManagua-Nicaragua.");
				$printer->text("Telefono: 8885-1430");
				$printer->selectPrintMode();
				$printer->feed(2);
			}
			else{

				$printer->setTextSize(1,1);
				$printer->text("COPIA DE FACTURA\n");
				$printer->selectPrintMode();
				$printer->feed(2);
			}

			//invoice info
			$printer -> setJustification(Printer::JUSTIFY_CENTER);
			$printer->text( 'Fecha: ' . date('d/m/Y h:i:s') . "\n" );
			$printer->text( 'No. Factura: ' . $sale->id_sale );
			$printer->feed(2);

			//products header
			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$printer->text( str_pad('Cant.', 6, ' ', STR_PAD_RIGHT) );
			$printer->text( str_pad('Descripción', 27, ' ', STR_PAD_RIGHT) );
			$printer->text( "Subtotal\n" );
			$printer->text( "------------------------------------------\n" );
			$printer->selectPrintMode();

			//products
			$total_dol = 0;

			foreach( $details as $d ){

				$subtotal = $d->quantity * $d->price;

				if( $d->discount )
					$subtotal -= $subtotal * $d->discount / 100;

				$total_dol += $subtotal;

				$printer->text( $this->format_columns(

					$d->quantity,
					$d->name,
					number_format( $subtotal,2)
				));
			}

			$total_cor = $total_dol * $sale->dollar_ex_sell;
			$_total_cor = $total_cor;

			//totals
			$printer->feed();
			$printer->setJustification(Printer::JUSTIFY_RIGHT);
			$printer->text("Total en Dolares:".str_pad("$".number_format($total_dol,2)."\n",15,' ',STR_PAD_LEFT) );
			$printer->text("Total en Cordobas:".str_pad("C$".number_format($total_cor,2)."\n",15,' ',STR_PAD_LEFT) );

			$methods = [
				'cash' => 'Efectivo',
				'pos' => 'POS',
				'transfer' => 'Transferencia',
				'cupon' => 'Cupón'
			];

			$cash = array();
			foreach($payment as $pay){
				if($pay->payment_method === 'cash'){
					array_push($cash, $pay);
				}else{
					$cor = floatval($pay->cordoba);
					$dol = floatval($pay->dolar);

					if( $cor > 0 ){
						$printer->text($methods[$pay->payment_method].":".str_pad("C$".number_format($pay->cordoba,2)."\n",15,' ',STR_PAD_LEFT) );
					}

					if( $dol > 0 ){
						$printer->text($methods[$pay->payment_method].":".str_pad("$".number_format($pay->dolar,2)."\n",15,' ',STR_PAD_LEFT) );
					}

					$dol_to_cor = $dol * $sale->dollar_ex_sell;
					$total_cordobas = $cor + $dol_to_cor;

					$_total_cor -= $total_cordobas;

					if( $pay->tax_rate ){
						$commission = $total_cordobas * ($pay->tax_rate / 100);

						$printer->text("Comision (".$pay->tax_rate."%):".str_pad("C$".number_format($commission,2)."\n",15,' ',STR_PAD_LEFT) );
					}
				}
			}

			if( count($cash) > 0 ){
				$pay = $cash[0];

				$cor = floatval($pay->cordoba);
				$dol = floatval($pay->dolar);

				if( $cor > 0 ){
					$printer->text($methods[$pay->payment_method].":".str_pad("C$".number_format($pay->cordoba,2)."\n",15,' ',STR_PAD_LEFT) );
				}

				if( $dol > 0 ){
					$printer->text($methods[$pay->payment_method].":".str_pad("$".number_format($pay->dolar,2)."\n",15,' ',STR_PAD_LEFT) );
				}

				$dol_to_cor = $dol * $sale->dollar_ex_sell;
				$total_cordobas = $cor + $dol_to_cor;

				$change_cordobas = $total_cordobas - $_total_cor;
				$change_dollars = $change_cordobas / $sale->dollar_ex_buy;

				$printer->text("Cambio: ".str_pad("$".number_format($change_dollars,2)." (C$".number_format($change_cordobas,2).")\n",15,' ',STR_PAD_LEFT) );
			}

			/*if( $sale->pos_paid )
				$printer->text( 'POS' );
			else{

				if( $sale->cash_cordobas )
					$printer->text("Efectivo:".str_pad("C$".number_format($sale->cash_cordobas,2)."\n",15,' ',STR_PAD_LEFT) );

				if( $sale->cash_dollar )
					$printer->text("Efectivo:".str_pad("$".number_format($sale->cash_dollar,2)."\n",15,' ',STR_PAD_LEFT) );

				$cor = 0;

				if( $sale->cash_cordobas )
					$cor = $sale->cash_cordobas;

				if( $sale->cash_dollar )
					$cor += $sale->cash_dollar * $sale->dollar_ex_sell;

				$printer->text("Cambio: ".str_pad("C$".number_format($cor - $total_cor,2)."\n",15,' ',STR_PAD_LEFT) );
			}*/

			//seller info
			$printer->feed(2);
			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$printer->text("Vendedor: ".$sale->name);
			$printer->feed(2);

			//footer
			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$printer->selectPrintMode(Printer::MODE_FONT_B);
			$printer->text('No se aceptan devoluciones');
			$printer->feed(1);
			$printer->text("Gracias por preferirnos!!\nLe esperamos pronto.");
			$printer->feed(2);
			$printer->cut();
			$printer->close();

			return TRUE;
		}
		catch( \Throwable $e ){
			return FALSE;
		}
	}

	public function sale_form(){

		$data['employees'] = $this->db->query('select * from employees')->result();
		$data['exchange'] = $this->db->get('dollar_exchange')->row();
		$this->content_data = $data;
		$this->content_data['accounts'] = $this->bank_account_mdl->getAccounts();
		$this->renderPanel('form_sales');
	}

	public function returns(){

		$this->load->library('easysearch');

		$search['query'] = '';
		$search['return_as'] = 'returns';

		$data['returns'] = array();

		$this->renderPanel('returns',$data);
	}

	public function register_return(){

		$this->renderPanel('form_returns');
	}

	public function details($id_sale=0){

		$payments = $this->payments_mdl->getPayment($id_sale);
		usort($payments, function($a, $b) {
			$priority = [
				'transfer' => 1,
				'pos' => 1,
				'cupon' => 1,
				'cash' => 2
			];
			return ($priority[$a->payment_method] ?? 99) <=> ($priority[$b->payment_method] ?? 99);
		});

		$view_data['sale'] = $this->sales_mdl->getSale($id_sale);
		$view_data['details'] = $this->sales_mdl->getDetails($id_sale);
		$view_data['payments'] = $payments;
		
		$view_data['payment_methods'] = [
			'cash' => 'Efectivo',
			'pos' => 'POS',
			'transfer' => 'Transferencia',
			'cupon' => 'Cupón'
		];
		$this->content_data = $view_data;
		$this->renderPanel('sale_details');
	}

	public function goal(){
		if($this->get_userID() !== 1){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->library('easysearch');

        $search['query'] = 'SELECT month_goal, year_goal, amount FROM `goals`';
        $search['order_by'] = array( 'month_goal' => 'DESC',  'year_goal' => 'DESC');
        $search['group_by'] = array('month_goal', 'year_goal', 'amount');
        $search['return_as'] = 'sale_goal';
        $search['limit'] = 100;
        $search['fields'] = array(

                'id_provider' => array( 'column' => 'month_goal', 'type' => 'tinyint' ),
                'date_start' => array( 'column' => 'year_goal', 'type' => 'year' ),
                'date_end' => array( 'column' => 'amount', 'type' => 'decimal' )
            );

        $data = $this->easysearch->search($search);

		$this->content_data = $data;
		
		$this->renderPanel('sale_goal');
	}
}