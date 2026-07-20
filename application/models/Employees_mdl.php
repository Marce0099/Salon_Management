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

		$sql = 'select S.* from sales S where id_employee = '.$id_employee.' and
		date(date_time) between "'.$date_start.'" and "'.$date_end.'"';

		$data['sales'] = $this->db->query($sql)->result();

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

	public function getSaleDetail( $id_sale ){

		$data = array(
			'totalSale' => 0,
			'commission' => 0,
			'commissionUsd' => 0,
			'realProfit' => 0
		);

		$sql = "SELECT * FROM `sale_products` WHERE id_sale='{$id_sale}';";
		$products = $this->db->query($sql)->result();

		$total_usd = 0;
		$commission_gross_usd = 0;

		foreach($products as $p){
			$sold = $p->price * $p->quantity;
			$discounted = $sold - ($sold * ($p->discount/100));

			if(!is_null($p->commission_rate)){
				$cr = floatval($p->commission_rate) / 100;

				// employee used their own product for this service: settled commission gets a 10% bump
				if($p->my_product){
					$cr *= 1.10;
				}

				// commission rate can never exceed 50%, regardless of configured rate or bonus
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
		$sale_row = $this->db->query("SELECT dollar_ex_sell FROM `sales` WHERE id_sale='{$id_sale}';")->row();
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