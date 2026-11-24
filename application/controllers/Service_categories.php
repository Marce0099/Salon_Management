<?php if( !defined('BASEPATH') ){ exit(); }

class Service_categories extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		// waiters (trabajador) may not use this screen; only the listed methods stay open to them
		if( !$this->is_admin() && !in_array($this->router->fetch_method(), array('search')) ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}
	}

	public function index(){

		$this->load->library('easysearch');

		$search['query'] = 'select c.*, p.name as printer_name from service_category c left join printers p on p.id_printer = c.id_printer';
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

	/** Kitchen printer chosen in the form: NULL (default printer) or the id of an ACTIVE kitchen printer. */
	private function postedPrinter(){

		$id = (int) $this->input->post('id_printer');
		if( !$id ){ return NULL; }

		if( !$this->db->where(array('id_printer' => $id, 'role' => 'kitchen', 'active' => 1))->get('printers')->row() ){
			throw new Exception('Impresora inválida');
		}

		return $id;
	}

	public function register(){

		if( !$this->is_admin() ){
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

					$id_printer = $this->postedPrinter();

					$this->db->trans_start();
					$this->db->insert('service_category', array( 'name_category' => $name_category, 'id_printer' => $id_printer));
					$this->audit->log('category.create', 'service_category', $this->db->insert_id(), array('summary' => 'Categoría creada: '.$name_category, 'after' => array('name_category' => $name_category, 'id_printer' => $id_printer)));
					$this->db->trans_complete();

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

		if( !$this->is_admin() ){
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

					$id_printer = $this->postedPrinter();

					$before = $this->audit->row('service_category', 'id_service_category', $id_service_category);
					$this->db->trans_start();
					$this->db->update('service_category', array( 'name_category' => $name_category, 'id_printer' => $id_printer), array( 'id_service_category' => $id_service_category ));
					$this->audit->log('category.update', 'service_category', $id_service_category, array('summary' => 'Categoría modificada: '.$name_category, 'before' => array('name_category' => $before ? $before['name_category'] : NULL, 'id_printer' => $before ? $before['id_printer'] : NULL), 'after' => array('name_category' => $name_category, 'id_printer' => $id_printer)));
					$this->db->trans_complete();

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

		if( !$this->is_admin() ){
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

		$view_data['kitchen_printers'] = $this->db->where(array('role' => 'kitchen', 'active' => 1))->order_by('id_printer')->get('printers')->result();

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
