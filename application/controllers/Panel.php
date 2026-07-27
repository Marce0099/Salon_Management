<?php
	if( !defined('BASEPATH') ){ exit(); }

	class Panel extends Admin_Controller{

		public function __construct(){
			parent::__construct();

			$this->load->model('cash_mdl');
			$this->load->model('products_mdl');
			$this->load->model('appointments_mdl');
		}

		public function index(){
			$this->form();
		}

		public function form(){

			$data['open_cash'] = $this->cash_mdl->getOpenedCash();
			$data['cash_movements'] = $data['open_cash'] ? $this->cash_mdl->getCashMovements($data['open_cash']) : NULL;
			$data['low_stock_count'] = $this->products_mdl->getLowStockCount();
			$data['upcoming_appointments'] = $this->appointments_mdl->getUpcomingCount(7);

			$this->content_data = $data;
			$this->renderPanel();
		}

		//API for graph
		public function showSalesGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			$query = 'SELECT
			YEAR(S.date_time) AS Año,
			MONTH(S.date_time) AS Mes,
			DATE(S.date_time) AS Fecha,
			SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS "Ventas"
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
			GROUP BY Año, Mes
			ORDER BY Año DESC, Mes DESC;';

			$res = $this->db->query($query);
			echo json_encode($res->result());
			exit;
		}

		public function showTopSoldGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			$query = 'SELECT
			SUM(SP.quantity) AS units_sold,
            P.barcode,
			P.name
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
            INNER JOIN products AS P on P.id_product=SP.id_product
            GROUP BY P.barcode, P.name
            ORDER BY units_sold DESC
            LIMIT 10;';

			$res = $this->db->query($query);
			echo json_encode($res->result());
			exit;
		}

		public function showGoalsGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

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

			$res = $this->db->query($query);
			echo json_encode($res->result());
			exit;
		}
	}

?>
