<?php if( !defined('BASEPATH') ){ exit(); }

class Products extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('products_mdl');
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

		$this->easysearch->setInitialWhere('products.active=1');
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
	}

	public function register(){

		if( formPosted() ){

			$this->setFormRules();

			try{

				if( $this->form_validation->run() ){

					$data = $this->input->post();

					if (empty($data['barcode'])) {
						$data['barcode'] = NULL;
					} else {
						if( $this->products_mdl->barcodeExists($data['barcode']) ){
							throw new Exception('El código de barra ya esta registrado a un producto');
						}
					}

					if(!$data['id_provider'])
						$data['id_provider'] = NULL;

					$this->products_mdl->save( $data );

					$response = array( 'type' => 'success', 'message' => 'Producto agregado al inventario' );

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

		$view_data['providers'] = $this->db->get('providers')->result();
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

					if( !isset($data['is_service']) ){

						$data['is_service'] = 0;
					}

					if( empty($data['barcode']) ){

						$data['barcode'] = NULL;
					}
					else{

						if( $this->products_mdl->barcodeExists($data['barcode'],$id_product) ){

							throw new Exception('El código de barra ya esta registrado a un producto');
						}
					}

					if(!$data['id_provider'])
						$data['id_provider'] = NULL;

					$this->products_mdl->edit( $id_product, $data );

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

	public function edit_new($id_product = 0){

		try{

			$view_data['product'] = $this->products_mdl->getProduct($id_product);

			if( !$view_data['product'] ){

				throw new Exception('Producto inválido',1);
			}

			if( formPosted() ){

				//$this->setFormRules();

				if( 1 ){

					$post = $this->input->post();
					$data['barcode'] = $post['barcode'];
					$data['name'] = $post['name'];
					$data['price_cost'] = $post['price_cost_dollar'];
					$data['price_sell'] = $post['price_sell'];
					$data['stock'] = $post['stock'];
					$data['is_service'] = isset($post['is_service']) ? 1 : 0;

					if( $this->products_mdl->barcodeExists($data['barcode'],$id_product) ){

						throw new Exception('El código de barra ya esta registrado a un producto');
					}

					$this->products_mdl->edit( $id_product, $data );

					$response = array( 'type' => 'success', 'message' => 'Producto editado', 'edit' => true );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');
				}

			}
			else{

				$this->form_products_new($view_data);
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

	public function register_new(){

		if( formPosted() ){

			//$this->setFormRules();

			try{

				$post = $this->input->post();
				$data['barcode'] = $post['barcode'];
				$data['name'] = $post['name'];
				$data['price_cost'] = $post['price_cost_dollar'];
				$data['price_sell'] = $post['price_sell'];
				$data['stock'] = $post['stock'];
				$data['is_service'] = isset($post['is_service']) ? 1 : 0;

				if( $post['is_new'] == 1 ){

					if( $this->products_mdl->barcodeExists($data['barcode']) ){

						throw new Exception('El código de barra ya esta registrado a un producto');
					}

					$this->products_mdl->save($data);
				}
				else{

					$this->products_mdl->edit_barcode($data);
				}

				$response = array( 'type' => 'success', 'message' => 'Producto guardado en el inventario' );

				echo json_encode( $response );
			}
			catch( Exception $e ){

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode( $response );
			}
		}
		else{

			$this->form_products_new();
		}
	}

	public function form_products_new( $view_data = array() ){

		$view_data['exchange'] = $this->db->get('dollar_exchange')->row();
		$this->content_data = $view_data;
		$this->renderPanel('form_products_new');
	}
}