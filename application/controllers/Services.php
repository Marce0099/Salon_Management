<?php if( !defined('BASEPATH') ){ exit(); }

class Services extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = 'select products.*, providers.provider, service_category.name_category
			from products
			left outer join providers using(id_provider)
			left outer join service_category using(id_service_category)';
		$search['order_by'] = array( 'id_product' => 'desc' );
		$search['group_by'] = array( 'id_product' );
		$search['return_as'] = 'services';
		$search['limit'] = 150;

		$search['fields'] = array(

			'name' => array( 'column' => 'name', 'type' => 'string' ),
			'barcode' => array( 'column' => 'barcode', 'type' => 'string' ),
			'provider' => array( 'column' => 'providers.id_provider', 'type' => 'numeric' ),
			'id_service_category' => array( 'column' => 'products.id_service_category', 'type' => 'numeric' ),
			'price_cost' => array( 'column' => 'price_cost', 'type' => 'numeric' ),
			'price_sell' => array( 'column' => 'price_sell', 'type' => 'numeric' )
		);

		$this->easysearch->setInitialWhere('products.active=1 and products.is_service=1');
		$data = $this->easysearch->search($search);

		$view_data['providers'] = $this->db->get('providers')->result();
		$view_data['categories'] = $this->db->get('service_category')->result();
		$this->content_data = array_merge($data,$view_data);

		$this->renderPanel('services');
	}
}
