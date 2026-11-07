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
		$view_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $view_data['tk']);
		$this->content_data = array_merge($data,$view_data);

		$this->renderPanel('products');
	}

	private function importColumns(){

		return array(
			array('key' => 'barcode',       'label' => 'Código de barras',      'required' => FALSE, 'text' => TRUE, 'width' => 20, 'note' => 'Opcional, máximo 20 caracteres, único en todo el sistema'),
			array('key' => 'name',          'label' => 'Nombre',                'required' => TRUE,  'text' => TRUE, 'width' => 34, 'note' => 'Texto, máximo 250 caracteres'),
			array('key' => 'category',      'label' => 'Categoría',             'required' => FALSE, 'text' => TRUE, 'width' => 20, 'note' => 'Solo platillos. Si no existe se crea'),
			array('key' => 'price_cost',    'label' => 'Precio costo',          'required' => TRUE,  'text' => TRUE, 'width' => 14, 'note' => 'Dólares, número (por unidad). Ej.: 1.25'),
			array('key' => 'price_sell',    'label' => 'Precio venta',          'required' => TRUE,  'text' => TRUE, 'width' => 14, 'note' => 'Dólares, número. Ej.: 2.50'),
			array('key' => 'stock',         'label' => 'Stock',                 'required' => FALSE, 'text' => TRUE, 'width' => 10, 'note' => 'Entero, 0 si se deja vacío. Vacío para platillos'),
			array('key' => 'alert',         'label' => 'Alerta stock mínimo',   'required' => FALSE, 'text' => TRUE, 'width' => 18, 'note' => 'Entero opcional. No aplica a platillos'),
			array('key' => 'is_service',    'label' => 'Platillo',              'required' => FALSE, 'text' => TRUE, 'width' => 10, 'note' => 'Sí o No (No si se deja vacío). Platillo = sin inventario'),
			array('key' => 'prints_kitchen','label' => 'Imprime en cocina',     'required' => FALSE, 'text' => TRUE, 'width' => 16, 'note' => 'Sí o No (Sí para platillos, No para productos si se deja vacío)'),
			array('key' => 'pack_name',     'label' => 'Presentación',          'required' => FALSE, 'text' => TRUE, 'width' => 16, 'note' => 'Opcional, ej.: Six-pack. Una presentación por producto'),
			array('key' => 'pack_units',    'label' => 'Unidades presentación', 'required' => FALSE, 'text' => TRUE, 'width' => 18, 'note' => 'Entero mayor a 1 (obligatorio si hay presentación)'),
			array('key' => 'pack_cost',     'label' => 'Costo presentación',    'required' => FALSE, 'text' => TRUE, 'width' => 16, 'note' => 'Opcional, dólares por presentación'),
			array('key' => 'pack_barcode',  'label' => 'Código presentación',   'required' => FALSE, 'text' => TRUE, 'width' => 20, 'note' => 'Opcional, máximo 20 caracteres, único'),
		);
	}

	public function template(){

		if( !$this->is_admin() ){ echo "No se encontro resultado para esta pagina"; exit; }

		$this->load->library('excel_import');
		$this->excel_import->sendTemplate('plantilla_productos.xlsx', $this->importColumns(), array(
			'Un producto se considera repetido cuando coincide el código de barras (o el nombre, si no tiene código).',
			'Los precios son en dólares, igual que en el formulario de productos.',
		));
	}

	public function importYesNo( $value, $default ){

		$v = mb_strtolower(trim($value), 'UTF-8');

		if( $v === '' ) return $default;
		if( in_array($v, array('si', 'sí', 's', '1', 'yes'), TRUE) ) return 1;
		if( in_array($v, array('no', 'n', '0'), TRUE) ) return 0;

		return NULL;
	}

	public function importNumber( $value ){

		$v = str_replace(',', '.', trim($value));

		return ( $v !== '' && is_numeric($v) && (float)$v >= 0 && (float)$v <= 99999999 ) ? (float)$v : NULL;
	}

	public function import(){

		if( !$this->is_admin() ){
			echo json_encode(array('type' => 'error', 'message' => 'No tiene permisos para esta acción'));
			return;
		}

		$this->load->library('excel_import');

		if( !formSubmited() ){
			echo json_encode(array('type' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo'));
			return;
		}

		$db = $this->db;
		$self = $this;
		$prod_codes = array();  // upper-cased product barcodes seen in the file
		$pack_codes = array();  // upper-cased pack barcodes seen in the file

		$key = function($d){

			return $d['barcode'] !== '' ? 'b:'.mb_strtoupper($d['barcode'], 'UTF-8') : 'n:'.mb_strtolower($d['name'], 'UTF-8');
		};

		$response = $this->excel_import->run(array(
			'columns' => $this->importColumns(),
			'db' => $db,
			'validate' => function(&$d) use ($self, $db, &$prod_codes, &$pack_codes){

				if( $d['name'] === '' ) return 'El nombre es obligatorio';
				if( mb_strlen($d['name'], 'UTF-8') > 250 ) return 'El nombre supera 250 caracteres';
				if( mb_strlen($d['barcode'], 'UTF-8') > 20 ) return 'El código de barras supera 20 caracteres';
				if( mb_strlen($d['category'], 'UTF-8') > 150 ) return 'La categoría supera 150 caracteres';

				$cost = $self->importNumber($d['price_cost']);
				$sell = $self->importNumber($d['price_sell']);
				if( $cost === NULL ) return 'Precio costo inválido';
				if( $sell === NULL ) return 'Precio venta inválido';

				$service = $self->importYesNo($d['is_service'], 0);
				if( $service === NULL ) return 'Platillo debe ser Sí o No';
				$kitchen = $self->importYesNo($d['prints_kitchen'], $service ? 1 : 0);
				if( $kitchen === NULL ) return 'Imprime en cocina debe ser Sí o No';

				$stock = 0;
				if( $d['stock'] !== '' ){
					if( !ctype_digit($d['stock']) || (int)$d['stock'] > 1000000 ) return 'Stock inválido (entero mayor o igual a 0)';
					$stock = (int)$d['stock'];
				}

				$alert = NULL;
				if( $d['alert'] !== '' ){
					if( !ctype_digit($d['alert']) || (int)$d['alert'] > 1000000 ) return 'Alerta de stock inválida';
					$alert = (int)$d['alert'];
				}

				$has_pack = ( $d['pack_name'] !== '' || $d['pack_units'] !== '' || $d['pack_cost'] !== '' || $d['pack_barcode'] !== '' );

				if( $service ){

					if( $stock > 0 || $alert !== NULL ) return 'Un platillo no lleva stock ni alerta de stock';
					if( $has_pack ) return 'Un platillo no puede tener presentaciones';
				}
				else{

					if( $d['category'] !== '' ) return 'La categoría solo aplica a platillos';
				}

				$pack_units = 0; $pack_cost = NULL;
				if( $has_pack ){

					if( $d['pack_name'] === '' || mb_strlen($d['pack_name'], 'UTF-8') > 60 ) return 'La presentación necesita un nombre (máximo 60 caracteres)';
					if( !ctype_digit($d['pack_units']) || (int)$d['pack_units'] < 2 || (int)$d['pack_units'] > 100000 ) return 'Las unidades de la presentación deben ser un entero mayor a 1';
					$pack_units = (int)$d['pack_units'];

					if( $d['pack_cost'] !== '' ){
						$pack_cost = $self->importNumber($d['pack_cost']);
						if( $pack_cost === NULL ) return 'Costo de presentación inválido';
					}
					if( mb_strlen($d['pack_barcode'], 'UTF-8') > 20 ) return 'El código de la presentación supera 20 caracteres';
				}

				// barcodes must stay disjoint between products and packs (inside the file and against the DB)
				$pc = mb_strtoupper($d['barcode'], 'UTF-8');
				$kc = mb_strtoupper($d['pack_barcode'], 'UTF-8');

				if( $pc !== '' && isset($pack_codes[$pc]) ) return 'El código de barras ya lo usa una presentación';
				if( $pc !== '' && $db->query('select 1 from product_packs where upper(barcode) = ?', array($pc))->row() ) return 'El código de barras ya lo usa una presentación';

				if( $kc !== '' ){

					if( $kc === $pc || isset($pack_codes[$kc]) || isset($prod_codes[$kc]) ) return 'El código de la presentación está repetido';
					if( $db->query('select 1 from products where upper(barcode) = ? union select 1 from product_packs where upper(barcode) = ?', array($kc, $kc))->row() ) return 'El código de la presentación ya está registrado';
					$pack_codes[$kc] = TRUE;
				}
				if( $pc !== '' ) $prod_codes[$pc] = TRUE;

				$d['_cost'] = $cost; $d['_sell'] = $sell; $d['_service'] = $service; $d['_kitchen'] = $kitchen;
				$d['_stock'] = $stock; $d['_alert'] = $alert; $d['_pack_units'] = $pack_units; $d['_pack_cost'] = $pack_cost;

				return NULL;
			},
			'key' => $key,
			'existing' => function() use ($db){

				$keys = array();
				foreach( $db->select('barcode, name')->get('products')->result_array() as $r ){
					$keys[] = ( $r['barcode'] !== NULL && $r['barcode'] !== '' ) ? 'b:'.mb_strtoupper($r['barcode'], 'UTF-8') : 'n:'.mb_strtolower((string)$r['name'], 'UTF-8');
				}
				return $keys;
			},
			'insert' => function($rows) use ($db){

				$categories = array();
				foreach( $db->get('service_category')->result_array() as $c ){
					$categories[mb_strtolower($c['name_category'], 'UTF-8')] = (int)$c['id_service_category'];
				}

				foreach( $rows as $d ){

					$id_cat = NULL;
					if( $d['category'] !== '' ){

						$ck = mb_strtolower($d['category'], 'UTF-8');
						if( !isset($categories[$ck]) ){
							$db->insert('service_category', array('name_category' => $d['category']));
							$categories[$ck] = (int)$db->insert_id();
						}
						$id_cat = $categories[$ck];
					}

					$db->insert('products', array(
						'barcode' => $d['barcode'] !== '' ? $d['barcode'] : NULL,
						'name' => $d['name'],
						'price_cost' => (string)round($d['_cost'], 4),
						'price_sell' => (string)round($d['_sell'], 4),
						'stock' => $d['_stock'],
						'stock_alert_threshold' => $d['_alert'],
						'is_service' => $d['_service'],
						'id_service_category' => $id_cat,
						'prints_kitchen' => $d['_kitchen'],
						'commisssion_rate' => 0,
					));
					$id_product = (int)$db->insert_id();

					if( $d['_pack_units'] > 0 ){

						$db->insert('product_packs', array(
							'id_product' => $id_product,
							'name' => $d['pack_name'],
							'units' => $d['_pack_units'],
							'barcode' => $d['pack_barcode'] !== '' ? $d['pack_barcode'] : NULL,
							'price_cost' => $d['_pack_cost'] !== NULL ? round($d['_pack_cost'], 2) : NULL,
						));
					}
				}

				if( $db->trans_status() === FALSE ){
					throw new Exception('insert failed');
				}
			},
		));

		$new_tk = generateToken();
		$this->session->set_userdata('tk', $new_tk);
		$response['tk'] = $new_tk;

		echo json_encode($response);
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