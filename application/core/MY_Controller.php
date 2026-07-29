<?php (defined('BASEPATH')) OR exit('No direct script access allowed');

#[\AllowDynamicProperties]
class MY_Controller extends CI_Controller{
 
    public function __construct(){

    	parent::__construct();
    }
}

class Admin_Controller extends MX_Controller{

	protected $header_data = array();
	//protected $sidebar_data = array();
	protected $content_data = array();
	protected $footer_data = array();
	protected $id_admin = 0;
	protected $role = '';

	public function __construct(){

		parent::__construct();
		$this->id_admin = $this->session->userdata('id_admin');
		$this->role = $this->session->userdata('role');

		if( !$this->id_admin ){

			redirect('login');
		}

		$this->load->model('settings_mdl');
		$this->header_data['company_name'] = $this->settings_mdl->get('company_name', 'Conceptos');
	}

	protected function renderPanel( $content_view = '' ){

		$content_view = empty($content_view) ? 'panel.php' : $content_view . '.php';
		//debug($this->content_data);
		$this->load->view('header',$this->header_data);
		//$this->load->view('sidebar',$this->sidebar_data);
		$this->load->view($content_view,$this->content_data);
		$this->load->view('footer',$this->footer_data);
	}

	public function get_userID(){
		return $this->id_admin;
	}

	public function is_admin(){
		return $this->role === 'administrador' || $this->role === 'super';
	}

	public function is_super(){
		return $this->role === 'super';
	}

}