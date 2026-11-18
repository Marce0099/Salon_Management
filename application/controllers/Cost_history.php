<?php if( !defined('BASEPATH') ){ exit(); }

/** Audit of product cost changes (admin only): purchases, manual edits and imports, with average / highest cost per product. */
class Cost_history extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('cost_history_mdl');
	}

	private function validDate($v){

		return ( is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== FALSE ) ? $v : '';
	}

	public function index(){

		$f = array(
			'id_product' => ctype_digit((string)$this->input->get('id_product')) ? (int)$this->input->get('id_product') : 0,
			'id_provider' => ctype_digit((string)$this->input->get('id_provider')) ? (int)$this->input->get('id_provider') : 0,
			'source' => (string)$this->input->get('source'),
			'from' => $this->validDate($this->input->get('from')),
			'to' => $this->validDate($this->input->get('to')),
			'only_changes' => $this->input->get('only_changes') ? 1 : 0
		);

		if( $f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to'] ){
			list($f['from'], $f['to']) = array($f['to'], $f['from']);
		}

		$data = array(
			'f' => $f,
			'products' => $this->db->query('select id_product, name from products where active = 1 and is_service = 0 order by name')->result(),
			'providers' => $this->db->query('select id_provider, provider from providers order by provider')->result(),
			'product' => $f['id_product'] ? $this->db->get_where('products', array('id_product' => $f['id_product']))->row() : NULL,
			'stats' => NULL, 'rows' => array(), 'summary' => array()
		);

		if( $f['id_product'] ){

			$data['stats'] = $this->cost_history_mdl->productStats($f);
			$data['rows'] = $this->cost_history_mdl->rows($f);
		}
		else{

			$data['summary'] = $this->cost_history_mdl->summary($f);
		}

		$this->content_data = $data;
		$this->renderPanel('cost_history');
	}
}
