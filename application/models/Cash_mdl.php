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
		$data['sales_total_cordobas'] = 0;
		$data['sales_total_dollar'] = 0;
		$data['sales_total_pos'] = 0;
		$data['sales_total_transfer'] = 0;
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

				foreach( $sales_products as $s ){

					$subtotal = $s->quantity * $s->price;

					if( $s->discount )
						$subtotal -= $subtotal * $s->discount / 100;

					$sale_total += $subtotal;
				}

				$s->sale_total = $sale_total;

				$sql = "SELECT SPA.* FROM sale_payments SPA WHERE id_sale=";
				$sql .= "'" . $s->id_sale . "'";

				$sales_payment = $this->db->query($sql)->result();

				$num_payments = count($sales_payment);

				$_sale_total = round($sale_total * $s->dollar_ex_sell); //C$

				$recalc = array();

				foreach( $sales_payment as $spa){

					if( $spa->payment_method === "cash" ){

						if( $spa->cordoba && !$spa->dolar ){
	
							if($num_payments == 1){
								$change = $spa->cordoba - $_sale_total;
								$added = $spa->cordoba - $change;
								$data['sales_total_cordobas'] += $added;
	
								$_sale_total -= $added;
							}else{
								$recalc['case'] = 1;
								$recalc['cor'] = $spa->cordoba;
							}
						}
						else if( $spa->dolar && !$spa->cordoba ){
	
							$cor = round( $spa->dolar * $s->dollar_ex_buy );
							if($num_payments == 1){
								$change = $cor - $_sale_total;
								$data['sales_total_dollar'] += $spa->dolar;
		
								$data['sales_total_cordobas'] -= $change;
	
								$_sale_total -= $cor;
							}else{
								$recalc['case'] = 2;
								$recalc['cor'] = $cor;
								$recalc['dol'] = $spa->dolar;
							}
						}
						else{
	
							$dol_to_cor = round( $spa->dolar * $s->dollar_ex_buy );
							$cor = $dol_to_cor + $spa->cordoba;

							if($num_payments == 1){
								$change = $cor - $_sale_total;
								$data['sales_total_dollar'] += $spa->dolar;
	
								$tcor = $spa->cordoba - $change;
								$data['sales_total_cordobas'] += $tcor;
								$_sale_total -= ($dol_to_cor + $tcor);
							}else{
								$recalc['case'] = 3;
								$recalc['cor'] = $cor;
								$recalc['dol'] = $spa->dolar;
								$recalc['spacor'] = $spa->cordoba;
							}
						}
					}else{
						$cor = $spa->cordoba ?? 0;
						$dol = $spa->dolar ?? 0;
						$dol *= $s->dollar_ex_buy; // $ to C$

						$added = $cor + $dol;
						$data['sales_total_' . $spa->payment_method] += $added;
						$_sale_total -= $added;
					}

				}

				if( count($recalc) >0 ){
					switch ($recalc['case']) {
						case 1:
							$change = $recalc['cor'] - $_sale_total;
							$added = $recalc['cor'] - $change;
							$data['sales_total_cordobas'] += $added;
							break;
						case 2:
							$change = $recalc['cor'] - $_sale_total;
							$data['sales_total_dollar'] += $recalc['dol'];
	
							$data['sales_total_cordobas'] -= $change;
							break;
						case 3:
							$change = $recalc['cor'] - $_sale_total;
							$data['sales_total_dollar'] += $recalc['dol'];

							$tcor = $recalc['spacor'] - $change;
							$data['sales_total_cordobas'] += $tcor;
							break;
						
						default:
							# code...
							break;
					}
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

		//outcomes
		$data['outcomes'] = $this->db->query('select O.*,A.name from outcomes O inner join admins A on O.id_admin = A.id_admin where date(O.date_time) = date('.$date.')')->result();

		$outcomes_total = 0;

		foreach( $data['outcomes'] as $o )
			$outcomes_total += $o->quantity;

		$data['outcomes_total'] = $outcomes_total;

		return $data;
	}

	public function save_open( $data ){

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
