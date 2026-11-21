<?php if( !defined('BASEPATH') ){ exit(); }

class Statistics_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getSalesTrend(){

		$query = 'SELECT
		YEAR(S.date_time) AS Año,
		MONTH(S.date_time) AS Mes,
		MIN(DATE(S.date_time)) AS Fecha,
		SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS "Ventas"
		FROM sales as S
		INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
		GROUP BY Año, Mes
		ORDER BY Año DESC, Mes DESC;';

		return $this->db->query($query)->result();
	}

	public function getTopSold($limit = 10){

		$query = 'SELECT
		SUM(SP.quantity) AS units_sold,
        P.barcode,
		P.name
		FROM sales as S
		INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
        INNER JOIN products AS P on P.id_product=SP.id_product
        GROUP BY P.barcode, P.name
        ORDER BY units_sold DESC, P.name ASC
        LIMIT '.(int)$limit.';';

		return $this->db->query($query)->result();
	}

	public function getGoalsVsActual(){

		$query = 'SELECT
		g.year_goal AS Año,
		g.month_goal AS Mes,
		g.amount AS Meta,
		IFNULL(prof.Ventas, 0) AS Ventas
		FROM goals g
		LEFT JOIN (
			SELECT YEAR(S.date_time) AS Año, MONTH(S.date_time) AS Mes,
			SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS Ventas
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
			GROUP BY Año, Mes
		) prof ON prof.Año = g.year_goal AND prof.Mes = g.month_goal
		ORDER BY g.year_goal DESC, g.month_goal DESC;';

		return $this->db->query($query)->result();
	}

	/**
	 * Monthly revenue grouped by payment method, net of each method's own
	 * revenue-recognition rule (mirrors Cash_mdl::getCashMovements()):
	 * cupon rows are net of their discount rate, pos rows are net of tax_rate.
	 */
	public function getRevenueByPaymentMethod(){

		$query = 'SELECT
		YEAR(S.date_time) AS Año,
		MONTH(S.date_time) AS Mes,
		SPAY.payment_method AS Metodo,
		SUM(
			CASE SPAY.payment_method
				WHEN "cupon" THEN SPAY.cordoba * (1 - IFNULL(SPAY.cupon_discount_rate,0) / 100)
				WHEN "pos" THEN (SPAY.cordoba + SPAY.dolar * S.dollar_ex_sell) * (1 - IFNULL(SPAY.tax_rate,0) / 100)
				ELSE SPAY.cordoba + SPAY.dolar * S.dollar_ex_sell
			END
		) AS Ingresos
		FROM sale_payments SPAY
		INNER JOIN sales S ON S.id_sale = SPAY.id_sale
		WHERE S.date_time >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH)
		GROUP BY Año, Mes, Metodo
		ORDER BY Año ASC, Mes ASC, Metodo ASC;';

		return $this->db->query($query)->result();
	}

	/**
	 * Net revenue per employee (all-time), same revenue formula as getSalesTrend().
	 */
	public function getSalesByEmployee($limit = 10){

		$query = 'SELECT
		E.id_employee,
		E.name,
		SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS Ventas
		FROM sales as S
		INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
		INNER JOIN employees as E on E.id_employee = S.id_employee
		GROUP BY E.id_employee, E.name
		ORDER BY Ventas DESC, E.name ASC
		LIMIT '.(int)$limit.';';

		return $this->db->query($query)->result();
	}

	public function getNewClientsTrend(){

		$query = 'SELECT
		YEAR(registered_client) AS Año,
		MONTH(registered_client) AS Mes,
		MIN(DATE(registered_client)) AS Fecha,
		COUNT(*) AS Clientes
		FROM clients
		GROUP BY Año, Mes
		ORDER BY Año DESC, Mes DESC;';

		return $this->db->query($query)->result();
	}

	/**
	 * Current month's goal amount (if one has been set) vs. actual net sales
	 * for the same month. Returns amount = NULL when no goals row exists yet.
	 */
	public function getCurrentMonthGoalProgress(){

		$query = 'SELECT
		g.amount AS Meta,
		IFNULL(prof.Ventas, 0) AS Ventas
		FROM (SELECT MONTH(CURRENT_DATE()) AS Mes, YEAR(CURRENT_DATE()) AS Año) cur
		LEFT JOIN goals g ON g.month_goal = cur.Mes AND g.year_goal = cur.Año
		LEFT JOIN (
			SELECT YEAR(S.date_time) AS Año, MONTH(S.date_time) AS Mes,
			SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS Ventas
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
			WHERE YEAR(S.date_time) = YEAR(CURRENT_DATE()) AND MONTH(S.date_time) = MONTH(CURRENT_DATE())
			GROUP BY Año, Mes
		) prof ON 1=1;';

		$row = $this->db->query($query)->row();

		$amount = $row && $row->Meta !== NULL ? (float)$row->Meta : NULL;
		$sales = $row ? (float)$row->Ventas : 0;

		return array(
			'amount' => $amount,
			'sales' => $sales,
			'percent' => $amount && $amount > 0 ? round(($sales / $amount) * 100, 1) : NULL
		);
	}
}
