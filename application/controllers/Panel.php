<?php 
//debug($_SESSION);
	if( !defined('BASEPATH') ){ exit(); }

	class Panel extends Admin_Controller{

		public function __construct(){
			parent::__construct();
		}

		public function index(){
			$this->form();
		}

		public function form(){
			$this->renderPanel();
		}

		//API for graph
		public function showSalesGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			$query = 'SELECT 
			YEAR(S.date_time) AS Año, 
			MONTH(S.date_time) AS Mes,
			DATE(S.date_time) AS Fecha,
			SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS "Ganancias"
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
			GROUP BY Año, Mes
			ORDER BY Año DESC, Mes DESC;';

			$res = $this->db->query($query);
			echo json_encode($res->result());
			exit;
		}

		public function showTopServicesGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			$query = 'SELECT 
			COUNT(P.barcode) AS frequency,
            P.barcode,
			P.name
			FROM sales as S
			INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
            INNER JOIN products AS P on P.id_product=SP.id_product
            WHERE P.is_service = 1
            GROUP BY P.barcode, P.name
            ORDER BY frequency DESC
            LIMIT 10;';

			$res = $this->db->query($query);
			echo json_encode($res->result());
			exit;
		}
	}

?>