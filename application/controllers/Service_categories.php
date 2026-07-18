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

	private function categoryExists( $name_category ){

		$sql = 'select * from service_category where trim(upper(name_category)) = trim(upper("'.$this->db->escape_str($name_category).'"))';

		return $this->db->query( $sql )->row();
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

					$this->db->insert('service_category', array( 'name_category' => $name_category ));

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

	public function form_service_categories(){

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
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
