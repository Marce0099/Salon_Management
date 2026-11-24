<?php if( !defined('BASEPATH') ){ exit(); }

/** Printers (where jobs go) and the print queue listing. Soft delete via `active`. */
class Printers_mdl extends CI_Model{

	public function get($id){

		return $this->db->where('id_printer', (int) $id)->get('printers')->row();
	}

	public function all($inactive = FALSE){

		return $this->db->query('SELECT p.*,
				(SELECT COUNT(*) FROM print_jobs j WHERE j.id_printer = p.id_printer AND j.status = "failed") AS n_failed,
				(SELECT j.status FROM print_jobs j WHERE j.id_printer = p.id_printer ORDER BY j.id_job DESC LIMIT 1) AS last_status
			FROM printers p WHERE p.active = ? ORDER BY p.role, p.name', array($inactive ? 0 : 1))->result();
	}

	public function nameTaken($name, $exclude = 0){

		$this->db->where('name', $name);
		if( $exclude ){ $this->db->where('id_printer !=', (int) $exclude); }

		return $this->db->get('printers')->row();
	}

	/** Creates/updates. Returns the id or 0. */
	public function save($id, $data){

		$this->db->trans_begin();

		$before = $id ? $this->audit->row('printers', 'id_printer', (int) $id) : NULL;

		if( $id ){
			$this->db->where('id_printer', (int) $id)->update('printers', $data);
		}
		else{
			$this->db->insert('printers', $data);
			$id = (int) $this->db->insert_id();
		}

		$this->audit->log($before ? 'printer.update' : 'printer.create', 'printer', $id, array(
			'summary' => ($before ? 'Impresora modificada: ' : 'Impresora creada: ').$data['name'], 'before' => $before, 'after' => $this->audit->row('printers', 'id_printer', (int) $id)));

		if( $this->db->trans_status() === FALSE ){
			$this->db->trans_rollback();
			return 0;
		}

		$this->db->trans_commit();
		return (int) $id;
	}

	public function setActive($id, $active){

		$this->db->trans_start();
		$this->db->where('id_printer', (int) $id)->update('printers', array('active' => $active ? 1 : 0));
		$this->audit->log('printer.toggle', 'printer', $id, array('summary' => ($active ? 'Impresora reactivada #' : 'Impresora desactivada #').(int) $id, 'after' => array('active' => $active ? 1 : 0)));
		$this->db->trans_complete();
	}

	/* ---------- queue ---------- */

	public function job($id){

		return $this->db->where('id_job', (int) $id)->get('print_jobs')->row();
	}

	/** Newest first, without the bytes. `$status` '' = all. */
	public function jobs($status = '', $limit = 100){

		$this->db->select('id_job, id_printer, printer_name, kind, ref_type, ref_id, title, status, attempts, last_error, id_admin, created_at, printed_at, OCTET_LENGTH(payload) AS size', FALSE);
		if( in_array($status, array('pending', 'printing', 'printed', 'failed'), TRUE) ){
			$this->db->where('status', $status);
		}

		return $this->db->order_by('id_job', 'DESC')->limit((int) $limit)->get('print_jobs')->result();
	}

	public function countByStatus(){

		$out = array('pending' => 0, 'printing' => 0, 'printed' => 0, 'failed' => 0);
		foreach( $this->db->query('SELECT status, COUNT(*) AS n FROM print_jobs GROUP BY status')->result() as $r ){
			$out[$r->status] = (int) $r->n;
		}

		return $out;
	}
}
