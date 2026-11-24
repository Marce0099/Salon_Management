<?php if( !defined('BASEPATH') ){ exit(); }

require_once FCPATH.'print/autoload.php';

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/**
 * Print queue (migration 030, plan in .claude/docs/printing_plan.md).
 *
 * Every thermal print is RENDERED ONCE to ESC/POS bytes (canvas()/bytes()), stored as a `print_jobs` row and then DELIVERED by the
 * transport of the chosen printer: `network` = raw TCP (host:9100, short timeouts) or `windows` = Windows printer name / smb:// path
 * on the server PC. A failure never throws to the caller: the job stays `failed` with its error and can be retried or reprinted
 * from the stored bytes. The Rust/Tauri backend must keep this table contract.
 *
 *   list($p, $c) = $this->printing->canvas();   // build the ticket with $p->text(...)
 *   $res = $this->printing->send('kitchen', 'comanda', 'order', $id_order, 'Comanda #5', $this->printing->bytes($p, $c), $id_admin);
 *   // $res = array('ok' => bool, 'error' => string, 'id_job' => int)
 */
class Printing{

	const CONNECT_TIMEOUT = 3;   // seconds to open the TCP connection
	const IO_TIMEOUT      = 5;   // seconds per write
	const STALE_CLAIM     = 60;  // a job stuck in `printing` for this long can be claimed again

	private $CI;

	public function __construct(){

		$this->CI =& get_instance();
	}

	/* ---------- rendering ---------- */

	/** Escpos Printer writing to memory. Returns array(Printer, DummyPrintConnector). */
	public function canvas(){

		$c = new DummyPrintConnector();
		$p = new Printer($c);
		$p->initialize();

		return array($p, $c);
	}

	/** Closes the canvas and returns the ESC/POS bytes. */
	public function bytes($p, $c){

		$data = $c->getData();
		$p->close();

		return $data;
	}

