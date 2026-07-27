<?php if( !defined('BASEPATH') ){ exit(); }

class Service_categories extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select * from service_category';
		$search['order_by'] = array( 'name_category' => 'asc' );
		$search['return_as'] = 'categorias';
		$search['fields'] = array(
			'name_category' => array( 'column' => 'name_category', 'type' => 'string' ),
		);

		$data = $this->easysearch->search($search);

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);

		$this->renderPanel('service_categories');
	}

	private function categoryExists( $name_category, $exclude_id = NULL ){

		$sql = 'select * from service_category where trim(upper(name_category)) = trim(upper("'.$this->db->escape_str($name_category).'"))';

		if( $exclude_id ){
			$sql .= ' and id_service_category != '.(int)$exclude_id;
		}

		return $this->db->query( $sql )->row();
	}

	private function resolveDiscountRate(){

		$discount_rate = trim( $this->input->post('discount_rate') );

		if( $discount_rate === '' ){
			return NULL;
		}

		if( !is_numeric($discount_rate) || $discount_rate < 0 || $discount_rate > 100 ){

			throw new Exception('El descuento debe ser un valor numérico entre 0 y 100');
		}

		return $discount_rate;
	}

	public function register(){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( formSubmited() ){

			$this->form_validation->set_rules('name_category','','trim|required|max_length[150]');

			try{

				if( $this->form_validation->run() ){

					$name_category = mb_strtolower( $this->input->post('name_category'), 'UTF-8' );

					if( $this->categoryExists($name_category) ){

						throw new Exception('Esa categoría ya está registrada');
					}

					$discount_rate = $this->resolveDiscountRate();

					$this->db->insert('service_category', array( 'name_category' => $name_category, 'discount_rate' => $discount_rate ));

					$new_tk = generateToken();
					$this->session->set_userdata('tk', $new_tk);

					$response = array( 'type' => 'success', 'message' => 'Categoría registrada', 'tk' => $new_tk );

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

			$this->form_service_categories();
		}
	}

	public function edit( $id_service_category = 0 ){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( formSubmited() ){

			$this->form_validation->set_rules('name_category','','trim|required|max_length[150]');

			try{

				$category = $this->db->get_where('service_category', array( 'id_service_category' => $id_service_category ))->row();

				if( !$category ){

					throw new Exception('Categoría inválida');
				}

				if( $this->form_validation->run() ){

					$name_category = mb_strtolower( $this->input->post('name_category'), 'UTF-8' );

					if( $this->categoryExists($name_category, $id_service_category) ){

						throw new Exception('Esa categoría ya está registrada');
					}

					$discount_rate = $this->resolveDiscountRate();

					$this->db->update('service_category', array( 'name_category' => $name_category, 'discount_rate' => $discount_rate ), array( 'id_service_category' => $id_service_category ));

					$new_tk = generateToken();
					$this->session->set_userdata('tk', $new_tk);

					$response = array( 'type' => 'success', 'message' => 'Categoría editada', 'tk' => $new_tk );

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

			$this->form_service_categories( $id_service_category );
		}
	}

	public function form_service_categories( $id_service_category = 0 ){

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		if( $id_service_category ){

			$category = $this->db->get_where('service_category', array( 'id_service_category' => $id_service_category ))->row();

			if( !$category ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => 'Categoría inválida' );
				$this->session->set_userdata( 'view_data', $sess_data );
				redirect('service_categories');
			}

			$view_data['category'] = $category;
		}

		$view_data['tk'] = generateToken();
		$this->session->set_userdata('tk', $view_data['tk']);

		$this->content_data = $view_data;
		$this->renderPanel('form_service_categories');
	}

	public function search(){

		$name = $this->input->post('name');
		$exact = $this->input->post('exact');

		if( $exact ){

			$data['category'] = $this->categoryExists($name);
		}
		else{

			$sql = 'select id_service_category, name_category from service_category where 1=1';

			if( !empty($name) ){
				$safe = $this->db->escape_like_str($name);
				$sql .= ' and name_category like "%'.$safe.'%"';
			}

			$sql .= ' order by name_category asc limit 50';

			$data['categories'] = $this->db->query($sql)->result();
		}

		echo json_encode($data);
	}
}
