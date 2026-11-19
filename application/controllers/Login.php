<?php 

	if( !defined('BASEPATH') ){ exit(); }

	class Login extends MX_Controller{

		public function __construct(){

			parent::__construct();
			$this->load->model('login_mdl');
			$this->load->model('settings_mdl');
		}

		public function index(){

			if( $this->session->userdata('id_admin') ){

				redirect('panel');
			}
			else{

				$this->form( array('company_name' => $this->settings_mdl->get('company_name', 'Conceptos')) );
			}
			
		}

		private function form($view_data=array()){

			$this->load->view('form_login',$view_data);
		}

		public function check(){

			$username = $this->input->post('username');
			$password = $this->input->post('password');

			$result = $this->login_mdl->check($username, $password);


			if( !empty($result) ){
				
				$this->session->set_userdata('id_admin', $result->id_admin);
				$this->session->set_userdata('admin_name', $result->name);
				$this->session->set_userdata('role', $result->role);

				$this->audit->log('login.success', 'admin', $result->id_admin, array('summary' => 'Inicio de sesión', 'best_effort' => TRUE));

				redirect('panel');
			}
			else{

				$this->audit->log('login.fail', 'admin', NULL, array('result' => 'denied', 'summary' => 'Intento de acceso fallido', 'after' => array('username' => substr((string)$username, 0, 60)), 'actor' => array('id_admin' => NULL, 'admin_name' => NULL, 'role' => NULL), 'best_effort' => TRUE));

				$this->form( array('error' => TRUE, 'company_name' => $this->settings_mdl->get('company_name', 'Conceptos') ) );
			}
		}

		public function out(){

			if( $this->session->userdata('id_admin') ){

				$this->audit->log('logout', 'admin', $this->session->userdata('id_admin'), array('summary' => 'Cierre de sesión', 'best_effort' => TRUE));
			}

			$this->session->set_userdata('id_admin','');
			$this->session->set_userdata('admin_name','');
			$this->session->set_userdata('role','');
			redirect('login');
		}
	}

?>