	/** Printer-safe text (the thermal code page has no accents). */
	public function ascii($s){

		return strtr((string) $s, array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
			'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
			'¿' => '?', '¡' => '!',
		));
	}

	/** Bytes of the test page of a printer row. */
	public function testPage($printer){

		list($p, $c) = $this->canvas();

		$p->setJustification(Printer::JUSTIFY_CENTER);
		$p->setTextSize(2, 2);
		$p->text("PRUEBA\n");
		$p->setTextSize(1, 1);
		$p->text($this->ascii($printer->name)."\n");
		$p->feed(1);
		$p->setJustification(Printer::JUSTIFY_LEFT);
		$p->text("Rol: ".($printer->role === 'kitchen' ? 'Cocina' : 'Recibos')."\n");
		$p->text("Conexion: ".$printer->conn_type.' '.$printer->address.($printer->conn_type === 'network' ? ':'.(int) $printer->port : '')."\n");
		$p->text("Fecha: ".date('d/m/Y h:i A')."\n");
		$p->text("------------------------------------------\n");
		$p->text("Acentos: ".$this->ascii("áéíóú ñ ¿?")."\n");
		$p->setEmphasis(TRUE);
		$p->text("Negrita\n");
		$p->setEmphasis(FALSE);
		$p->setTextSize(2, 2);
		$p->text("Texto doble\n");
		$p->setTextSize(1, 1);
		$p->text("------------------------------------------\n");
		$p->text("Si lee esto, la impresora funciona.\n");
		$p->feed(3);
		$p->cut();

		return $this->bytes($p, $c);
	}

	/* ---------- queue ---------- */

	/** First active printer of a role = the default one of that role. */
	public function printerFor($role){

		return $this->CI->db->where('role', $role)->where('active', 1)->order_by('id_printer')->limit(1)->get('printers')->row();
	}

	/**
	 * Kitchen routing (plan step 3): for each product id, the kitchen printer of its menu category
	 * (`service_category.id_printer`). No category printer, or one that is inactive / not a kitchen printer,
	 * falls back to the default kitchen printer so a ticket is never lost. Returns array(id_product => printer row|NULL).
	 */
	public function routeProducts($ids){

		$ids = array_values(array_unique(array_map('intval', (array) $ids)));
		$default = $this->printerFor('kitchen');
		$out = array();

		if( !$ids ){ return $out; }

		$printers = array();
		foreach( $this->CI->db->where('role', 'kitchen')->where('active', 1)->get('printers')->result() as $p ){
			$printers[(int) $p->id_printer] = $p;
		}

		$sql = 'SELECT p.id_product, c.id_printer FROM products p LEFT JOIN service_category c ON c.id_service_category = p.id_service_category WHERE p.id_product IN ('.implode(',', $ids).')';
		foreach( $this->CI->db->query($sql)->result() as $r ){
			$id_printer = (int) $r->id_printer;
			$out[(int) $r->id_product] = ($id_printer && isset($printers[$id_printer])) ? $printers[$id_printer] : $default;
		}

		foreach( $ids as $id ){
			if( !isset($out[$id]) ){ $out[$id] = $default; } // product no longer in the catalog
		}

		return $out;
	}

	/** Receipt printer for THIS device: the one chosen in /printers (cookie `pos_receipt_printer`), else the default receipt printer. */
	public function receiptPrinter(){

		$id = isset($_COOKIE['pos_receipt_printer']) ? (int) $_COOKIE['pos_receipt_printer'] : 0;

		if( $id ){
			$p = $this->CI->db->where('id_printer', $id)->where('role', 'receipt')->where('active', 1)->get('printers')->row();
			if( $p ){ return $p; }
		}

		return $this->printerFor('receipt');
	}

	/** Stores a job (status pending). `$printer` = row, or NULL to pick the role's printer. Returns the job id. */
	public function enqueue($role, $kind, $ref_type, $ref_id, $title, $bytes, $id_admin = NULL, $printer = NULL){

		if( $printer === NULL ){
			$printer = $this->printerFor($role);
		}

		$db = $this->CI->db;
		$db->set(array(
			'id_printer'   => $printer ? (int) $printer->id_printer : NULL,
			'printer_name' => $printer ? $printer->name : NULL,
			'kind'         => $kind,
			'ref_type'     => $ref_type,
			'ref_id'       => $ref_id ? (int) $ref_id : NULL,
			'title'        => mb_substr($title, 0, 120, 'UTF-8'),
			'id_admin'     => $id_admin ? (int) $id_admin : NULL,
		));
		// binary bytes cannot travel as a literal on the 3-byte utf8 connection: ship them as hex
		$db->set('payload', 'UNHEX('.$db->escape(bin2hex((string) $bytes)).')', FALSE);
		$db->insert('print_jobs');

		return (int) $db->insert_id();
	}

	/** enqueue() + deliver(). Never throws. */
	public function send($role, $kind, $ref_type, $ref_id, $title, $bytes, $id_admin = NULL, $printer = NULL){

		$id_job = $this->enqueue($role, $kind, $ref_type, $ref_id, $title, $bytes, $id_admin, $printer);
		list($ok, $error) = $this->deliver($id_job);

		return array('ok' => $ok, 'error' => $error, 'id_job' => $id_job);
	}

	/** Sends a stored job (pending/failed, or stuck in printing). Returns array(ok, error). */
	public function deliver($id_job){

		$db = $this->CI->db;

		$db->query('UPDATE print_jobs SET status = "printing", claimed_at = NOW(), attempts = attempts + 1
			WHERE id_job = ? AND (status IN ("pending","failed") OR (status = "printing" AND claimed_at < NOW() - INTERVAL '.self::STALE_CLAIM.' SECOND))', array((int) $id_job));

		if( $db->affected_rows() !== 1 ){
			return array(FALSE, 'The job is already printing or was already printed');
		}

		$job = $db->where('id_job', (int) $id_job)->get('print_jobs')->row();
		$printer = $job->id_printer ? $db->where('id_printer', (int) $job->id_printer)->get('printers')->row() : NULL;

		// a printer plugged into another PC: leave the job for its agent to collect (see agentClaim)
		if( $printer && $printer->active && $printer->conn_type === 'agent' && $job->payload !== NULL && $job->payload !== '' ){
			return $this->queueForAgent($job, $printer);
		}

		if( !$printer ){
			$error = 'No printer configured for this job';
		}
		elseif( !$printer->active ){
			$error = 'Printer "'.$printer->name.'" is disabled';
		}
		elseif( $job->payload === NULL || $job->payload === '' ){
			$error = 'The job has no content';
		}
		else{
			$error = $this->transport($printer, $job->payload);
		}

		$this->finish($job, $error);

		return array($error === '', $error);
	}

	/* ---------- agents (printers on other PCs) ---------- */

	const AGENT_ONLINE  = 45;  // seconds since the last poll to count a station as online
	const AGENT_MAX_TRY = 5;   // claims before a job is given up

	public function agentOnline($id_agent){

		$r = $this->CI->db->query('SELECT (last_seen_at >= NOW() - INTERVAL '.self::AGENT_ONLINE.' SECOND) AS online FROM print_agents WHERE id_agent = ?', array((int) $id_agent))->row();

		return $r && (int) $r->online === 1;
	}

	/** Records that the agent is alive (every poll). */
	public function agentTouch($id_agent, $ip, $version){

		$this->CI->db->query('UPDATE print_agents SET last_seen_at = NOW(), last_ip = ?, version = ? WHERE id_agent = ?',
			array(mb_substr((string) $ip, 0, 45), mb_substr(preg_replace('/[^0-9A-Za-z._-]/', '', (string) $version), 0, 20), (int) $id_agent));
	}

	/** Puts the job back in `pending` so its agent collects it. Returns array(ok, error): ok = the station is online right now. */
	private function queueForAgent($job, $printer){

		$db = $this->CI->db;
		$agent = $db->where('id_agent', (int) $printer->id_agent)->get('print_agents')->row();

		if( !$agent || !$agent->active ){
			$error = 'Print station '.($agent ? '"'.$agent->name.'" ' : '').'is disabled or missing';
			$this->finish($job, $error);
			return array(FALSE, $error);
		}

		$online = $this->agentOnline($agent->id_agent);
		$note = $online ? NULL : 'Station "'.$agent->name.'" is offline; the ticket stays queued and prints when it reconnects';

		$db->query('UPDATE print_jobs SET status = "pending", claimed_at = NULL, attempts = GREATEST(attempts - 1, 0), last_error = ? WHERE id_job = ?', array($note, (int) $job->id_job));

		if( $job->kind === 'comanda' && $job->ref_type === 'order' && $job->ref_id ){ $this->refreshOrderStatus((int) $job->ref_id); }

		return array($online, (string) $note);
	}

	/** Hands the agent its waiting jobs (claimed atomically). Returns a list of array(id_job, printer, title, payload(base64)). */
	public function agentClaim($id_agent, $limit = 10){

		$db = $this->CI->db;

		$ids = $db->query('SELECT j.id_job FROM print_jobs j JOIN printers p ON p.id_printer = j.id_printer
			WHERE p.id_agent = ? AND p.active = 1 AND p.conn_type = "agent" AND (j.status = "pending" OR (j.status = "printing" AND j.claimed_at < NOW() - INTERVAL '.self::STALE_CLAIM.' SECOND))
			ORDER BY j.id_job LIMIT '.(int) $limit, array((int) $id_agent))->result();

		$out = array();

		foreach( $ids as $r ){

			$db->query('UPDATE print_jobs SET status = "printing", claimed_at = NOW(), attempts = attempts + 1
				WHERE id_job = ? AND (status = "pending" OR (status = "printing" AND claimed_at < NOW() - INTERVAL '.self::STALE_CLAIM.' SECOND))', array((int) $r->id_job));

			if( $db->affected_rows() !== 1 ){ continue; }

			$job = $db->where('id_job', (int) $r->id_job)->get('print_jobs')->row();

			if( (int) $job->attempts > self::AGENT_MAX_TRY ){
				$this->finish($job, 'The station did not confirm the print after '.self::AGENT_MAX_TRY.' attempts');
				continue;
			}

			$printer = $db->where('id_printer', (int) $job->id_printer)->get('printers')->row();

			$out[] = array('id_job' => (int) $job->id_job, 'printer' => $printer->address, 'title' => $job->title, 'payload' => base64_encode($job->payload));
		}

		return $out;
	}

	/** The agent reports what happened to a job it claimed. Returns array(ok, message). */
	public function agentResult($id_agent, $id_job, $ok, $error){

		$db = $this->CI->db;

		$job = $db->where('id_job', (int) $id_job)->get('print_jobs')->row();
		$printer = $job && $job->id_printer ? $db->where('id_printer', (int) $job->id_printer)->get('printers')->row() : NULL;

		if( !$job || !$printer || (int) $printer->id_agent !== (int) $id_agent ){ return array(FALSE, 'Unknown job'); }
		if( $job->status !== 'printing' ){ return array(FALSE, 'The job is not waiting for a result'); }

		$this->finish($job, $ok ? '' : ($this->clean($error) !== '' ? $this->clean($error) : 'The station reported an error'));

		return array(TRUE, '');
	}

	/** Clones a job (same bytes) and delivers the copy. Returns array(ok, error, id_job). */
	public function reprint($id_job, $id_admin = NULL){

		$job = $this->CI->db->where('id_job', (int) $id_job)->get('print_jobs')->row();
		if( !$job || $job->payload === NULL ){
			return array(FALSE, 'The job has no content', 0);
		}

		$printer = $job->id_printer ? $this->CI->db->where('id_printer', (int) $job->id_printer)->get('printers')->row() : NULL;
		// a deleted printer falls back to the first active one of the job's role
		$res = $this->send($job->kind === 'comanda' ? 'kitchen' : 'receipt', $job->kind, $job->ref_type, $job->ref_id, $job->title, $job->payload, $id_admin, $printer ? $printer : NULL);

		return array($res['ok'], $res['error'], $res['id_job']);
	}

	private function finish($job, $error){

		$db = $this->CI->db;

		$db->where('id_job', (int) $job->id_job)->update('print_jobs', array(
			'status'     => $error === '' ? 'printed' : 'failed',
			'last_error' => $error === '' ? NULL : mb_substr($error, 0, 255, 'UTF-8'),
			'printed_at' => $error === '' ? date('Y-m-d H:i:s') : NULL,
		));

		// the kitchen screen reads the outcome from the order itself
		if( $job->kind === 'comanda' && $job->ref_type === 'order' && $job->ref_id ){
			$this->refreshOrderStatus((int) $job->ref_id);
		}

		if( $error !== '' ){
			log_message('error', 'print job #'.$job->id_job.' ('.$job->title.') failed: '.$error);
		}
	}

	/**
	 * An order can have one comanda per printer (kitchen, bar...). Its print status summarises the LATEST job of each printer:
	 * any failed = failed (error names the printer), any waiting = pending, else printed. Reprints supersede older jobs.
	 */
	public function refreshOrderStatus($id_order){

		$db = $this->CI->db;

		$rows = $db->query('SELECT j.status, j.last_error, j.printer_name FROM print_jobs j
			JOIN (SELECT id_printer, MAX(id_job) AS mj FROM print_jobs WHERE kind = "comanda" AND ref_type = "order" AND ref_id = ? GROUP BY id_printer) x ON x.mj = j.id_job
			ORDER BY j.id_job', array((int) $id_order))->result();

		if( !$rows ){ return; }

		$failed = array(); $waiting = FALSE;
		foreach( $rows as $r ){
			if( $r->status === 'failed' ){ $failed[] = ($r->printer_name !== NULL ? $r->printer_name.': ' : '').(string) $r->last_error; }
			elseif( $r->status !== 'printed' ){ $waiting = TRUE; }
		}

		if( $failed ){ $data = array('print_status' => 'failed', 'print_error' => mb_substr(implode(' | ', $failed), 0, 255, 'UTF-8')); }
		elseif( $waiting ){ $data = array('print_status' => 'pending', 'print_error' => NULL); }
		else{ $data = array('print_status' => 'printed', 'print_error' => NULL, 'printed_at' => date('Y-m-d H:i:s')); }

		$db->where('id_order', (int) $id_order)->update('orders_kitchen', $data);
	}

	/* ---------- transports (return '' on success, else the error text) ---------- */

	private function transport($printer, $bytes){

		try{
			return $printer->conn_type === 'network'
				? $this->sendNetwork($printer->address, (int) $printer->port, $bytes)
				: $this->sendWindows($printer->address, $bytes);
		}
		catch( \Throwable $e ){
			return $this->clean($e->getMessage());
		}
	}

	/** Raw TCP (port 9100 of Ethernet/Wi-Fi printers). Short timeouts so an unplugged printer cannot hang the request. */
	private function sendNetwork($host, $port, $bytes){

		if( !preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,148}[A-Za-z0-9])?$/', $host) || $port < 1 || $port > 65535 ){
			return 'Invalid printer address';
		}

		$errno = 0; $errstr = '';
		$fp = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, self::CONNECT_TIMEOUT);

		if( !$fp ){
			return 'Could not connect to '.$host.':'.$port.' ('.$this->clean($errstr !== '' ? $errstr : 'error '.$errno).')';
		}

		stream_set_timeout($fp, self::IO_TIMEOUT);

		$len = strlen($bytes);
		$off = 0;
		while( $off < $len ){

			$n = @fwrite($fp, substr($bytes, $off, 8192));
			if( $n === FALSE || $n === 0 ){
				fclose($fp);
				return 'The printer stopped accepting data ('.$off.' of '.$len.' bytes sent)';
			}
			$off += $n;
		}

		@fflush($fp);
		fclose($fp);

		return '';
	}

	/** Windows printer name on the server PC (or smb://host/printer). */
	private function sendWindows($name, $bytes){

		$prev = error_reporting(0); // the connector warns on failure; we report the exception instead
		ob_start();

		try{
			$c = new WindowsPrintConnector($name);
			$c->write($bytes);
			$c->finalize();
			$error = '';
		}
		catch( \Throwable $e ){
			$error = $this->clean($e->getMessage());
		}

		ob_end_clean();
		error_reporting($prev);

		return $error;
	}

	/** OS error texts come in the machine's ANSI code page; keep them valid UTF-8 and short. */
	private function clean($s){

		$s = (string) $s;
		if( !mb_check_encoding($s, 'UTF-8') ){
			$s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
		}

		return mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, 240, 'UTF-8');
	}
}
