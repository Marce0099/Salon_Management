<?php if( !defined('BASEPATH') ){ exit(); }

/** Printers (where jobs go) and the print queue listing. Soft delete via `active`. */
class Printers_mdl extends CI_Model{

	public function get($id){

		return $this->db->where('id_printer', (int) $id)->get('printers')->row();
	}

	public function all($inactive = FALSE){

		return $this->db->query('SELECT p.*, a.name AS agent_name, (a.last_seen_at >= NOW() - INTERVAL 45 SECOND) AS agent_online,
				(SELECT COUNT(*) FROM print_jobs j WHERE j.id_printer = p.id_printer AND j.status = "failed") AS n_failed,
				(SELECT j.status FROM print_jobs j WHERE j.id_printer = p.id_printer ORDER BY j.id_job DESC LIMIT 1) AS last_status,
				(SELECT GROUP_CONCAT(c.name_category ORDER BY c.name_category SEPARATOR ", ") FROM service_category c WHERE c.id_printer = p.id_printer) AS categories
			FROM printers p LEFT JOIN print_agents a ON a.id_agent = p.id_agent WHERE p.active = ? ORDER BY p.role, p.name', array($inactive ? 0 : 1))->result();
	}

	/** Active printers of a role, oldest first (the first kitchen one is the default). */
	public function activeByRole($role){

		return $this->db->where(array('role' => $role, 'active' => 1))->order_by('id_printer')->get('printers')->result();
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

	/* ---------- agents (print stations) ---------- */

	public function agent($id){

		return $this->db->where('id_agent', (int) $id)->get('print_agents')->row();
	}

	public function agents(){

		return $this->db->query('SELECT a.*, (a.last_seen_at >= NOW() - INTERVAL 45 SECOND) AS online,
			(SELECT COUNT(*) FROM printers p WHERE p.id_agent = a.id_agent AND p.active = 1) AS n_printers
			FROM print_agents a ORDER BY a.active DESC, a.name')->result();
	}

	public function agentNameTaken($name, $exclude = 0){

		$this->db->where('name', $name);
		if( $exclude ){ $this->db->where('id_agent !=', (int) $exclude); }

		return $this->db->get('print_agents')->row();
	}

	/** Creates a station; only the sha256 of its token is stored. Returns the id or 0. */
	public function createAgent($name, $token){

		$this->db->trans_start();
		$this->db->insert('print_agents', array('name' => $name, 'token_hash' => hash('sha256', $token)));
		$id = (int) $this->db->insert_id();
		$this->audit->log('print_agent.create', 'print_agent', $id, array('summary' => 'Estación de impresión creada: '.$name, 'after' => array('name' => $name)));
		$this->db->trans_complete();

		return $this->db->trans_status() === FALSE ? 0 : $id;
	}

	public function rotateAgentToken($id, $token){

		$this->db->trans_start();
		$this->db->where('id_agent', (int) $id)->update('print_agents', array('token_hash' => hash('sha256', $token), 'last_seen_at' => NULL));
		$this->audit->log('print_agent.rotate', 'print_agent', $id, array('summary' => 'Token de la estación #'.(int) $id.' regenerado (el anterior deja de funcionar)'));
		$this->db->trans_complete();

		return $this->db->trans_status() !== FALSE;
	}

	public function setAgentActive($id, $active){

		$this->db->trans_start();
		$this->db->where('id_agent', (int) $id)->update('print_agents', array('active' => $active ? 1 : 0));
		$this->audit->log('print_agent.toggle', 'print_agent', $id, array('summary' => ($active ? 'Estación reactivada #' : 'Estación desactivada #').(int) $id, 'after' => array('active' => $active ? 1 : 0)));
		$this->db->trans_complete();
	}

	/* ---------- queue ---------- */

	public function job($id){

		return $this->db->where('id_job', (int) $id)->get('print_jobs')->row();
	}

	/** Newest first, without the bytes. `$status` '' = all. */
	public function jobs($status = '', $limit = 100){

		$this->db->select('id_job, id_printer, printer_name, kind, ref_type, ref_id, title, status, attempts, last_error, id_admin, created_at, printed_at, OCTET_LENGTH(payload) AS size', FALSE);
		if( in_array($status, array('pending', 'printing', 'printed', 'failed', 'cancelled'), TRUE) ){
			$this->db->where('status', $status);
		}

		return $this->db->order_by('id_job', 'DESC')->limit((int) $limit)->get('print_jobs')->result();
	}

	public function countByStatus(){

		$out = array('pending' => 0, 'printing' => 0, 'printed' => 0, 'failed' => 0, 'cancelled' => 0);
		foreach( $this->db->query('SELECT status, COUNT(*) AS n FROM print_jobs GROUP BY status')->result() as $r ){
			$out[$r->status] = (int) $r->n;
		}

		return $out;
	}
}
