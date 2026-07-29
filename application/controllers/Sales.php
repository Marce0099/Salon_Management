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
		$this->load->model('appointments_mdl');
		$this->load->model('appointment_payments_mdl');
		$this->load->model('loyalty_points_mdl');
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

	public function register( $id_appointment = 0 ){

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
			$my_product = $this->input->post('my_product');

			// Line discounts are never trusted from the client — a posted value is only ever a UI
			// convenience the browser happened to compute; the real value is recomputed below from
			// server-side data (service category default / VIP rate) once the client is resolved,
			// so a tampered or hand-crafted request can't apply a discount beyond what's legitimate.
			$discounts = array();

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

			/* continuation of an appointment's booking deposit into a full sale, if posted */
			$posted_id_appointment = (int) $this->input->post('id_appointment');
			$appointment_deposit = null;

			if( $posted_id_appointment > 0 ){

				if( $this->sales_mdl->getByAppointment( $posted_id_appointment ) ){

					$response['type'] = 'error';
					$response['message'] = 'Esta reserva ya fue facturada';
					echo json_encode($response);
					exit();
				}

				$posted_appointment = $this->appointments_mdl->getAppointment( $posted_id_appointment );

				if( $posted_appointment ){
					$sale['client'] = $posted_appointment->id_client;
				}

				$appointment_deposit = $this->appointment_payments_mdl->getForAppointment( $posted_id_appointment );
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

			/* VIP/birthday discount: resolved once from the now-final sale client, never trusted from the client */
			$this->load->model('settings_mdl');
			$vip_discount_rate = (float) $this->settings_mdl->get('vip_discount_rate', 0);
			$birthday_discount_rate = (float) $this->settings_mdl->get('birthday_discount_rate', 0);
			$sale_client_row = $this->db->get_where('clients', array( 'id_client' => $sale['client'] ))->row();
			$sale_client_is_vip = $sale_client_row && $sale_client_row->is_vip_client == 1;
			$sale_client_is_birthday = $sale_client_row && !empty($sale_client_row->birth_client)
				&& date('m-d', strtotime($sale_client_row->birth_client)) === date('m-d');

			/* invoice total + services-only subtotal, needed unconditionally for the coupon overpay check below */
			$total_cordobas = 0;
			$services_total_cordobas = 0;
			$sale_products_cache = array();
			$has_discounted_service = false;

			for( $i=0; $i < count($id_products); $i++ ){

				$product = $this->db->get_where('products', array( 'id_product' => $id_products[$i] ))->row();
				$sale_products_cache[$i] = $product;

				/* line discount is server-computed, not read from the POST: services get max(category
				   default, VIP rate, birthday rate); anything else gets 0 — mirrors the auto-fill in
				   form_sales.php, whose .p-discount inputs are readonly for this exact reason */
				$line_discount = 0;

				if( $product && $product->is_service ){

					$category_rate = 0;

					if( $product->id_service_category ){
						$category = $this->db->get_where('service_category', array( 'id_service_category' => $product->id_service_category ))->row();
						if( $category && $category->discount_rate !== null ) $category_rate = (float) $category->discount_rate;
					}

					$line_discount = max( $category_rate, $sale_client_is_vip ? $vip_discount_rate : 0, $sale_client_is_birthday ? $birthday_discount_rate : 0 );
				}

				$discounts[$i] = $line_discount;

				if( $discounts[$i] > 0 ) $has_discounted_service = true;

				$price = str_replace(',', '', $prices[$i] );
				$subtotal = $price * $quantities[$i];

				if( $discounts[$i] )
					$subtotal -= $subtotal * $discounts[$i] / 100;

				$subtotal_cordobas = $subtotal * $exchange->sell_price;
				$total_cordobas += $subtotal_cordobas;

				if( $product && $product->is_service )
					$services_total_cordobas += $subtotal_cordobas;
			}

			/* loyalty program: if the client marked "claim reward" and currently qualifies, burn the
			   threshold for the highest tier they qualify for and discount this sale's services by that
			   tier's rate; balance/eligibility are always re-validated server-side, never trusted from the client */
			$loyalty_settings = $this->loyalty_points_mdl->getSettings();
			$claim_tier = (int) $this->input->post('claim_loyalty_tier');
			$loyalty_balance_before = 0;
			$loyalty_tier_threshold = 0;
			$loyalty_tier_rate = null;
			$loyalty_discount_cordobas = 0;

			if( $loyalty_settings && $loyalty_settings->enabled ){

				// always the client's true current balance — needed unconditionally as the accrual's
				// ceiling-capping base below, not just when a reward is being claimed
				$loyalty_balance_before = $this->loyalty_points_mdl->getClientBalance( $sale['client'] );

				if( $claim_tier >= 1 && $claim_tier <= 4 ){

					$threshold = round( $loyalty_settings->points_ceiling * $claim_tier / 4, 2 );

					if( $loyalty_balance_before >= $threshold ){

						$loyalty_tier_threshold = $threshold;
						$loyalty_tier_rate = $loyalty_settings->{"tier{$claim_tier}_discount_rate"};
						$loyalty_discount_cordobas = round( $services_total_cordobas * $loyalty_tier_rate / 100, 2 );
					}
				}
			}

			if( $loyalty_discount_cordobas <= 0 ) $claim_tier = 0; // nothing eligible/claimed — silently ignored, no error

			/* an appointment's frozen booking deposit is credited against the invoice total before any
			   payment-sufficiency math runs below; it was fixed at booking time and never re-validated
			   against overpay rules here since it's already-collected money, not a new payment decision */
			$deposit_cordoba_equivalent = 0;
			$remaining_cordobas = $total_cordobas;

			if( $appointment_deposit ){

				$deposit_cordoba_equivalent = floatval($appointment_deposit->cordoba) + ( floatval($appointment_deposit->dolar) * $exchange->sell_price );
				$remaining_cordobas = max( $total_cordobas - $deposit_cordoba_equivalent, 0 );
			}

			$remaining_cordobas = max( $remaining_cordobas - $loyalty_discount_cordobas, 0 );

			/* coupon uniqueness + sequential coverage + aggregate non-cash overpay rejection (mirrors the JS check);
			   also gathers the raw cash tendered while we're already looping payment methods */
			$seen_codes = array();
			$non_cash_total = 0;
			$cash_cordoba_raw = 0;
			$cash_dolar_raw = 0;
			// coupons may only be redeemed against the services portion of the invoice, not products; the
			// loyalty discount is subtracted first so a coupon can't validate against services value already
			// given away as a loyalty reward
			$coupon_remaining = max( $services_total_cordobas - $loyalty_discount_cordobas, 0 );

			for( $i=0; $i < count($payment_methods); $i++ ){

				$method = $payment_methods[$i];

				if( $method === 'cupon' ){

					if( $has_discounted_service ){

						$response['type'] = 'error';
						$response['message'] = 'No se pueden aplicar cupones cuando algún servicio de la factura tiene un descuento aplicado';
						echo json_encode($response);
						exit();
					}

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
						$response['message'] = 'Los cupones solo se pueden aplicar sobre servicios, y esta factura no tiene servicios';
						echo json_encode($response);
						exit();
					}

					if( $amount - $coupon_remaining > 0.01 ){

						$response['type'] = 'error';
						$response['message'] = 'El cupón "'.$code.'" excede el total disponible de servicios en la factura (disponible: C$'.number_format($coupon_remaining,2).')';
						echo json_encode($response);
						exit();
					}
					$coupon_remaining -= $amount;

					$non_cash_total += $amount;

				}else if( $method === 'cash' ){

					$cash_cordoba_raw += floatval(str_replace(',', '', $cordobas_inputs[$i]));
					$cash_dolar_raw += floatval(str_replace(',', '', $dolar_inputs[$i]));

				}else{

					$amt = floatval(str_replace(',', '', $cordobas_inputs[$i]));

					if( $dolar_inputs[$i] )
						$amt += floatval(str_replace(',', '', $dolar_inputs[$i])) * floatval($exchange->sell_price);

					$non_cash_total += $amt;
				}
			}

			/* only cash may cover an overpayment (it gives change); POS/transfer/cupon combined can't exceed the total */
			if( $non_cash_total - $remaining_cordobas > 0.01 ){

				$response['type'] = 'error';
				$response['message'] = 'El total de pagos con POS, transferencia y/o cupón no puede exceder el total de la factura. El efectivo debe cubrir cualquier excedente.';
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

				if( !$product->is_service && $product->stock < $quantities[$i] ){

					$response['type'] = 'error';
					$response['message'] = 'Stock insuficiente para "'.$product->name.'" (disponible: '.$product->stock.')';
					echo json_encode($response);
					exit();
				}
			}

			$sale['id_appointment'] = $posted_id_appointment > 0 ? $posted_id_appointment : NULL;

			$this->db->trans_start();
			$id_sale = $this->sales_mdl->saveSale( $sale );

			$accrual_basis_cordobas = max( $services_total_cordobas - $loyalty_discount_cordobas, 0 );

			$this->loyalty_points_mdl->recordSaleMovements(
				$id_sale, $sale['client'], $this->id_admin,
				$claim_tier, $loyalty_tier_threshold, $loyalty_tier_rate,
				$loyalty_balance_before, $accrual_basis_cordobas
			);

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

			if( $appointment_deposit ){

				$payment_to_save[] = array(
					"id_sale" => $id_sale,
					"payment_method" => "transfer",
					"account" => $appointment_deposit->account,
					"cupon_code" => null,
					"cupon_discount_rate" => null,
					"cordoba" => $appointment_deposit->cordoba,
					"dolar" => $appointment_deposit->dolar,
					"tax_rate" => null,
					"change_cordoba_given" => null,
					"change_dollar_given" => null,
					"change_rounding_cordoba" => null,
				);
			}

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
			$this->sale_form( (int) $id_appointment );
		}
	}

	private function printInvoice( $id_sale = 0, $copy = FALSE ){

		$sale = $this->sales_mdl->getSale( $id_sale );
		$details = $this->sales_mdl->getDetails( $id_sale );
		$payment = $this->payments_mdl->getPayment( $id_sale );

		try{

			$logo_path = resolveLogoPath('logo_receipt', 'logo_invoice.png');

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

				/* sales recorded after change_cordoba_given/change_dollar_given were added print the
				   cashier's actual disbursed split; older sales fall back to the theoretical
				   córdoba-vs-dollar-equivalent figure computed the old way */
				if( $pay->change_cordoba_given !== null || $pay->change_dollar_given !== null ){

					$change_cordobas = floatval($pay->change_cordoba_given);
					$change_dollars = floatval($pay->change_dollar_given);

					if( $change_cordobas > 0 || $change_dollars > 0 ){
						$printer->text("Cambio: ".str_pad("C$".number_format($change_cordobas,2)." + $".number_format($change_dollars,2)."\n",15,' ',STR_PAD_LEFT) );
					}
				}else{

					$change_cordobas = $total_cordobas - $_total_cor;
					$change_dollars = $change_cordobas / $sale->dollar_ex_buy;

					$printer->text("Cambio: ".str_pad("$".number_format($change_dollars,2)." (C$".number_format($change_cordobas,2).")\n",15,' ',STR_PAD_LEFT) );
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

	public function sale_form( $id_appointment = 0 ){

		$this->load->model('settings_mdl');

		$data['employees'] = $this->db->query('select * from employees')->result();
		$data['exchange'] = $this->db->get('dollar_exchange')->row();
		$data['tk'] = generateToken();
		$this->session->set_userdata('tk', $data['tk']);
		$this->content_data = $data;
		$this->content_data['accounts'] = $this->bank_account_mdl->getAccounts();
		$this->content_data['prefill'] = $this->buildAppointmentPrefill( (int) $id_appointment, $data['exchange'] );
		$this->content_data['vip_discount_rate'] = $this->settings_mdl->get('vip_discount_rate', 15);
		$this->content_data['birthday_discount_rate'] = $this->settings_mdl->get('birthday_discount_rate', 50);
		$this->renderPanel('form_sales');
	}

	private function buildAppointmentPrefill( $id_appointment, $exchange ){

		if( $id_appointment <= 0 ){

			return NULL;
		}

		$appointment = $this->appointments_mdl->getAppointment( $id_appointment );

		if( !$appointment || $this->appointments_mdl->isInvoiced( $id_appointment ) ){

			return NULL;
		}

		$client = $this->db->get_where('clients', array( 'id_client' => $appointment->id_client ))->row();

		if( $client && !empty($client->birth_client) ){
			$client->is_birthday_today = ( date('m-d', strtotime($client->birth_client)) === date('m-d') ) ? 1 : 0;
		}

		$sql = 'select p.id_product, p.barcode, p.name, p.price_sell, p.is_service, p.commisssion_rate,
		               sc.discount_rate as category_discount_rate
		        from appointment_services aps
		        join products p on p.id_product = aps.id_product
		        left join service_category sc on sc.id_service_category = p.id_service_category
		        where aps.id_appointment = '.(int) $id_appointment;

		$products = $this->db->query( $sql )->result();

		$deposit = $this->appointment_payments_mdl->getForAppointment( $id_appointment );
		$deposit_credit_cordobas = 0;
		$deposit_display = NULL;

		if( $deposit ){

			$deposit_credit_cordobas = floatval($deposit->cordoba) + ( floatval($deposit->dolar) * $exchange->sell_price );
			$deposit_display = 'C$'.number_format($deposit->cordoba,2).' / $'.number_format($deposit->dolar,2);
		}

		return array(
			'id_appointment' => $id_appointment,
			'client' => $client,
			'products' => $products,
			'deposit_display' => $deposit_display,
			'deposit_credit_cordobas' => $deposit_credit_cordobas,
		);
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