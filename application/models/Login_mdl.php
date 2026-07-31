<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Login_mdl extends MX_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function check( $username, $password ){

		$admin = $this->db->get_where( 'admins', array( 'username' => $username, 'active' => 1 ) )->row();

		if( !$admin ){

			return null;
		}

		$password = $password ?? '';

		// Legacy SHA1 hash (40 hex chars) - verify old-style, then transparently upgrade
		if( strlen($admin->password) === 40 && ctype_xdigit($admin->password) ){

			if( !hash_equals($admin->password, sha1($password)) ){

				return null;
			}

			$admin->password = password_hash($password, PASSWORD_DEFAULT);
			$this->db->update( 'admins', array('password' => $admin->password), array('id_admin' => $admin->id_admin) );

			return $admin;
		}

		return password_verify($password, $admin->password) ? $admin : null;
	}

	public function getDatosAcceso(){

		return $this->db->get('admins')->row();
	}

	public function saveAcceso( $data ){

		$this->db->update( 'admins', $data );
	}
}