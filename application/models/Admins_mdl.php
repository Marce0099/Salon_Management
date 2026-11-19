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

		$id = $this->db->insert_id();
		$this->audit->change('admin.create', 'admin', $id, NULL, $data, array('summary' => 'Usuario creado: '.(isset($data['username']) ? $data['username'] : '').' ('.(isset($data['role']) ? $data['role'] : '').')'));

		return $id;
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

		$before = $this->audit->row('admins', 'id_admin', $id_admin);

		$this->db->trans_start();
		$this->db->where('id_admin', $id_admin);
		$this->db->update('admins', ["active" => 0]);
		$this->audit->log('admin.deactivate', 'admin', $id_admin, array(
			'summary' => 'Usuario desactivado: '.($before ? $before['username'] : $id_admin),
			'before' => array('active' => 1, 'username' => $before ? $before['username'] : NULL, 'role' => $before ? $before['role'] : NULL), 'after' => array('active' => 0)
		));
		$this->db->trans_complete();
	}
}
