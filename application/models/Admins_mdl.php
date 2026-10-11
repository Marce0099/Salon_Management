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

		// UNIQUE(username) backstops the controller's usernameExists() pre-check against two
		// concurrent saves racing past it; surface that as the same friendly message
		$debug = $this->db->db_debug;
		$this->db->db_debug = FALSE;

		try {
			$ok = $this->db->insert('admins', $data);
			$err = $this->db->error();
		} catch (\Throwable $th) {
			$ok = FALSE;
			$err = array('code' => $th->getCode(), 'message' => $th->getMessage());
		}

		$this->db->db_debug = $debug;

		if( !$ok ){

			if( (int) $err['code'] === 1062 || stripos((string) $err['message'], 'duplicate entry') !== FALSE ){

				throw new \Exception('Ya existe un usuario con ese nombre');
			}

			throw new \Exception('Ha ocurrido un error guardando los datos');
		}

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
