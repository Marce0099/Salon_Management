<?php if( !defined('BASEPATH') ){ exit(); }

class Products extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('products_mdl');
		$this->load->model('internal_use_mdl');
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = 'select products.*, providers.provider from products left outer join providers using(id_provider)';
		$search['order_by'] = array( 'id_product' => 'desc' );
		$search['group_by'] = array( 'id_product' );
		$search['return_as'] = 'products';
		$search['limit'] = 150;

		$search['fields'] = array(

			'name' => array( 'column' => 'name', 'type' => 'string' ),
			'barcode' => array( 'column' => 'barcode', 'type' => 'string' ),
			'provider' => array( 'column' => 'providers.id_provider', 'type' => 'numeric' ),
			'stock' => array( 'column' => 'stock', 'type' => 'numeric'),
			'price_cost' => array( 'column' => 'price_cost', 'type' => 'numeric' ),
			'price_sell' => array( 'column' => 'price_sell', 'type' => 'numeric' )
		);

		$this->easysearch->setInitialWhere('products.active=1 and products.is_service=0');
		$data = $this->easysearch->search($search);

		$data['total_inventory'] = $this->products_mdl->getTotalInventory(array_filter($_GET),$data['query_no_limit']);

		$view_data['providers'] = $this->db->get('providers')->result();
		$this->content_data = array_merge($data,$view_data);

		$this->renderPanel('products');
	}

	private function setFormRules(){

		$this->form_validation->set_rules('barcode','trim');
		$this->form_validation->set_rules('name','trim|required');
		$this->form_validation->set_rules('description','trim');
		$this->form_validation->set_rules('stock','is_natural_no_zero');
		$this->form_validation->set_rules('price_sell','required|numeric');
		$this->form_validation->set_rules('price_cost','required|numeric');
		$this->form_validation->set_rules('commisssion_rate','numeric');
		$this->form_validation->set_rules('id_service_category','numeric');
		$this->form_validation->set_rules('stock_alert_threshold','trim|is_natural');
	}

	private function normalizeStockAlertThreshold( &$data ){

		if( !isset($data['stock_alert_threshold']) || $data['stock_alert_threshold'] === '' ){

			$data['stock_alert_threshold'] = NULL;
		}

		if( !empty($data['is_service']) ){

			$data['stock_alert_threshold'] = NULL;
		}
	}

	private function resolveServiceCategory( $data ){

		if( empty($data['id_service_category']) ){

			return NULL;
		}

		$exists = $this->db->get_where('service_category', array( 'id_service_category' => $data['id_service_category'] ))->row();

		return $exists ? $data['id_service_category'] : NULL;
	}

	// Reads and validates the "Presentaciones" rows posted with the product form (removes them from $data).
	private function extractPacks( &$data, $id_product = NULL ){

		$names    = isset($data['pack_names'])    ? (array)$data['pack_names']    : array();
		$units    = isset($data['pack_units'])    ? (array)$data['pack_units']    : array();
		$costs    = isset($data['pack_costs'])    ? (array)$data['pack_costs']    : array();
		$barcodes = isset($data['pack_barcodes']) ? (array)$data['pack_barcodes'] : array();

		unset($data['pack_names'], $data['pack_units'], $data['pack_costs'], $data['pack_barcodes']);

		if( !empty($data['is_service']) ){

			return array();
		}

		$packs = array();
		$seen = array();

		if( !empty($data['barcode']) ){

			$seen[strtoupper(trim($data['barcode']))] = TRUE;
		}

		foreach( $names as $i => $name ){

			$name = trim((string)$name);
			$u = isset($units[$i]) ? trim((string)$units[$i]) : '';
			$cost = isset($costs[$i]) ? trim((string)$costs[$i]) : '';
			$bc = isset($barcodes[$i]) ? trim((string)$barcodes[$i]) : '';

			if( $name === '' && $u === '' && $cost === '' && $bc === '' ){
				continue;
			}

			if( $name === '' || strlen($name) > 60 ){
				throw new Exception('Cada presentación necesita un nombre (máximo 60 caracteres)');
			}
			if( !ctype_digit($u) || (int)$u < 2 || (int)$u > 100000 ){
				throw new Exception('Las unidades de la presentación "'.$name.'" deben ser un entero mayor a 1');
			}
			if( $cost !== '' && ( !is_numeric($cost) || (float)$cost < 0 || (float)$cost > 99999999 ) ){
				throw new Exception('El costo de la presentación "'.$name.'" es inválido');
			}
			if( strlen($bc) > 20 ){
				throw new Exception('El código de barra de la presentación "'.$name.'" es muy largo (máximo 20)');
			}
			if( $bc !== '' ){

				$key = strtoupper($bc);

				if( isset($seen[$key]) || $this->products_mdl->barcodeTaken($bc, $id_product) ){
					throw new Exception('El código de barra "'.$bc.'" ya está registrado');
				}
				$seen[$key] = TRUE;
			}

			$packs[] = array(
				'name' => $name,
				'units' => (int)$u,
				'barcode' => $bc === '' ? NULL : $bc,
				'price_cost' => $cost === '' ? NULL : round((float)$cost, 2)
			);
		}

		return $packs;
	}

	public function register(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( formSubmited() ){

			$this->setFormRules();

			try{

				if( $this->form_validation->run() ){

					$data = $this->input->post();
					$data["prints_kitchen"] = !empty($data["prints_kitchen"]) ? 1 : 0;
					unset($data['tk']);

					if (empty($data['barcode'])) {
						$data['barcode'] = NULL;
					} else {
						if( $this->products_mdl->barcodeTaken($data['barcode']) ){
							throw new Exception('El código de barra ya esta registrado a un producto');
						}
					}

					$packs = $this->extractPacks( $data );

					if(!$data['id_provider'])
						$data['id_provider'] = NULL;

					$data['id_service_category'] = $this->resolveServiceCategory( $data );
					$this->normalizeStockAlertThreshold( $data );

					$this->db->trans_begin();
					$id_product = $this->products_mdl->save( $data );
					$this->products_mdl->savePacks( $id_product, $packs );

					if( $this->db->trans_status() === FALSE ){
						$this->db->trans_rollback();
						throw new Exception('No se ha podido guardar el producto');
					}
					$this->db->trans_commit();

					$new_tk = generateToken();
					$this->session->set_userdata('tk', $new_tk);

					$response = array( 'type' => 'success', 'message' => 'Producto agregado al inventario', 'tk' => $new_tk );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');	
				}
			}
			catch( Exception $e ){

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode( $response );
			}
		}
		else{

			$this->form_products();
		}
	}

	public function form_products( $view_data = array() ){

		if( !isset($view_data['product']) ){

			if( !$this->is_admin() ){
				echo "No se encontro resultado para esta pagina";
				exit;
			}

			$view_data['tk'] = generateToken();
			$this->session->set_userdata('tk', $view_data['tk']);
		}

		$view_data['providers'] = $this->db->get('providers')->result();
		$view_data['packs'] = isset($view_data['product']) ? $this->products_mdl->getPacks($view_data['product']->id_product) : array();
		$this->content_data = $view_data;
		$this->renderPanel('form_products');
	}

	public function edit($id_product = 0){

		try{

			$view_data['product'] = $this->products_mdl->getProduct($id_product);

			if( !$view_data['product'] ){

				throw new Exception('Producto inválido',1);
			}

			if( formPosted() ){

				$this->setFormRules();

				if( $this->form_validation->run() ){

					$data = $this->input->post();
					$data["prints_kitchen"] = !empty($data["prints_kitchen"]) ? 1 : 0;

					if( !isset($data['is_service']) ){

						$data['is_service'] = 0;
					}

					if( empty($data['barcode']) ){
						$data['barcode'] = NULL;
					}
					else{
						if( $this->products_mdl->barcodeTaken($data['barcode'],$id_product) ){
							throw new Exception('El código de barra ya esta registrado a un producto');
						}
					}

					$packs = $this->extractPacks( $data, $id_product );

					if(!$data['id_provider'])
						$data['id_provider'] = NULL;

					$data['id_service_category'] = $this->resolveServiceCategory( $data );
					$this->normalizeStockAlertThreshold( $data );

					$this->db->trans_begin();
					$this->products_mdl->edit( $id_product, $data );
					$this->products_mdl->savePacks( $id_product, $packs );

					if( $this->db->trans_status() === FALSE ){
						$this->db->trans_rollback();
						throw new Exception('No se ha podido guardar el producto');
					}
					$this->db->trans_commit();

					$response = array( 'type' => 'success', 'message' => 'Producto editado', 'edit' => true );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');
				}

			}
			else{

				$this->form_products($view_data);
			}
		}	
		catch( Exception $e ){

			if( $e->getCode() == 1 ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

				$this->session->set_userdata( 'view_data', $sess_data );
				redirect('products');
			}
			else{

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode($response);
			}
		}
	}

	public function delete( $id_product = 0 ){

		try{

			$product = $this->products_mdl->getProduct( $id_product );

			if( !$product ){

				throw new Exception('Producto inválido');
			}

			$this->products_mdl->delete($id_product);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Producto eliminado' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('products');

		}
		catch( Exception $e ){

			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('products');
		}
	}

	public function packs( $id_product = 0 ){

		echo json_encode( array( 'packs' => $this->products_mdl->getPacks( (int)$id_product ) ) );
	}

	public function getByBarCode(){

		$barcode = $this->input->post('barcode');

		if( !empty($barcode) ){

			$data['product'] = $this->products_mdl->barcodeExists( $barcode );

			echo json_encode($data);
		}
	}

	public function searchProducts(){

		$barcode = $this->input->post('barcode');
		$name    = $this->input->post('name');
		$type    = $this->input->post('type');

		if( !in_array($type, array('all','products','services')) ){
			$type = 'all';
		}

		$data['products'] = $this->products_mdl->searchActive($barcode, $name, $type);
		echo json_encode($data);
	}

	public function searchAvailableProducts(){

		$barcode = $this->input->post('barcode');
		$name    = $this->input->post('name');
		$type    = $this->input->post('type');

		if( !in_array($type, array('all','products','services')) ){
			$type = 'all';
		}

		$data['products'] = $this->products_mdl->searchAvailable($barcode, $name, $type);
		echo json_encode($data);
	}

	public function getProductForReturn(){

		$barcode = $this->input->post('barcode');
		$id_provider = $this->input->post('id_provider') !== "" ? 
		$this->input->post('id_provider') : NULL;


		if( !empty($barcode) /* && !empty($id_provider) */ ){

			$data['product'] = $this->products_mdl->barcodeExistsForProvider( $barcode, $id_provider );
			
			echo json_encode($data);
		}
	}

	public function addStock(){

		$quantity = $this->input->post('quantity');
		$id_product = $this->input->post('id_product');

		try{

			$product = $this->products_mdl->getProduct( $id_product );

			if( !$product ){

				throw new Exception('Producto inválido');
			}

			$stock = $this->products_mdl->addStock($id_product,$quantity);

			$data['message'] = 'Stock del producto actualizado';
			$data['type'] = 'success';
			$data['stock'] = $stock;

			echo json_encode($data);
		}
		catch( Exception $e ){

			$data['message'] = $e->getMessage();
			$data['type'] = 'warning';

			echo json_encode($data);
		}
		
	}

	public function lowStockCount(){

		echo json_encode( array( 'count' => $this->products_mdl->getLowStockCount() ) );
	}

	public function lowStockList(){

		echo json_encode( array( 'products' => $this->products_mdl->getLowStockProducts() ) );
	}

	public function validateQuantities(){

		$id_products = $this->input->post('id_products');
		$quantities = $this->input->post('quantities');

		if( !empty($id_products) && !empty($quantities) ){

			for( $i=0; $i < count($id_products); $i++ ){

				$p = $this->products_mdl->getProduct($id_products[$i]);

				if( !$p->is_service && $p->stock < $quantities[$i] ){

					$data['message'] = '<p>'.$p->name.'</p>La cantidad seleccionada es mayor que el stock disponible ('.$p->stock.')';
					$data['type'] = 'error';

					echo json_encode($data);
					exit();
				}
			}

			$data['type'] = 'success';
			echo json_encode($data);
		}
		else{

			$data['message'] = 'Datos invalidos';
			$data['type'] = 'error';

			echo json_encode($data);
		}
	}

	public function searchForInternalUse(){

		$search = $this->input->post('search');

		$data['products'] = $this->products_mdl->searchForInternalUse($search);
		echo json_encode($data);
	}

	public function internalUse(){

		if( formPosted() ){

			$this->form_validation->set_rules('reason',        '', 'required|trim');
			$this->form_validation->set_rules('id_products[]', '', 'required|is_natural_no_zero');
			$this->form_validation->set_rules('quantities[]',  '', 'required|is_natural_no_zero');

			if( $this->form_validation->run() ){

				$data   = $this->input->post();
				$reason = trim($data['reason']);

				$this->db->trans_begin();

				try{

					$id_internal_use = $this->internal_use_mdl->save( $this->id_admin, $reason );
					$this->internal_use_mdl->saveProducts(
						$id_internal_use, $data['id_products'], $data['quantities']
					);

					if( $this->db->trans_status() === FALSE ){
						throw new Exception('No se ha podido registrar el uso interno');
					}

					$this->db->trans_commit();

					$view_data['status'] = array( 'class' => 'success', 'message' => 'Uso interno registrado e inventario actualizado' );
					$this->session->set_userdata('view_data', $view_data);
					redirect('products/internal_use_list');
				}
				catch( Exception $e ){

					$this->db->trans_rollback();
					$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );
					$this->internalUseForm($view_data);
				}
			}
			else{

				$view_data['status'] = array( 'class' => 'error', 'message' => 'Datos invalidos' );
				$this->internalUseForm($view_data);
			}
		}
		else{

			$this->internalUseForm();
		}
	}

	private function internalUseForm( $view_data = array() ){

		$this->footer_data = $view_data;
		$this->renderPanel('form_internal_use');
	}

	public function internal_use_list(){

		$this->load->library('easysearch');

		$search['query'] = 'select IU.*, A.name as admin_name from internal_uses IU
		left outer join admins A on IU.id_admin = A.id_admin inner join internal_use_products IUP on IU.id_internal_use = IUP.id_internal_use';
		$search['order_by'] = array( 'IU.id_internal_use' => 'desc' );
		$search['group_by'] = array( 'IU.id_internal_use' );
		$search['return_as'] = 'internal_uses';
		$search['limit'] = 100;
		$search['fields'] = array(

			'date_start' => array( 'column' => 'IU.date_time', 'type' => 'date' ),
			'date_end' => array( 'column' => 'IU.date_time', 'type' => 'date' )
		);

		$data = $this->easysearch->search($search);

		foreach( $data['internal_uses'] as $iu ){

			$sql = 'select sum(IUP.quantity * IUP.price_cost) as total_cost from internal_use_products IUP
			where IUP.id_internal_use = '.$iu->id_internal_use;

			$iu->total_cost = $this->db->query($sql)->row('total_cost');
		}

		$data['totals'] = $this->internal_use_mdl->getTotalInternalUse(array_filter($_GET),$data['query_no_limit']);

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);

		$this->renderPanel('internal_use');
	}

	public function internal_use_details( $id_internal_use = 0 ){

		$data['internal_use'] = $this->internal_use_mdl->getDetails($id_internal_use);

		if( !$data['internal_use'] ){

			$view_data = array( 'class' => 'error', 'message' => 'Datos invalidos' );
			$this->session->set_userdata('view_data',$view_data);
			redirect('products/internal_use_list');
			exit();
		}

		$this->content_data = $data;
		$this->renderPanel('internal_use_details');
	}

}