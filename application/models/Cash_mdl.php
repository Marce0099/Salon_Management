<?php if( !defined('BASEPATH') ){ exit(); }

class Cash_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getDayCash(){

		return $this->db->query('select * from cash where date(open_date_time) = date(CURRENT_TIMESTAMP)')->row();
	} 

	public function getOpenedCash(){

		return $this->db->query('select * from cash where date(open_date_time) = date(CURRENT_TIMESTAMP) and close_date_time IS NULL')->row();
	}

	public function getCashMovements( $cash = NULL ){

		if( !$cash )
			$date = 'CURRENT_TIMESTAMP';
		else
			$date = '"'.$cash->open_date_time.'"';

		$data['sales_cash_num'] = 0;
		$data['sales_pos_num'] = 0;
		$data['sales_transfer_num'] = 0;
		$data['sales_cupon_num'] = 0;
		$data['sales_total_cordobas'] = 0;
		$data['sales_total_dollar'] = 0;
		$data['sales_total_pos'] = 0;
		$data['sales_total_transfer'] = 0;
		$data['sales_total_cupon'] = 0;
		$data['sales_total_change_rounding'] = 0;
		$data['sales_num_total'] = 0;

		$query = "SELECT 
		spay.payment_method,
		COUNT(spay.payment_method) frequency
		FROM `sale_payments` spay 
		INNER JOIN sales s on s.id_sale=spay.id_sale
		WHERE date(s.date_time) = date({$date})
		GROUP BY spay.payment_method;";

		$numSales = $this->db->query( $query )->result();
		foreach($numSales as $pay){
			$data['sales_'. $pay->payment_method .'_num'] += $pay->frequency;
		}

		if( !$cash )
			$current = $this->getOpenedCash();
		else
			$current = $cash;

		if( !$current )
			return FALSE;

		$data['current'] = $current;

		//sales
		$sales = $this->db->query( 'select * from sales S where date(date_time) = date('.$date.')' )->result();

		if( $sales ){

			$data['sales_num_total'] = count($sales);
			//$data['sales_pos_num'] = $this->db->query( 'select count(S.id_sale) as num_sales from sales S where date(date_time) = date('.$date.') and S.pos_paid = 1' )->row('num_sales');
			//$data['sales_cash_num'] = count($sales) - $data['sales_pos_num'];

			foreach( $sales as &$s ){

				$sql = 'select P.is_service,SP.*,S.* from sale_products SP inner join sales S on
				SP.id_sale = S.id_sale
				inner join products P on SP.id_product = P.id_product
				where S.id_sale = '.$s->id_sale;

				$sales_products = $this->db->query($sql)->result();
				$sale_total = 0;

				foreach( $sales_products as $sp ){

					$subtotal = $sp->quantity * $sp->price;

					if( $sp->discount )
						$subtotal -= $subtotal * $sp->discount / 100;

					$sale_total += $subtotal;
				}

				$s->sale_total = $sale_total;

				$sql = "SELECT SPA.* FROM sale_payments SPA WHERE id_sale=";
				$sql .= "'" . $s->id_sale . "'";

				$sales_payment = $this->db->query($sql)->result();

				$_sale_total = $sale_total * $s->dollar_ex_sell; //C$

				$cash_cordoba = 0;
				$cash_dolar = 0;
				$has_cash = FALSE;
				$change_cordoba_given = NULL;
				$change_dollar_given = NULL;

				foreach( $sales_payment as $spa){

					if( $spa->payment_method === "cash" ){

						$has_cash = TRUE;
						$cash_cordoba += floatval($spa->cordoba);
						$cash_dolar += floatval($spa->dolar);
						$change_cordoba_given = $spa->change_cordoba_given;
						$change_dollar_given = $spa->change_dollar_given;
						$data['sales_total_change_rounding'] += floatval($spa->change_rounding_cordoba ?? 0);

					}else if( $spa->payment_method === 'cupon' ){

						// tendered amount still covers the invoice; the reported total is net of the coupon's discount rate (real revenue, not tendered)
						$cor = floatval($spa->cordoba ?? 0);
						$discount = floatval($spa->cupon_discount_rate ?? 0);

						$data['sales_total_cupon'] += round($cor * (1 - $discount / 100), 2);
						$_sale_total -= $cor;

					}else if( $spa->payment_method === 'pos' ){

						// bank charges a commission on POS payments; the business absorbs it, so
						// recognized income is net of tax_rate even though the full tendered amount
						// covers the invoice and is what the customer paid
						$cor = floatval($spa->cordoba ?? 0);
						$dol = floatval($spa->dolar ?? 0) * $s->dollar_ex_sell;
						$tendered = $cor + $dol;
						$tax_rate = floatval($spa->tax_rate ?? 0);

						$data['sales_total_pos'] += round($tendered * (1 - $tax_rate / 100), 2);
						$_sale_total -= $tendered;

					}else{
						$cor = $spa->cordoba ?? 0;
						$dol = $spa->dolar ?? 0;
						$dol *= $s->dollar_ex_sell; // $ to C$

						$added = $cor + $dol;
						$data['sales_total_' . $spa->payment_method] += $added;
						$_sale_total -= $added;
					}

				}

				if( $has_cash ){

					$applied = resolveCashApplied( $_sale_total, $cash_cordoba, $cash_dolar, $s->dollar_ex_sell, $change_cordoba_given, $change_dollar_given );

					$data['sales_total_dollar'] += $applied['applied_dollar'];
					$data['sales_total_cordobas'] += $applied['applied_cordoba'];
				}

				/*if( !$s->pos_paid ){

					if( $s->cash_cordobas && !$s->cash_dollar ){

						$st_cor = round( $sale_total * $s->dollar_ex_buy );
						$change = $s->cash_cordobas - $st_cor;
						$data['sales_total_cordobas'] += $s->cash_cordobas - $change;
					}
					else if( $s->cash_dollar && !$s->cash_cordobas ){

						$change = round( $s->cash_dollar * $s->dollar_ex_buy ) - round( $sale_total * $s->dollar_ex_buy );
						$data['sales_total_dollar'] += $s->cash_dollar;

						$data['sales_total_cordobas'] -= $change;
					}
					else{

						$cor = round( $s->cash_dollar * $s->dollar_ex_buy );
						$cor += $s->cash_cordobas;
						$change = $cor - round( $sale_total * $s->dollar_ex_buy );
						$data['sales_total_dollar'] += $s->cash_dollar;
						$data['sales_total_cordobas'] += $s->cash_cordobas - $change;
					}
				}
				else
					$data['sales_total_pos'] += $sale_total * $s->dollar_ex_buy;*/
			}
		}

		$data['sales'] = $sales;

		//cancelled appointments' forfeited deposits (no-refund policy; never became a sale)
		$exchange = $this->db->get('dollar_exchange')->row();

		$data['cancelled_deposits'] = $this->db->query(
			'select a.id_appointment, a.cancelled_at, c.name_client, c.surname_client,
			        ap.account, ba.name_account, ap.cordoba, ap.dolar
			 from appointments a
			 inner join appointment_payments ap on ap.id_appointment = a.id_appointment
			 inner join clients c on c.id_client = a.id_client
			 left join bank_account ba on ba.id_account = ap.account
			 where a.active = 0
			 and date(a.cancelled_at) = date('.$date.')'
		)->result();

		$data['cancelled_deposits_total'] = 0;

		foreach( $data['cancelled_deposits'] as $cd ){

			$total_cor = floatval($cd->cordoba) + ( floatval($cd->dolar) * $exchange->sell_price );
			$cd->total_cordobas = $total_cor;

			$data['cancelled_deposits_total'] += $total_cor;
		}

		//outcomes
		$data['outcomes'] = $this->db->query('select O.*,A.name from outcomes O inner join admins A on O.id_admin = A.id_admin where date(O.date_time) = date('.$date.')')->result();

		$outcomes_total = 0;

		foreach( $data['outcomes'] as $o )
			$outcomes_total += $o->quantity;

		$data['outcomes_total'] = $outcomes_total;

		return $data;
	}

	public function save_open( $data ){

		// a second concurrent "abrir caja" for the same day is rejected by the uniq_open_date
		// constraint; on this PHP/PDO version (default PDO::ERRMODE_EXCEPTION since PHP 8.1) that
		// surfaces as a normal catchable PDOException, which Cash::open()'s existing
		// catch (\Throwable $th) around this call already handles
		$this->db->insert( 'cash', $data );
	}

	public function save_outcome( $data ){

		$this->db->insert('outcomes',$data);
	}

	public function save_close( $data ){

		$cash = $this->getOpenedCash();

		$this->db->update('cash',$data,array( 'id_cash' => $cash->id_cash ));
	}

	public function getCash($id_cash){

		return $this->db->get_where('cash',array('id_cash'=>$id_cash))->row();
	}
}
