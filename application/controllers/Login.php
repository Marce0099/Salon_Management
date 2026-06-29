<?php 

	if( !defined('BASEPATH') ){ exit(); }

	class Login extends MX_Controller{

		public function __construct(){

			parent::__construct();
			$this->load->model('login_mdl');
		}

		public function index(){

			if( $this->session->userdata('id_admin') ){

				redirect('panel');
			}
			else{

				$this->form();
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

				redirect('panel');
			}
			else{

				$this->form( array('error' => TRUE ) );
			}
		}

		public function out(){

			$this->session->set_userdata('id_admin','');
			$this->session->set_userdata('admin_name','');
			redirect('login');
		}
	}

?>