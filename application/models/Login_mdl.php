<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login_mdl extends MX_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function check( $username, $password ){

		return $this->db->get_where( 'admins', array( 'username' => $username, 'password' => sha1($password) ) )->row();
	}

	public function getDatosAcceso(){

		return $this->db->get('admins')->row();
	}

	public function saveAcceso( $data ){

		$this->db->update( 'admins', $data );
	}
}