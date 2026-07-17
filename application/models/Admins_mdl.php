<?php if( !defined('BASEPATH') ){ exit(); }

class Admins_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getAll(){

		$this->db->where('active', 1);
		return $this->db->get('admins')->result();
	}

	public function getAdmin($id_admin){

		return $this->db->get_where('admins', array('id_admin' => $id_admin))->row();
	}

	public function usernameExists($username){

		return $this->db->get_where('admins', array('username' => $username))->row();
	}

	public function save($data){

		$this->db->insert('admins', $data);

		return $this->db->insert_id();
	}

	public function countActive(){

		$this->db->where('active', 1);
		return $this->db->count_all_results('admins');
	}

	public function countActiveByRole($role){

		$this->db->where('active', 1);
		$this->db->where('role', $role);
		return $this->db->count_all_results('admins');
	}

	public function delete($id_admin){

		$this->db->where('id_admin', $id_admin);
		$this->db->update('admins', ["active" => 0]);
	}
}
