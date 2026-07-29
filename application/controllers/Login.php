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

				redirect('panel');
			}
			else{

				$this->form( array('error' => TRUE, 'company_name' => $this->settings_mdl->get('company_name', 'Conceptos') ) );
			}
		}

		public function out(){

			$this->session->set_userdata('id_admin','');
			$this->session->set_userdata('admin_name','');
			$this->session->set_userdata('role','');
			redirect('login');
		}
	}

?>