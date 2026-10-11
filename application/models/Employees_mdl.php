<?php if( !defined('BASEPATH') ){ exit(); }

class Employees_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
		$this->load->model('payments_mdl');
	}

	public function save( $data ){

		$this->db->insert( 'employees', $data );

		return sprintf('%03d', $this->db->insert_id() );
	}

	public function getEmployee( $id_employee ){

		return $this->db->get_where( 'employees', array( 'id_employee' => $id_employee ) )->row();
	}

	public function edit( $id_employee, $data ){

		$this->db->update('employees',$data,array( 'id_employee' => $id_employee ));
	}

	public function delete($id_employee){

		$this->db->where('id_employee', $id_employee);
		$this->db->update('employees', ["active" => 0]);

		//$this->db->delete( 'employees', array( 'id_employee' => $id_employee ) );
	}

	public function getAll(){

		return $this->db->get('employees')->result();
	}

	public function getActiveEmployees(){

		$sql = 'select id_employee, name from employees where active = 1 order by name asc';

		return $this->db->query( $sql )->result();
	}

	public function getSales( $id_employee, $date_start, $date_end ){

		$date_start = date('Y-m-d',strtotime($date_start));
		$date_end = date('Y-m-d',strtotime($date_end));

		// an invoice belongs to this employee's report either as its main Vendedor (legacy behavior,
		// and the fallback attribution for lines with no explicit servicing employee) or because they
		// actually served at least one line on it via sale_products.id_employee
		$sql = 'select distinct S.* from sales S
		left join sale_products SP on SP.id_sale = S.id_sale
		where ( S.id_employee = ? or SP.id_employee = ? ) and
		date(S.date_time) between ? and ?
			order by S.date_time, S.id_sale';

		$data['sales'] = $this->db->query($sql, array( (int) $id_employee, (int) $id_employee, $date_start, $date_end ))->result();

		/*foreach( $data['sales'] as $s ){

			$sql = 'select SP.*,P.barcode,P.name,P.is_service from sale_products SP inner join products P on SP.id_product = P.id_product where SP.id_sale = '.$s->id_sale;

			$products = $this->db->query($sql)->result();

			$sale_total = 0;
			$service_total = 0;

			foreach( $products as $p ){

				if( !$p->is_service )
					$sale_total += $p->price * $p->quantity;
				else
					$service_total += $p->price * $p->quantity;
			}

			$s->sale_total = $sale_total;
			$s->service_total = $service_total;
		}*/

		return $data['sales'];
	}

	public function getSaleDetail( $id_sale, $id_employee = NULL ){

		$data = array(
			'totalSale' => 0,
			'commission' => 0,
			'commissionUsd' => 0,
			'realProfit' => 0
		);

		$sale_row = $this->db->query("SELECT id_employee, dollar_ex_sell FROM `sales` WHERE id_sale='{$id_sale}';")->row();
		$sale_employee = $sale_row ? $sale_row->id_employee : NULL;

		$sql = "SELECT * FROM `sale_products` WHERE id_sale='{$id_sale}';";
		$products = $this->db->query($sql)->result();

		$total_usd = 0;
		$commission_gross_usd = 0;

		foreach($products as $p){
			$sold = $p->price * $p->quantity;
			$discounted = $sold - ($sold * ($p->discount/100));

			// a line's servicing employee is whoever actually performed it; lines that predate this
			// feature (or product lines, which never carry it) fall back to the invoice's Vendedor
			$effective_employee = $p->id_employee ? $p->id_employee : $sale_employee;

			if(!is_null($p->commission_rate) && ( is_null($id_employee) || $effective_employee == $id_employee )){
				$cr = floatval($p->commission_rate) / 100;

				// commission rate can never exceed 50%, regardless of configured rate
				if($cr > 0.5){
					$cr = 0.5;
				}

				$commission_gross_usd += $discounted * $cr;
			}

			$total_usd += $discounted;

		}

		// córdoba conversion uses this sale's own dollar_ex_sell snapshot — the rate
		// every other córdoba conversion in the app uses (invoices, cash-drawer revenue,
		// receipts), not today's live dollar_exchange rate
		$sell_rate = $sale_row ? floatval($sale_row->dollar_ex_sell) : 0;

		$gross_cordobas = $total_usd * $sell_rate;

		// commission is paid on real profit (net of POS bank-commission / coupon-discount
		// absorption), not the raw gross invoice value — mirrors Cash_mdl::getCashMovements()
		$sale_payments = $this->payments_mdl->getPayment($id_sale);
		$real_profit_ratio = saleRealProfitRatio( $sale_payments, $sell_rate, $gross_cordobas );

		$data['totalSale'] = round( $gross_cordobas, 2 );
		$data['realProfit'] = round( $gross_cordobas * $real_profit_ratio, 2 );
		$data['commissionUsd'] = round( $commission_gross_usd * $real_profit_ratio, 2 );
		$data['commission'] = round( $commission_gross_usd * $sell_rate * $real_profit_ratio, 2 );

		return $data;
	}
}