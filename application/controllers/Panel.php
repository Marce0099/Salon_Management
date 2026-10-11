<?php
	if( !defined('BASEPATH') ){ exit(); }

	class Panel extends Admin_Controller{

		public function __construct(){
			parent::__construct();

			$this->load->model('cash_mdl');
			$this->load->model('products_mdl');
			$this->load->model('appointments_mdl');
			$this->load->model('statistics_mdl');
		}

		public function index(){
			$this->form();
		}

		public function form(){

			$data['open_cash'] = $this->cash_mdl->getOpenedCash();
			$data['cash_movements'] = $data['open_cash'] ? $this->cash_mdl->getCashMovements($data['open_cash']) : NULL;
			$data['low_stock_count'] = $this->products_mdl->getLowStockCount();
			$data['upcoming_appointments'] = $this->appointments_mdl->getUpcomingCount(7);
			// monthly goals and per-employee sales are admin-only everywhere else
			// (Sale_goals, Employees), so the dashboard does not leak them to workers
			$data['is_admin'] = $this->is_admin();
			$data['goal_progress'] = $data['is_admin'] ? $this->statistics_mdl->getCurrentMonthGoalProgress() : NULL;

			$this->content_data = $data;
			$this->renderPanel();
		}

		//API for graph
		public function showSalesGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			echo json_encode($this->statistics_mdl->getSalesTrend());
			exit;
		}

		public function showTopSoldGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			echo json_encode($this->statistics_mdl->getTopSold(10));
			exit;
		}

		private function denyUnlessAdmin(){

			if( !$this->is_admin() ){

				http_response_code(403);
				echo json_encode(array());
				exit;
			}
		}

		public function showGoalsGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;
			$this->denyUnlessAdmin();

			echo json_encode($this->statistics_mdl->getGoalsVsActual());
			exit;
		}

		public function showPaymentMethodGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			echo json_encode($this->statistics_mdl->getRevenueByPaymentMethod());
			exit;
		}

		public function showSalesByEmployeeGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;
			$this->denyUnlessAdmin();

			echo json_encode($this->statistics_mdl->getSalesByEmployee(10));
			exit;
		}

		public function showNewClientsGraph(){
			if($_SERVER["REQUEST_METHOD"] !== "POST") exit;

			echo json_encode($this->statistics_mdl->getNewClientsTrend());
			exit;
		}
	}

?>
