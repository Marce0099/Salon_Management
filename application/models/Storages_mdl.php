<?php if( !defined('BASEPATH') ){ exit(); }

/** Storage places (bodega, refrigerador...) used by the shift inventory report. Soft delete via `active`. */
class Storages_mdl extends CI_Model{

	public function get($id){

		return $this->db->where('id_storage', (int) $id)->get('inv_storages')->row();
	}

	public function all($inactive = FALSE){

		return $this->db->where('active', $inactive ? 0 : 1)->order_by('sort_order')->order_by('name')->get('inv_storages')->result();
	}

	public function nameTaken($name, $exclude = 0){

		$this->db->where('name', $name);
		if( $exclude ){ $this->db->where('id_storage !=', (int) $exclude); }

		return $this->db->get('inv_storages')->row();
	}

	/** Creates/updates. Returns the id or 0. */
	public function save($id, $name, $description, $sort){

		$this->db->trans_begin();

		$before = $id ? $this->audit->row('inv_storages', 'id_storage', (int) $id) : NULL;
		$data = array('name' => $name, 'description' => $description, 'sort_order' => (int) $sort);

		if( $id ){
			$this->db->where('id_storage', (int) $id)->update('inv_storages', $data);
		}
		else{
			$this->db->insert('inv_storages', $data);
			$id = (int) $this->db->insert_id();
		}

		$this->audit->log($before ? 'storage.update' : 'storage.create', 'inv_storage', $id, array(
			'summary' => ($before ? 'Almacén modificado: ' : 'Almacén creado: ').$name, 'before' => $before, 'after' => $this->audit->row('inv_storages', 'id_storage', (int) $id)));

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return 0;
		}

		$this->db->trans_commit();
		return (int) $id;
	}

	public function setActive($id, $active){

		$this->db->trans_start();
		$this->db->where('id_storage', (int) $id)->update('inv_storages', array('active' => $active ? 1 : 0));
		$this->audit->log('storage.toggle', 'inv_storage', $id, array('summary' => ($active ? 'Almacén reactivado #' : 'Almacén desactivado #').(int) $id, 'after' => array('active' => $active ? 1 : 0)));
		$this->db->trans_complete();
	}
}
