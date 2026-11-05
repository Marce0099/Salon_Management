<?php if( !defined('BASEPATH') ){ exit(); }

/** Restaurant reports (admin only): sales by waiter / table / category / shift, tips, top dishes, kitchen ticket history. */
class Reports extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('reports_mdl');
	}

	private function validDate($v, $default){

		return ( is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== FALSE ) ? $v : $default;
	}

	public function index(){

		$from = $this->validDate($this->input->get('from'), date('Y-m-01'));
		$to   = $this->validDate($this->input->get('to'), date('Y-m-d'));

		if( $from > $to ){
			list($from, $to) = array($to, $from);
		}

		$this->content_data = array_merge($this->reports_mdl->report($from, $to), array('from' => $from, 'to' => $to));
		$this->renderPanel('reports');
	}
}
