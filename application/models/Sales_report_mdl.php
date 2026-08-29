<?php if( !defined('BASEPATH') ){ exit(); }

class Sales_report_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
		$this->load->model('payments_mdl');
	}

	/**
	 * One row per sale_products line item for every sale whose date_time falls within
	 * [date_start, date_end] (inclusive of both edges). Mirrors the commission/real-profit
	 * math in Employees_mdl::getSaleDetail(), but applied to every line (products and
	 * services alike) instead of aggregated per invoice for a single employee.
	 *
	 * "Utilidad bruta" here is what Employees_mdl calls "ganancia real" — gross córdoba
	 * revenue net of POS-fee/coupon-discount losses, before commission is deducted.
	 * "Ganancia real" in this report's output is utilidad_bruta - comision, i.e. the
	 * business's actual take-home profit per line after paying out the commission.
	 */
	public function getDetailedSales( $date_start, $date_end ){

		$date_start = date('Y-m-d', strtotime($date_start));
		$date_end = date('Y-m-d', strtotime($date_end));

		$sql = "SELECT S.id_sale, S.date_time, S.dollar_ex_sell, S.id_employee AS sale_employee,
			C.name_client, C.surname_client
			FROM sales S
			LEFT JOIN clients C ON C.id_client = S.client
			WHERE DATE(S.date_time) BETWEEN ? AND ?
			ORDER BY S.date_time ASC";

		$sales = $this->db->query( $sql, array( $date_start, $date_end ) )->result();

		$employees = array();
		foreach( $this->db->select('id_employee, name')->get('employees')->result() as $e ){
			$employees[$e->id_employee] = $e->name;
		}

		$rows = array();

		foreach( $sales as $s ){

			$products = $this->db->query(
				"SELECT SP.id_product, SP.quantity, SP.price, SP.discount, SP.commission_rate, SP.id_employee,
				P.name AS product_name, P.is_service
				FROM sale_products SP
				INNER JOIN products P ON P.id_product = SP.id_product
				WHERE SP.id_sale = ?", array( $s->id_sale )
			)->result();

			if( empty($products) ) continue;

			$sell_rate = floatval($s->dollar_ex_sell);

			$total_usd = 0;
			foreach( $products as $p ){
				$sold = $p->price * $p->quantity;
				$total_usd += $sold - ($sold * ($p->discount/100));
			}

			$gross_cordobas = $total_usd * $sell_rate;

			$sale_payments = $this->payments_mdl->getPayment($s->id_sale);
			$ratio = saleRealProfitRatio( $sale_payments, $sell_rate, $gross_cordobas );

			$client_name = trim( ($s->name_client ?? '') . ' ' . ($s->surname_client ?? '') );
			if( $client_name === '' ) $client_name = 'Consumidor final';

			foreach( $products as $p ){

				$sold = $p->price * $p->quantity;
				$discounted = $sold - ($sold * ($p->discount/100));

				$effective_employee_id = $p->id_employee ? $p->id_employee : $s->sale_employee;
				$effective_employee = isset($employees[$effective_employee_id]) ? $employees[$effective_employee_id] : '';

				$venta = $discounted * $sell_rate;
				$utilidad_bruta = $venta * $ratio;

				$comision = 0;
				if( !is_null($p->commission_rate) ){
					$cr = floatval($p->commission_rate) / 100;
					if( $cr > 0.5 ) $cr = 0.5;
					$comision = $discounted * $cr * $sell_rate * $ratio;
				}

				$ganancia_real = $utilidad_bruta - $comision;

				$rows[] = array(
					'id_sale' => (int) $s->id_sale,
					'date_time' => $s->date_time,
					'client' => $client_name,
					'product' => $p->product_name,
					'is_service' => (bool) $p->is_service,
					'quantity' => (int) $p->quantity,
					'price' => round( floatval($p->price), 2 ),
					'discount' => (int) $p->discount,
					'employee' => $effective_employee,
					'venta' => round( $venta, 2 ),
					'utilidad_bruta' => round( $utilidad_bruta, 2 ),
					'comision' => round( $comision, 2 ),
					'ganancia_real' => round( $ganancia_real, 2 ),
				);
			}
		}

		return $rows;
	}
}
