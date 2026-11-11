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

		// sales (history, details, checkout) are admin-only: waiters get nothing here
		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('sales_mdl');
		$this->load->model('cash_mdl');
		$this->load->model('payments_mdl');
		$this->load->model('bank_account_mdl');
	}

	private function format_columns( $col_1, $col_2, $col_3 ){

	    $ww = wordwrap($col_2,24);
	    $strings = explode("\n",$ww);
	    $line = str_pad( $col_1, 4, " ", STR_PAD_RIGHT );

	    for($i=0; $i<count($strings); $i++){

	        if( $i == 0 )
	            $line .= str_pad($strings[$i],24,".", STR_PAD_RIGHT) . str_pad($col_3,12," ", STR_PAD_LEFT) . str_repeat(" ",3);
	        else
	            $line .= str_pad($strings[$i], strlen($strings[$i]) + 4, " ", STR_PAD_LEFT);

	        $line .= "\n";
	    }

	    $line .= "-------------------------------------------\n";

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
			$commission_rate = $this->input->post('commission_rate');
			$attended_employees = $this->input->post('attended_employees');

			/* Bill checkout: the lines, prices, quantities, tip and IVA come from the bill itself (never trusted from the browser) */
			$id_bill = (int) $this->input->post('id_bill');
			$bill_ctx = NULL;

			if( $id_bill ){

				$this->load->model('bills_mdl');
				$ctx = $this->bills_mdl->checkoutContext($id_bill);

				if( is_string($ctx) ){
					$response['type'] = 'error';
					$response['message'] = $ctx;
					echo json_encode($response);
					exit();
				}

				$bill_ctx = $ctx;
				$posted_att = $attended_employees;
				$id_products = $quantities = $prices = $commission_rate = $attended_employees = array();

				foreach( $ctx['lines'] as $k => $l ){

					$id_products[] = $l['id_product'];
					$quantities[] = $l['quantity'];
					$prices[] = (string) $l['price'];
					$commission_rate[] = $l['commisssion_rate'] === NULL ? '' : $l['commisssion_rate'];
					$attended_employees[] = ( is_array($posted_att) && isset($posted_att[$k]) ) ? $posted_att[$k] : '';
				}
			}


			$sale['client'] = $this->input->post('id_client');
			$sale['id_employee'] = $this->input->post('id_employee') ? $this->input->post('id_employee') : ( $bill_ctx ? $bill_ctx['tab']->id_employee : NULL );
			//$sale['pos_paid'] = $this->input->post('pos_paid') ? 1 : 0;

			if( !$sale['client'] ){

				$this->load->model('settings_mdl');
				$sale['client'] = $this->settings_mdl->get('default_client_id', 0);
			}

			if( !$sale['client'] ){

				$response['type'] = 'error';
				$response['message'] = 'No hay un cliente por defecto configurado';
				echo json_encode($response);
				exit();
			}

			if( !$sale['id_employee'] ){

				$response['type'] = 'error';
				$response['message'] = 'Debe seleccionar un mesero';
				echo json_encode($response);
				exit();
			}

			$payment_methods = $this->input->post('payment_method') ?: array();
			$cordobas_inputs = $this->input->post('cordobas_input') ?: array();
			$dolar_inputs = $this->input->post('dolar_input') ?: array();
			$account_numbers = $this->input->post('account_number') ?: array();
			$cupon_codes = $this->input->post('cupon_code') ?: array();
			$cupon_amounts = $this->input->post('cupon_amount') ?: array();
			$cupon_discounts = $this->input->post('cupon_discount_rate') ?: array();

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

			$cupon_count = 0;
			foreach( $payment_methods as $pm ){
				if( $pm === 'cupon' ) $cupon_count++;
			}

			if( $cupon_count > 1 ){

				$response['type'] = 'error';
				$response['message'] = 'No se permite más de un método de pago con cupón por venta';
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

			/* Line discounts are courtesies: only admins may grant them (range-validated 0-100); anyone else always gets 0 */
			$posted_discounts = $bill_ctx ? array() : ( $this->input->post('discounts') ?: array() );
			$discounts = array_fill( 0, count($id_products), 0 );
			$total_cordobas = 0;
			$sale_products_cache = array();

			if( !is_array($id_products) || count($id_products) == 0 ){

				$response['type'] = 'error';
				$response['message'] = 'Debe agregar al menos un producto';
				echo json_encode($response);
				exit();
			}

			for( $i=0; $i < count($id_products); $i++ ){

				$product = $this->db->get_where('products', array( 'id_product' => $id_products[$i] ))->row();

				if( !$product ){

					$response['type'] = 'error';
					$response['message'] = 'Producto no válido';
					echo json_encode($response);
					exit();
				}

				$sale_products_cache[$i] = $product;

				if( $this->is_admin() ){

					$raw = isset($posted_discounts[$i]) ? trim($posted_discounts[$i]) : '0';
					if( $raw === '' ) $raw = '0';

					if( !is_numeric($raw) || $raw < 0 || $raw > 100 ){

						$response['type'] = 'error';
						$response['message'] = 'El descuento debe ser un número entre 0 y 100';
						echo json_encode($response);
						exit();
					}

					$discounts[$i] = (int) round( (float) $raw );
				}

				$price = str_replace(',', '', $prices[$i] );
				$subtotal = $price * $quantities[$i];

				if( $discounts[$i] )
					$subtotal -= $subtotal * $discounts[$i] / 100;

				$total_cordobas += $subtotal * $exchange->sell_price;
			}

			/* bill checkout: tip (editable, 0..subtotal) and IVA (fixed by settings) are charged on top of the lines */
			$tip_dollar = 0;
			$iva_dollar = 0;

			if( $bill_ctx ){

				$bt = $bill_ctx['totals'];
				$tip_raw = $this->input->post('tip_amount');
				$tip_dollar = ( $tip_raw === NULL || $tip_raw === '' ) ? $bt['tip'] : round( floatval(str_replace(',', '', $tip_raw)), 2 );

				if( $tip_dollar < 0 || $tip_dollar > $bt['subtotal'] ){

					$response['type'] = 'error';
					$response['message'] = 'La propina debe estar entre 0 y el subtotal de la cuenta';
					echo json_encode($response);
					exit();
				}

				$iva_dollar = $bt['iva'];
				$total_cordobas += ( $tip_dollar + $iva_dollar ) * $exchange->sell_price;
			}

			$sale['tip'] = $tip_dollar;
			$sale['iva'] = $iva_dollar;
			$sale['id_bill'] = $bill_ctx ? $id_bill : NULL;

			$remaining_cordobas = $total_cordobas;

			/* coupon uniqueness + sequential coverage + aggregate non-cash overpay rejection (mirrors the JS check);
			   also gathers the raw cash tendered while we're already looping payment methods */
			$seen_codes = array();
			$non_cash_total = 0;
			$cash_cordoba_raw = 0;
			$cash_dolar_raw = 0;
			$dollar_row_count = 0;
			$coupon_remaining = $remaining_cordobas;

			for( $i=0; $i < count($payment_methods); $i++ ){

				$method = $payment_methods[$i];

				if( $method === 'cupon' ){

					$code = trim($cupon_codes[$i]);
					$amount = floatval(str_replace(',', '', $cupon_amounts[$i]));
					$discount_rate = ($cupon_discounts[$i] !== '' && $cupon_discounts[$i] !== null) ? floatval($cupon_discounts[$i]) : 0;

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

					if( $discount_rate < 0 || $discount_rate > 100 ){

						$response['type'] = 'error';
						$response['message'] = 'El descuento del cupón "'.$code.'" debe estar entre 0 y 100';
						echo json_encode($response);
						exit();
					}

					if( $coupon_remaining <= 0.01 ){

						$response['type'] = 'error';
						$response['message'] = 'La factura no tiene saldo pendiente para aplicar un cupón';
						echo json_encode($response);
						exit();
					}

					if( $amount - $coupon_remaining > 0.01 ){

						$response['type'] = 'error';
						$response['message'] = 'El cupón "'.$code.'" excede el total pendiente de la factura (pendiente: C$'.number_format($coupon_remaining,2).')';
						echo json_encode($response);
						exit();
					}
					$coupon_remaining -= $amount;

					$non_cash_total += $amount;

				}else if( $method === 'cash' ){

					$cash_cordoba_raw += floatval(str_replace(',', '', $cordobas_inputs[$i]));
					$cash_dolar_raw += floatval(str_replace(',', '', $dolar_inputs[$i]));

					if( $dolar_inputs[$i] )
						$dollar_row_count++;

				}else{

					$amt = floatval(str_replace(',', '', $cordobas_inputs[$i]));

					if( $dolar_inputs[$i] ){
						$amt += floatval(str_replace(',', '', $dolar_inputs[$i])) * floatval($exchange->sell_price);
						$dollar_row_count++;
					}

					$non_cash_total += $amt;
				}
			}

			/* dollar-denominated payment amounts are only exact to the cent in dollars, which after
			   conversion at $exchange->sell_price is coarser than a córdoba cent — the "Pendiente"
			   figure the cashier types back is itself a rounded display value (see form_sales.php's
			   updateDueAmount()/paymentSufficiencyTolerance(), which this must match exactly) */
			$payment_tolerance_cordobas = paymentSufficiencyToleranceCordobas( $dollar_row_count, $exchange->sell_price );

			/* only cash may cover an overpayment (it gives change); POS/transfer/cupon combined can't exceed the total */
			if( $non_cash_total - $remaining_cordobas > $payment_tolerance_cordobas ){

				$response['type'] = 'error';
				$response['message'] = 'El total de pagos con POS, transferencia y/o cupón no puede exceder el total de la factura. El efectivo debe cubrir cualquier excedente.';
				echo json_encode($response);
				exit();
			}

			/* minimum-payment guard: total tendered across every method must cover the invoice,
			   within the same dollar-rounding tolerance computed above */
			$total_tendered_cordobas = $non_cash_total + $cash_cordoba_raw + ( $cash_dolar_raw * floatval($exchange->sell_price) );

			if( $remaining_cordobas - $total_tendered_cordobas > $payment_tolerance_cordobas ){

				$response['type'] = 'error';
				$response['message'] = 'El efectivo entregado es menor que el total de la factura';
				echo json_encode($response);
				exit();
			}

			/* a coupon code can only ever be redeemed once, across all sales */
			if( !empty($seen_codes) ){

				$used_codes = $this->payments_mdl->getUsedCouponCodes( array_keys($seen_codes) );

				if( !empty($used_codes) ){

					$response['type'] = 'error';
					$response['message'] = 'El cupón "'.$used_codes[0].'" ya fue utilizado en otra venta';
					echo json_encode($response);
					exit();
				}
			}

			/* check cash available + validate/persist the cashier's change-given split */

			$has_cash = in_array('cash', $payment_methods);
			$debt_after_non_cash = $remaining_cordobas - $non_cash_total;

			$overpayment_cordobas = $has_cash
				? cashOverpaymentCordobas( $debt_after_non_cash, $cash_cordoba_raw, $cash_dolar_raw, $exchange->sell_price )
				: 0;

			$change_cordoba_given = null;
			$change_dollar_given = null;
			$change_rounding_cordoba = null;

			if( $overpayment_cordobas > 0.01 ){

				$change_cordoba_given = floatval(str_replace(',', '', $this->input->post('change_cordoba_given')));
				$change_dollar_given = floatval(str_replace(',', '', $this->input->post('change_dollar_given')));

				if( $change_cordoba_given < 0 || $change_dollar_given < 0 ){

					$response['type'] = 'error';
					$response['message'] = 'El cambio entregado no puede ser negativo';
					echo json_encode($response);
					exit();
				}

				/* the cashier may split change across either currency regardless of what the
				   customer tendered in; only a fractional (cents-level) mismatch against the
				   owed amount is tolerated, a whole-unit gain/loss is rejected */
				$given_cordoba_equiv = $change_cordoba_given + ( $change_dollar_given * $exchange->sell_price );
				$change_rounding_cordoba = round( $overpayment_cordobas - $given_cordoba_equiv, 2 );

				if( abs($change_rounding_cordoba) >= 1 ){

					$response['type'] = 'error';
					$response['message'] = 'El cambio entregado no cuadra con el vuelto de C$'.number_format($overpayment_cordobas,2).'. Solo se permite una diferencia de centavos.';
					echo json_encode($response);
					exit();
				}
			}

			if( $has_cash ){

				$current_cash = $this->cash_mdl->getCashMovements();
				$current_cordobas = $current_cash['sales_total_cordobas'] + $current_cash['current']->open_quantity - $current_cash['outcomes_total'];
				$current_dollars = $current_cash['sales_total_dollar'] + $current_cash['current']->open_quantity_dollar;

				if( floatval($change_cordoba_given) > $current_cordobas || floatval($change_dollar_given) > $current_dollars ){

					$response['type'] = 'warning';
					$response['message'] = 'No hay suficiente efectivo en caja para el cambio';

					echo json_encode($response);
					exit();
				}
			}

			/*******/

			for( $i=0; $i < count($id_products); $i++ ){

				$product = $sale_products_cache[$i];

				if( !$bill_ctx && !$product->is_service && $product->stock < $quantities[$i] ){

					$response['type'] = 'error';
					$response['message'] = 'Stock insuficiente para "'.$product->name.'" (disponible: '.$product->stock.')';
					echo json_encode($response);
					exit();
				}
			}

			$this->db->trans_start();

			// free-form sale: take the stock of ALL lines up front under row locks (ascending product id), so a concurrent kitchen order,
			// sale or internal use can neither oversell nor deadlock with this one. Bill checkout takes none (the order already did).
			if( !$bill_ctx ){

				$qty_by_product = array();
				for( $i=0; $i < count($id_products); $i++ ){
					$qty_by_product[(int) $id_products[$i]] = (isset($qty_by_product[(int) $id_products[$i]]) ? $qty_by_product[(int) $id_products[$i]] : 0) + (int) $quantities[$i];
				}

				$this->load->model('products_mdl');
				$short = $this->products_mdl->takeStock( $qty_by_product );

				if( $short ){

					$this->db->trans_rollback();
					$response['type'] = 'error';
					$response['message'] = 'Stock insuficiente para "'.$short->name.'" (disponible: '.(int) $short->stock.')';
					echo json_encode($response);
					exit();
				}
			}

			if( $bill_ctx ){

				// exclusive lock on the bill, then make sure nobody changed or paid it since the checkout screen was built
				$locked = $this->db->query('SELECT status FROM bills WHERE id_bill = ? FOR UPDATE', array($id_bill))->row();
				$ctx2 = $this->bills_mdl->checkoutContext($id_bill);

				if( !$locked || $locked->status !== 'open' || is_string($ctx2) || $ctx2['lines'] != $bill_ctx['lines'] || $ctx2['totals'] != $bill_ctx['totals'] ){

					$response['type'] = 'error';
					$response['message'] = 'La cuenta cambió o ya fue cobrada. Recargue la pantalla e intente de nuevo';
					$this->db->trans_rollback();
					echo json_encode($response);
					exit();
				}
			}

			$id_sale = $this->sales_mdl->saveSale( $sale );

			$payment = array();

			for( $i=0; $i < count($payment_methods); $i++ ){

				$method = $payment_methods[$i];

				if( $method === 'cupon' ){

					array_push($payment, array(
						"id_sale" => $id_sale,
						"payment_method" => $method,
						"account" => null,
						"cupon_code" => trim($cupon_codes[$i]),
						"cupon_discount_rate" => ($cupon_discounts[$i] !== '' && $cupon_discounts[$i] !== null) ? floatval($cupon_discounts[$i]) : null,
						"cordoba" => floatval(str_replace(',', '', $cupon_amounts[$i])),
						"dolar" => null,
						"tax_rate" => null,
						"change_cordoba_given" => null,
						"change_dollar_given" => null,
						"change_rounding_cordoba" => null,
					));

				}else{

					array_push($payment, array(
						"id_sale" => $id_sale,
						"payment_method" => $method,
						"account" => $method === 'transfer' ? ($account_numbers[$i] ?? null) : null,
						"cupon_code" => null,
						"cupon_discount_rate" => null,
						"cordoba" => str_replace(',', '', $cordobas_inputs[$i]),
						"dolar" => str_replace(',', '', $dolar_inputs[$i]),
						"tax_rate" => $method === 'pos' ? 6.75 : null,
						"change_cordoba_given" => $method === 'cash' ? $change_cordoba_given : null,
						"change_dollar_given" => $method === 'cash' ? $change_dollar_given : null,
						"change_rounding_cordoba" => $method === 'cash' ? $change_rounding_cordoba : null,
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

			try{

				$this->payments_mdl->savePayment( $payment_to_save );
			}
			catch( Exception $e ){

				$this->db->trans_rollback();

				/* most likely cause: two sales redeemed the same coupon code at the same instant and
				   the sale_payments.cupon_code UNIQUE index rejected the loser of that race (a
				   PDOException, on this PHP/PDO version's default PDO::ERRMODE_EXCEPTION) — the
				   app-level check at the top of register() only catches codes already committed by
				   the time it ran, not ones committing concurrently */
				if( !empty($seen_codes) ){

					$used_codes = $this->payments_mdl->getUsedCouponCodes( array_keys($seen_codes) );

					if( !empty($used_codes) ){

						$response['type'] = 'error';
						$response['message'] = 'El cupón "'.$used_codes[0].'" ya fue utilizado en otra venta';
						echo json_encode($response);
						exit();
					}
				}

				$response['type'] = 'error';
				$response['message'] = 'No se ha podido guardar la venta';
				echo json_encode($response);
				exit();
			}

			$total_cordobas = 0;

			for( $i=0; $i < count($id_products); $i++ ){

				$sale_product['id_sale'] = $id_sale;
				$sale_product['id_product'] = $id_products[$i];
				$sale_product['quantity'] = $quantities[$i];
				$sale_product['price'] = str_replace(',', '', $prices[$i] );
				$sale_product['discount'] = $discounts[$i];
				$cr = $commission_rate[$i] === "" ? NULL : $commission_rate[$i];
				$sale_product['commission_rate'] = $cr;
				$ae = isset($attended_employees[$i]) && $attended_employees[$i] !== '' ? (int) $attended_employees[$i] : NULL;
				$sale_product['id_employee'] = $ae;

				$subtotal = $sale_product['price'] * $sale_product['quantity'];

				if( $sale_product['discount'] )
					$subtotal -= $subtotal * $sale_product['discount'] / 100;

				$total_cordobas += $subtotal * $exchange->sell_price;

				$stock = $this->sales_mdl->saveSaleProduct($sale_product, FALSE); // stock already taken under lock above (free-form) or by the kitchen order (bill)
			}

			if( $bill_ctx ){

				$this->bills_mdl->settle($bill_ctx['bill'], $id_sale);
			}

			$this->db->trans_complete();

			if ($this->db->trans_status() === FALSE) {
				$response['type'] = 'error';
				$response['message'] = 'No se ha podido guardar la venta';

				echo json_encode($response);
				exit();
			}

			ob_start();
			$pdf_name = $this->generateReceiptPdf( $id_sale );
			ob_end_clean();

			if( $pdf_name )
				$this->sales_mdl->setFile( $id_sale, $pdf_name );

			ob_start();
			$prnt = $this->printInvoice( $id_sale, FALSE );
			ob_end_clean();

			ob_start();
			$prnt = $this->printInvoice( $id_sale, TRUE );
			ob_end_clean();

			$message = '<p>Venta registrada</p>';

			if( $overpayment_cordobas > 0.01 ){
				$message .= '<p>Cambio entregado: C$'.number_format($change_cordoba_given,2).' / $'.number_format($change_dollar_given,2).'</p>';
			}

			/*if( !$sale['pos_paid'] ){

				$cash_cordobas = floatval($sale['cash_cordobas']);

				if( $sale['cash_dollar'] )
					$cash_cordobas += round( floatval($sale['cash_dollar']) * floatval($exchange->buy_price ));

				$message .= '<p>Cambio: C$'.number_format($cash_cordobas - $total_cordobas, 2 ).'</p>';
			}*/

			if( !$prnt )
				$message .= '<p>No ha sido posible imprimir la factura</p>';

			$new_tk = generateToken();
			$this->session->set_userdata('tk', $new_tk);

			$response['message'] = $message;
			$response['type'] = 'success';
			$response['tk'] = $new_tk;
			echo json_encode($response);
		}
		else{
			$this->sale_form();
		}
	}

	private function generateReceiptPdf( $id_sale ){

		$sale = $this->sales_mdl->getSale( $id_sale );
		$details = $this->sales_mdl->getDetails( $id_sale );
		$payment = $this->payments_mdl->getPayment( $id_sale );

		$logo = resolveLogoPath('logo_receipt', 'logo_invoice.png');

		$receipt_ruc = $this->settings_mdl->get('receipt_ruc', 'RUC del restaurante');
		$receipt_address = $this->settings_mdl->get('receipt_address', "Dirección del restaurante");
		$receipt_phone = $this->settings_mdl->get('receipt_phone', '0000-0000');
		$receipt_tagline = $this->settings_mdl->get('receipt_tagline', 'Buen provecho');
		$receipt_footer_notice = $this->settings_mdl->get('receipt_footer_notice', 'Propina no incluida salvo indicación');
		$receipt_footer_thanks = $this->settings_mdl->get('receipt_footer_thanks', "Gracias por su visita!\nLo esperamos pronto.");

		$methods = [
			'cash' => 'Efectivo',
			'pos' => 'POS',
			'transfer' => 'Transferencia',
			'cupon' => 'Cupón'
		];

		// mirrors printInvoice()'s line-item loop exactly, so the archived PDF matches what was printed
		$items_html = '';
		$total_dol = 0;

		foreach( $details as $d ){

			$subtotal = $d->quantity * $d->price;

			if( $d->discount )
				$subtotal -= $subtotal * $d->discount / 100;

			$total_dol += $subtotal;
			$subtotal_cor = $subtotal * $sale->dollar_ex_sell;

			// cap the name at 34 chars so the auto-sized "Descripción" column can never
			// grow past this width and push the price column out of alignment. Widened from 24 to
			// 34 (+10 chars of dot-leader) so this column's auto-computed width eats 10 more
			// characters' worth of the row (~40.5pt at this font) that the unstyled, unconstrained
			// Subtotal column would otherwise absorb entirely as trailing blank space — the Subtotal
			// column still auto-stretches to fill whatever remains, so its content shifts right into
			// that space instead of sitting in a mostly-empty cell
			$name = mb_strlen($d->name,'UTF-8') > 34 ? mb_substr($d->name,0,33,'UTF-8').'…' : $d->name;

			$items_html .= '
				<tr>
					<td class="left">'.$d->quantity.'</td>
					<td class="left">'.htmlspecialchars(str_pad($name,34,".",STR_PAD_RIGHT)).'</td>
					<td class="left">C$'.number_format($subtotal_cor,2).str_repeat('&nbsp;',3).'</td>
				</tr>
			';
		}

		// restaurant checkout: IVA and tip (stored in dollars) are charged on top of the lines
		$extras_html = '';
		$subtotal_lines_cor = $total_dol * $sale->dollar_ex_sell;

		if( (float) $sale->iva > 0 ){
			$extras_html .= '<tr><td class="left label-col">IVA:</td><td class="left">C$'.number_format($sale->iva * $sale->dollar_ex_sell, 2).'</td></tr>';
		}
		if( (float) $sale->tip > 0 ){
			$extras_html .= '<tr><td class="left label-col">Propina:</td><td class="left">C$'.number_format($sale->tip * $sale->dollar_ex_sell, 2).'</td></tr>';
		}
		if( $extras_html !== '' ){
			$extras_html = '<tr><td class="left label-col">Subtotal:</td><td class="left">C$'.number_format($subtotal_lines_cor, 2).'</td></tr>'.$extras_html;
		}

		$total_dol += (float) $sale->tip + (float) $sale->iva;
		$total_cor = $total_dol * $sale->dollar_ex_sell;
		$total_a_pagar_cor = $total_cor;
		$_total_cor = $total_a_pagar_cor;

		// mirrors printInvoice()'s payment-method loop exactly (non-cash rows individually, cash aggregated with change)
		$payments_html = '';
		$cash = array();

		foreach( $payment as $pay ){

			if( $pay->payment_method === 'cash' ){
				array_push($cash, $pay);
			}else{

				$cor = floatval($pay->cordoba);
				$dol = floatval($pay->dolar);

				if( $cor > 0 ){
					$payments_html .= '<tr><td class="left label-col">'.$methods[$pay->payment_method].':</td><td class="left">C$'.number_format($pay->cordoba,2).'</td></tr>';
				}

				if( $dol > 0 ){
					$payments_html .= '<tr><td class="left label-col">'.$methods[$pay->payment_method].':</td><td class="left">$'.number_format($pay->dolar,2).'</td></tr>';
				}

				if( $pay->payment_method === 'cupon' && floatval($pay->cupon_discount_rate) > 0 ){
					$neto = $cor * (1 - floatval($pay->cupon_discount_rate) / 100);
					$payments_html .= '<tr><td class="left label-col">&nbsp;&nbsp;Desc. cupón:</td><td class="left">'.number_format($pay->cupon_discount_rate,2).'% (neto C$'.number_format($neto,2).')</td></tr>';
				}

				$dol_to_cor = $dol * $sale->dollar_ex_sell;
				$total_cordobas = $cor + $dol_to_cor;

				$_total_cor -= $total_cordobas;
			}
		}

		if( count($cash) > 0 ){

			$pay = $cash[0];

			$cor = floatval($pay->cordoba);
			$dol = floatval($pay->dolar);

			if( $cor > 0 ){
				$payments_html .= '<tr><td class="left label-col">Efectivo:</td><td class="left">C$'.number_format($pay->cordoba,2).'</td></tr>';
			}

			if( $dol > 0 ){
				$payments_html .= '<tr><td class="left label-col">Efectivo:</td><td class="left">$'.number_format($pay->dolar,2).'</td></tr>';
			}

			$dol_to_cor = $dol * $sale->dollar_ex_sell;
			$total_cordobas = $cor + $dol_to_cor;

			if( $pay->change_cordoba_given !== null || $pay->change_dollar_given !== null ){

				$change_cordobas = floatval($pay->change_cordoba_given);
				$change_dollars = floatval($pay->change_dollar_given);

				if( $change_cordobas > 0 || $change_dollars > 0 ){
					$payments_html .= '<tr class="total-highlight"><td class="left label-col">Cambio:</td><td class="left">C$'.number_format($change_cordobas,2).' + $'.number_format($change_dollars,2).'</td></tr>';
				}
			}else{

				$change_cordobas = $total_cordobas - $_total_cor;
				$change_dollars = $change_cordobas / $sale->dollar_ex_buy;

				$payments_html .= '<tr class="total-highlight"><td class="left label-col">Cambio:</td><td class="left">$'.number_format($change_dollars,2).' (C$'.number_format($change_cordobas,2).')</td></tr>';
			}
		}

		require_once(FCPATH.'includes/html2pdf/html2pdf.class.php');

		ob_start();

		?>

		<style type="text/css">
<!--
		page{
			font-family: courier;
		}
		table{
			width: 100%;
			border-collapse: collapse;
		}
		table td, table th{
			font-size: 9px;
			padding: 1px 0;
		}
		.left{ text-align: left; }
		.center{ text-align: center; }
		.label-col{ width: 50%; white-space: nowrap; }
		.divider{
			border-top: 1px dashed #000000;
			margin: 4px 0;
		}
		.tagline{
			text-decoration: underline;
			font-style: italic;
		}
		.total-highlight td{
			font-size: 12px;
			font-weight: bold;
		}
		#logo{
			width: 140px;
			height: auto;
		}
-->
		</style>
		<page backtop="3mm" backbottom="3mm" backleft="3mm" backright="3mm">
			<p class="center">
				<img src="<?php echo $logo; ?>" id="logo" alt="">
			</p>
			<p class="center tagline">"<?php echo htmlspecialchars($receipt_tagline); ?>"</p>

			<p class="center">
				RUC: <?php echo htmlspecialchars($receipt_ruc); ?><br>
				Direccion: <?php echo nl2br(htmlspecialchars($receipt_address)); ?><br>
				Telefono: <?php echo htmlspecialchars($receipt_phone); ?>
			</p>

			<p class="center">
				Fecha: <?php echo date('d/m/Y h:i A'); ?><br>
				<?php if( !empty($sale->table_label) ) : ?>Mesa: <?php echo htmlspecialchars($sale->table_label.' - '.$sale->bill_label); ?><br><?php endif; ?>
				No. Factura: <?php echo $sale->id_sale; ?>
			</p>

			<div class="divider"></div>

			<table>
				<tr>
					<th class="left">Cant.</th>
					<th class="left">Descripción</th>
					<th class="left">Subtotal</th>
				</tr>
				<?php echo $items_html; ?>
			</table>

			<div class="divider"></div>

			<table>
				<?php echo $extras_html; ?>
				<tr><td class="left label-col">Total en Cordobas:</td><td class="left">C$<?php echo number_format($total_cor,2); ?></td></tr>
				<tr class="total-highlight"><td class="left label-col">Total a pagar:</td><td class="left">C$<?php echo number_format($total_a_pagar_cor,2); ?></td></tr>
			</table>

			<div class="divider"></div>

			<table>
				<?php echo $payments_html; ?>
			</table>

			<p class="left">Cliente: <?php echo htmlspecialchars($sale->client); ?></p>
			<p class="left">Vendedor: <?php echo htmlspecialchars($sale->name); ?></p>

			<div class="divider"></div>

			<p class="center"><?php echo htmlspecialchars($receipt_footer_notice); ?></p>
			<p class="center"><?php echo nl2br(htmlspecialchars($receipt_footer_thanks)); ?></p>
		</page>

		<?php

		$content = ob_get_clean();

		try{

			$receipts_dir = FCPATH.'uploads/receipts/';

			if( !is_dir($receipts_dir) )
				mkdir($receipts_dir, 0755, true);

			$pdf_name = 'sale_'.$id_sale.'_'.time().'.pdf';

			$pdf_path = $receipts_dir.$pdf_name;

			// html2pdf/TCPDF are PHP 5 era code; any leftover deprecation would be logged once per
			// pixel/glyph (thousands of file writes per sale), so keep them out of the log here
			$prev_error_reporting = error_reporting( error_reporting() & ~E_DEPRECATED );

			$html2pdf = new HTML2PDF('P', array(80,200), 'es', true, 'UTF-8', array(0,0,0,0) );
			$html2pdf->writeHTML($content);
			$html2pdf->Output($pdf_path, 'F');

			error_reporting( $prev_error_reporting );

			return $pdf_name;
		}
		catch( Exception $e ){

			if( isset($prev_error_reporting) )
				error_reporting( $prev_error_reporting );

			return NULL;
		}
	}

	private function printInvoice( $id_sale = 0, $copy = FALSE ){

		$sale = $this->sales_mdl->getSale( $id_sale );
		$details = $this->sales_mdl->getDetails( $id_sale );
		$payment = $this->payments_mdl->getPayment( $id_sale );

		try{

			$logo_path = resolveLogoPath('logo_receipt', 'logo_invoice.png');

			$receipt_ruc = $this->settings_mdl->get('receipt_ruc', 'RUC del restaurante');
			$receipt_address = $this->settings_mdl->get('receipt_address', "Dirección del restaurante");
			$receipt_phone = $this->settings_mdl->get('receipt_phone', '0000-0000');
			$receipt_tagline = $this->settings_mdl->get('receipt_tagline', 'Buen provecho');
			$receipt_footer_notice = $this->settings_mdl->get('receipt_footer_notice', 'Propina no incluida salvo indicación');
			$receipt_footer_thanks = $this->settings_mdl->get('receipt_footer_thanks', "Gracias por su visita!\nLo esperamos pronto.");

			$connector = new WindowsPrintConnector('CAJA');
			$printer = new Printer($connector);
			$printer->initialize();

			//logo
			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$logo = EscposImage::load($logo_path, false);
			//$printer->graphics($logo);
			$printer->bitImage($logo);
			$printer->selectPrintMode(Printer::MODE_UNDERLINE);
			$printer->text("\n\"".$receipt_tagline."\"");
			$printer->selectPrintMode();
			$printer->feed(2);

			if( !$copy ){

				$printer->setTextSize(1,1);
				$printer->text("RUC: ".$receipt_ruc."\n");
				$printer->text("Direccion: ".$receipt_address);
				$printer->text("Telefono: ".$receipt_phone);
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
			$printer->text( 'Fecha: ' . date('d/m/Y h:i A') . "\n" );
			if( !empty($sale->table_label) ){
				$printer->text( 'Mesa: ' . $sale->table_label . ' - ' . $sale->bill_label . "\n" );
			}
			$printer->text( 'No. Factura: ' . $sale->id_sale );
			$printer->feed(2);

			//products header
			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$printer->text( str_pad('Cant.', 4, ' ', STR_PAD_RIGHT) );
			$printer->text( str_pad('Descripción', 24, ' ', STR_PAD_RIGHT) );
			$printer->text( str_pad("Subtotal", 12, ' ', STR_PAD_LEFT) . str_repeat(' ', 3) . "\n" );
			$printer->text( "-------------------------------------------\n" );
			$printer->selectPrintMode();

			//products
			$total_dol = 0;

			foreach( $details as $d ){

				$subtotal = $d->quantity * $d->price;

				if( $d->discount )
					$subtotal -= $subtotal * $d->discount / 100;

				$total_dol += $subtotal;
				$subtotal_cor = $subtotal * $sale->dollar_ex_sell;

				$printer->text( $this->format_columns(

					$d->quantity,
					$d->name,
					"C$".number_format( $subtotal_cor,2)
				));
			}

			if( (float) $sale->tip > 0 || (float) $sale->iva > 0 ){

				$printer->text( str_repeat('-', 43)."\n" );
				$printer->text( $this->format_columns( '', 'Subtotal', "C$".number_format( $total_dol * $sale->dollar_ex_sell, 2 ) ) );

				if( (float) $sale->iva > 0 ){
					$printer->text( $this->format_columns( '', 'IVA', "C$".number_format( $sale->iva * $sale->dollar_ex_sell, 2 ) ) );
				}
				if( (float) $sale->tip > 0 ){
					$printer->text( $this->format_columns( '', 'Propina', "C$".number_format( $sale->tip * $sale->dollar_ex_sell, 2 ) ) );
				}

				$total_dol += (float) $sale->tip + (float) $sale->iva;
			}

			$total_cor = $total_dol * $sale->dollar_ex_sell;
			$total_a_pagar_cor = $total_cor;
			$_total_cor = $total_a_pagar_cor;

			//totals
			$printer->feed();
			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$label_width = 20;
			//$printer->text(str_pad("Total en Dolares:", $label_width, ' ', STR_PAD_RIGHT)."$".number_format($total_dol,2)."\n" );
			$printer->text(str_pad("Total en Cordobas:", $label_width, ' ', STR_PAD_RIGHT)."C$".number_format($total_cor,2)."\n" );

			$printer->selectPrintMode(Printer::MODE_EMPHASIZED);
			$printer->text(str_pad("Total a pagar:", $label_width, ' ', STR_PAD_RIGHT)."C$".number_format($total_a_pagar_cor,2)."\n" );
			$printer->selectPrintMode();
			$printer->text( "-------------------------------------------\n" );

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
						$printer->text(str_pad($methods[$pay->payment_method].":", $label_width, ' ', STR_PAD_RIGHT)."C$".number_format($pay->cordoba,2)."\n" );
					}

					if( $dol > 0 ){
						$printer->text(str_pad($methods[$pay->payment_method].":", $label_width, ' ', STR_PAD_RIGHT)."$".number_format($pay->dolar,2)."\n" );
					}

					if( $pay->payment_method === 'cupon' && floatval($pay->cupon_discount_rate) > 0 ){
						$neto = $cor * (1 - floatval($pay->cupon_discount_rate) / 100);
						$printer->text("  Desc. cupón: ".number_format($pay->cupon_discount_rate,2)."% (neto C$".number_format($neto,2).")\n" );
					}

					$dol_to_cor = $dol * $sale->dollar_ex_sell;
					$total_cordobas = $cor + $dol_to_cor;

					$_total_cor -= $total_cordobas;
				}
			}

			if( count($cash) > 0 ){
				$pay = $cash[0];

				$cor = floatval($pay->cordoba);
				$dol = floatval($pay->dolar);

				if( $cor > 0 ){
					$printer->text(str_pad($methods[$pay->payment_method].":", $label_width, ' ', STR_PAD_RIGHT)."C$".number_format($pay->cordoba,2)."\n" );
				}

				if( $dol > 0 ){
					$printer->text(str_pad($methods[$pay->payment_method].":", $label_width, ' ', STR_PAD_RIGHT)."$".number_format($pay->dolar,2)."\n" );
				}

				$dol_to_cor = $dol * $sale->dollar_ex_sell;
				$total_cordobas = $cor + $dol_to_cor;

				/* sales recorded after change_cordoba_given/change_dollar_given were added print the
				   cashier's actual disbursed split; older sales fall back to the theoretical
				   córdoba-vs-dollar-equivalent figure computed the old way */
				if( $pay->change_cordoba_given !== null || $pay->change_dollar_given !== null ){

					$change_cordobas = floatval($pay->change_cordoba_given);
					$change_dollars = floatval($pay->change_dollar_given);

					if( $change_cordobas > 0 || $change_dollars > 0 ){
						$printer->selectPrintMode(Printer::MODE_EMPHASIZED);
						$printer->text(str_pad("Cambio:", $label_width, ' ', STR_PAD_RIGHT)."C$".number_format($change_cordobas,2)." + $".number_format($change_dollars,2)."\n" );
						$printer->selectPrintMode();
					}
				}else{

					$change_cordobas = $total_cordobas - $_total_cor;
					$change_dollars = $change_cordobas / $sale->dollar_ex_buy;

					$printer->selectPrintMode(Printer::MODE_EMPHASIZED);
					$printer->text(str_pad("Cambio:", $label_width, ' ', STR_PAD_RIGHT)."$".number_format($change_dollars,2)." (C$".number_format($change_cordobas,2).")\n" );
					$printer->selectPrintMode();
				}
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

			//customer + seller info
			$printer->feed(2);
			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$printer->text("Cliente: ".$sale->client);
			$printer->feed(1);
			$printer->text("Vendedor: ".$sale->name);
			$printer->feed(2);

			//footer
			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$printer->selectPrintMode(Printer::MODE_FONT_B);
			$printer->text($receipt_footer_notice);
			$printer->feed(1);
			$printer->text($receipt_footer_thanks);
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

		$this->load->model('settings_mdl');

		$data['employees'] = $this->db->query('select * from employees where active = 1')->result();
		$data['exchange'] = $this->db->get('dollar_exchange')->row();
		$data['tk'] = generateToken();
		$this->session->set_userdata('tk', $data['tk']);
		$this->content_data = $data;
		$this->content_data['accounts'] = $this->bank_account_mdl->getAccounts();
		$this->content_data['prefill'] = NULL;
		$this->content_data['bill_error'] = NULL;

		$id_bill = (int) $this->input->get('bill');

		if( $id_bill ){

			$this->load->model('bills_mdl');
			$ctx = $this->bills_mdl->checkoutContext($id_bill);

			if( is_string($ctx) ){

				$this->content_data['bill_error'] = $ctx;
			}
			else{

				$tab = $this->db->query('SELECT d.label FROM tabs t JOIN dining_tables d ON d.id_table = t.id_table WHERE t.id_tab = ?', array((int) $ctx['tab']->id_tab))->row();
				$products = array();

				foreach( $ctx['lines'] as $l ){
					$products[] = array(
						'id_product' => $l['id_product'], 'name' => $l['name'], 'barcode' => $l['barcode'], 'is_service' => $l['is_service'],
						'price_sell' => (string) $l['price'], 'commisssion_rate' => $l['commisssion_rate'], 'quantity' => $l['quantity'],
					);
				}

				$this->content_data['prefill'] = array(
					'id_bill' => $id_bill, 'title' => 'Mesa '.$tab->label.' · '.$ctx['bill']->label, 'id_employee' => (int) $ctx['tab']->id_employee,
					'products' => $products, 'tip' => $ctx['totals']['tip'], 'iva' => $ctx['totals']['iva'], 'subtotal' => $ctx['totals']['subtotal'],
				);
			}
		}
		// salon-era automatic discounts no longer exist; zeros keep form_sales.php's legacy JS inert
		$this->content_data['vip_discount_rate'] = 0;
		$this->content_data['birthday_discount_rate'] = 0;
		$this->content_data['birthday_discount_ceiling_min'] = 0;
		$this->content_data['birthday_discount_ceiling_max'] = 0;
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
			$view_data['current_admin_id'] = (int) $this->id_admin;
		
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
		if(!$this->is_admin()){
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