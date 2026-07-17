<?php if( !defined('BASEPATH') ){ exit(); }

class Admins extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('admins_mdl');

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->content_data['admins'] = $this->admins_mdl->getAll();
		$this->content_data = array_merge($this->content_data, $view_data);

		$this->renderPanel('admins');
	}

	private function setFormRules(){

		$this->form_validation->set_rules('username','', 'trim|required|max_length[50]');
		$this->form_validation->set_rules('password','', 'required|min_length[6]');
		$this->form_validation->set_rules('name','', 'trim|required|max_length[100]');
		$this->form_validation->set_rules('role','', 'required|in_list[administrador,trabajador]');
	}

	public function save(){

		$this->setFormRules();

		try {
			if( formPosted() && $this->form_validation->run() ){

				$username = $this->input->post('username');

				if( $this->admins_mdl->usernameExists($username) ){

					throw new \Exception('Ya existe un usuario con ese nombre');
				}

				$data = array(
					'username' => $username,
					'password' => sha1($this->input->post('password')),
					'name'     => $this->input->post('name'),
					'role'     => $this->input->post('role'),
				);

				$this->admins_mdl->save($data);

				$response = array( 'type' => 'success', 'message' => 'Usuario registrado' );
				echo json_encode($response);
			}
			else{

				throw new \Exception('Datos inválidos');
			}
		} catch (\Throwable $th) {

			$response = array( 'type' => 'error', 'message' => $th->getMessage() );
			echo json_encode($response);
		}
	}

	public function delete($id_admin = 0){

		try {

			$admin = $this->admins_mdl->getAdmin($id_admin);

			if( !$admin || !$admin->active ){

				throw new \Exception('Usuario inválido');
			}

			if( $this->admins_mdl->countActive() <= 1 ){

				throw new \Exception('No se puede eliminar el último usuario registrado');
			}

			if( $admin->role === 'administrador' && $this->admins_mdl->countActiveByRole('administrador') <= 1 ){

				throw new \Exception('Debe existir al menos un administrador');
			}

			$this->admins_mdl->delete($id_admin);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Usuario eliminado' );
			$this->session->set_userdata( 'view_data', $view_data );
			redirect('admins/list');

		} catch (\Throwable $th) {

			$view_data['status'] = array( 'class' => 'error', 'message' => $th->getMessage() );
			$this->session->set_userdata( 'view_data', $view_data );
			redirect('admins/list');
		}
	}
}
