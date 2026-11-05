<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Restaurant reports. Product prices and tip/IVA are stored in dollars; each sale keeps its own sell rate, so every
 * figure is reported both in $ and in C$ (C$ = $ x the sale's rate, summed per sale).
 * "Ventas" = sale lines only; propina and IVA are shown separately; "total" = ventas + propina + IVA.
 */
class Reports_mdl extends CI_Model{

	private function money(){

		return array('usd' => 0.0, 'cor' => 0.0);
	}

	private function blank(){

		return array('sales' => 0, 'ventas_usd' => 0.0, 'ventas_cor' => 0.0, 'tip_usd' => 0.0, 'tip_cor' => 0.0, 'iva_usd' => 0.0, 'iva_cor' => 0.0, 'total_usd' => 0.0, 'total_cor' => 0.0);
	}

	private function add(&$acc, $s){

		$r = (float) $s->r;
		$acc['sales']++;
		$acc['ventas_usd'] += $s->lines_total;
		$acc['ventas_cor'] += $s->lines_total * $r;
		$acc['tip_usd']    += $s->tip;
		$acc['tip_cor']    += $s->tip * $r;
		$acc['iva_usd']    += $s->iva;
		$acc['iva_cor']    += $s->iva * $r;
		$acc['total_usd']  += $s->lines_total + $s->tip + $s->iva;
		$acc['total_cor']  += ($s->lines_total + $s->tip + $s->iva) * $r;
	}

	/** One row per sale in the range, with waiter and table names. */
	private function sales($from, $to){

		$sql = 'SELECT s.id_sale, s.date_time, s.dollar_ex_sell AS r, s.tip, s.iva, s.id_bill, s.id_employee,
				e.name AS waiter, d.label AS table_label,
				IFNULL((SELECT SUM(sp.quantity * sp.price * (1 - sp.discount / 100)) FROM sale_products sp WHERE sp.id_sale = s.id_sale), 0) AS lines_total
			FROM sales s
			LEFT JOIN employees e ON e.id_employee = s.id_employee
			LEFT JOIN bills b ON b.id_bill = s.id_bill
			LEFT JOIN tabs t ON t.id_tab = b.id_tab
			LEFT JOIN dining_tables d ON d.id_table = t.id_table
			WHERE DATE(s.date_time) BETWEEN ? AND ?
			ORDER BY s.date_time';

		$rows = $this->db->query($sql, array($from, $to))->result();

		foreach( $rows as $r ){
			$r->lines_total = (float) $r->lines_total; $r->tip = (float) $r->tip; $r->iva = (float) $r->iva;
		}

		return $rows;
	}

	public function shiftOf($datetime){

		$h = (int) date('G', strtotime($datetime));
		return $h < 12 ? 'Mañana (antes de 12:00)' : ( $h < 18 ? 'Tarde (12:00 - 17:59)' : 'Noche (desde 18:00)' );
	}

	/** Everything the reports page needs for [from, to] (Y-m-d). */
	public function report($from, $to){

		$sales = $this->sales($from, $to);

		$summary  = $this->blank();
		$waiters  = array();
		$tables   = array();
		$shifts   = array('Mañana (antes de 12:00)' => $this->blank(), 'Tarde (12:00 - 17:59)' => $this->blank(), 'Noche (desde 18:00)' => $this->blank());

		foreach( $sales as $s ){

			$this->add($summary, $s);

			$w = $s->waiter ?: 'Sin mesero';
			if( !isset($waiters[$w]) ){ $waiters[$w] = $this->blank(); }
			$this->add($waiters[$w], $s);

			$t = $s->table_label ? 'Mesa '.$s->table_label : 'Sin mesa (mostrador)';
			if( !isset($tables[$t]) ){ $tables[$t] = $this->blank(); }
			$this->add($tables[$t], $s);

			$this->add($shifts[$this->shiftOf($s->date_time)], $s);
		}

		$cmp = function($a, $b){ return $b['total_cor'] <=> $a['total_cor']; };
		uasort($waiters, $cmp);
		uasort($tables, $cmp);

		$categories = $this->db->query('SELECT IFNULL(c.name_category, "Sin categoría") AS cat_name, SUM(sp.quantity) AS qty,
				SUM(sp.quantity * sp.price * (1 - sp.discount / 100)) AS usd,
				SUM(sp.quantity * sp.price * (1 - sp.discount / 100) * s.dollar_ex_sell) AS cor
			FROM sale_products sp
			JOIN sales s ON s.id_sale = sp.id_sale
			JOIN products p ON p.id_product = sp.id_product
			LEFT JOIN service_category c ON c.id_service_category = p.id_service_category
			WHERE DATE(s.date_time) BETWEEN ? AND ? AND IFNULL(p.barcode, "") <> "SPLIT-CONSUMO"
			GROUP BY cat_name ORDER BY cor DESC', array($from, $to))->result();

		$top = $this->db->query('SELECT p.name, SUM(sp.quantity) AS qty,
				SUM(sp.quantity * sp.price * (1 - sp.discount / 100) * s.dollar_ex_sell) AS cor
			FROM sale_products sp
			JOIN sales s ON s.id_sale = sp.id_sale
			JOIN products p ON p.id_product = sp.id_product
			WHERE DATE(s.date_time) BETWEEN ? AND ? AND IFNULL(p.barcode, "") <> "SPLIT-CONSUMO"
			GROUP BY p.id_product, p.name ORDER BY qty DESC, cor DESC LIMIT 10', array($from, $to))->result();

		$voided = $this->db->query('SELECT COUNT(*) AS n, IFNULL(SUM(oi.quantity * oi.unit_price), 0) AS usd
			FROM order_items oi JOIN orders_kitchen o ON o.id_order = oi.id_order
			WHERE oi.status = "void" AND DATE(o.created_at) BETWEEN ? AND ?', array($from, $to))->row();

		return array(
			'summary' => $summary, 'waiters' => $waiters, 'tables' => $tables, 'shifts' => $shifts,
			'categories' => $categories, 'top' => $top, 'voided' => $voided, 'tickets' => $this->tickets($from, $to),
		);
	}

	/** Kitchen ticket history for the range. */
	public function tickets($from, $to){

		$sql = 'SELECT o.id_order, o.created_at, o.print_status, o.print_error, d.label AS table_label, e.name AS waiter,
				(SELECT COUNT(*) FROM order_items i WHERE i.id_order = o.id_order AND i.status = "active") AS items,
				(SELECT COUNT(*) FROM order_items i WHERE i.id_order = o.id_order AND i.status = "void") AS voids
			FROM orders_kitchen o
			JOIN tabs t ON t.id_tab = o.id_tab
			JOIN dining_tables d ON d.id_table = t.id_table
			LEFT JOIN employees e ON e.id_employee = t.id_employee
			WHERE DATE(o.created_at) BETWEEN ? AND ?
			ORDER BY o.id_order DESC LIMIT 300';

		return $this->db->query($sql, array($from, $to))->result();
	}

	/** Live restaurant status for the dashboard. */
	public function live(){

		$tables = (int) $this->db->where('active', 1)->count_all_results('dining_tables');
		$open   = (int) $this->db->query('SELECT COUNT(DISTINCT t.id_table) AS n FROM tabs t JOIN dining_tables d ON d.id_table = t.id_table AND d.active = 1 WHERE t.status = "open"')->row()->n;

		$consumption = $this->db->query('SELECT IFNULL(SUM(oi.quantity * oi.unit_price), 0) AS usd FROM order_items oi
			JOIN tabs t ON t.id_tab = oi.id_tab AND t.status = "open" WHERE oi.status = "active"')->row()->usd;

		$reservations = (int) $this->db->query('SELECT COUNT(*) AS n FROM table_reservations WHERE status = "active" AND DATE(start_at) = CURDATE() AND end_at > NOW()')->row()->n;

		return array('tables' => $tables, 'occupied' => $open, 'consumption_usd' => (float) $consumption, 'consumption_cor' => (float) $consumption * (float) $this->db->get('dollar_exchange')->row()->sell_price, 'reservations' => $reservations);
	}
}
