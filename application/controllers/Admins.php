<?php if( !defined('BASEPATH') ){ exit(); }

class Admins extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('admins_mdl');

		if( !$this->is_super() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->content_data['admins'] = $this->admins_mdl->getAll();
		$this->content_data['tk'] = $this->issueToken();
		$this->content_data = array_merge($this->content_data, $view_data);

		$this->renderPanel('admins');
	}

	private function issueToken(){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);

		return $tk;
	}

	private function setFormRules(){

		$this->form_validation->set_rules('username','', 'trim|required|max_length[50]');
		$this->form_validation->set_rules('password','', 'required|min_length[6]|max_length[72]');
		$this->form_validation->set_rules('name','', 'trim|required|max_length[100]');
		$this->form_validation->set_rules('role','', 'required|in_list[super,administrador,trabajador]');
	}

	public function save(){

		$this->setFormRules();

		try {
			if( !formSubmited() ){

				throw new \Exception('Sesión inválida, recargue la página e intente de nuevo');
			}

			if( $this->form_validation->run() ){

				$username = $this->input->post('username');

				if( $this->input->post('password') !== $this->input->post('password_confirm') ){

					throw new \Exception('Las contraseñas no coinciden');
				}

				if( $this->admins_mdl->usernameExists($username) ){

					throw new \Exception('Ya existe un usuario con ese nombre');
				}

				$data = array(
					'username' => $username,
					'password' => password_hash($this->input->post('password'), PASSWORD_DEFAULT),
					'name'     => $this->input->post('name'),
					'role'     => $this->input->post('role'),
				);

				$this->admins_mdl->save($data);

				$response = array( 'type' => 'success', 'message' => 'Usuario registrado', 'tk' => $this->issueToken() );
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

			// state-changing GET: the link carries the page's form token (?tk=)
			if( !$this->input->get('tk') || $this->input->get('tk') !== $this->session->userdata('tk') ){

				throw new \Exception('Sesión inválida, recargue la página e intente de nuevo');
			}

			$id_admin = ctype_digit((string) $id_admin) ? (int) $id_admin : 0;

			if( $id_admin === (int) $this->id_admin ){

				throw new \Exception('No puede eliminar su propio usuario');
			}

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

			if( $admin->role === 'super' && $this->admins_mdl->countActiveByRole('super') <= 1 ){

				throw new \Exception('Debe existir al menos un usuario super');
			}

			$this->admins_mdl->delete($id_admin);
			$this->issueToken();

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
