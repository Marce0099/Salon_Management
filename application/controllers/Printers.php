<?php if( !defined('BASEPATH') ){ exit(); }

/** Printers (receipt / kitchen), test print and the print queue (retry / reprint). Admin only. */
class Printers extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('printers_mdl');
		$this->load->library('printing');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	private function ok($message, $extra = array()){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		$this->json(array_merge(array('type' => 'success', 'message' => $message, 'tk' => $tk), $extra));
	}

	private function token(){

		$tk = $this->session->userdata('tk');
		if( !$tk ){
			$tk = generateToken();
			$this->session->set_userdata('tk', $tk);
		}

		return $tk;
	}

	private function requirePost(){

		if( !formSubmited() ){
			$this->fail('Sesión inválida, recargue la página e intente de nuevo');
		}
	}

	private function cleanText($v, $max){

		$v = trim(preg_replace('/\s+/', ' ', (string) $v));
		if( $v === '' || mb_strlen($v, 'UTF-8') > $max || preg_match('/[<>]/', $v) || preg_match('/[\x{10000}-\x{10FFFF}]/u', $v) ){ return NULL; }

		return $v;
	}

	/* ---------- printers ---------- */

	public function index(){

		$show_inactive = (int) $this->input->get('inactive') === 1;

		$this->footer_data = load_data($this->session);
		$this->content_data = array(
			'printers'      => $this->printers_mdl->all($show_inactive),
			'show_inactive' => $show_inactive,
			'counts'        => $this->printers_mdl->countByStatus(),
			'tk'            => $this->token(),
			'agents'        => $this->printers_mdl->agents(),
			'server_hint'   => $this->serverHint(),
			'receipt_printers' => $this->printers_mdl->activeByRole('receipt'),
			'station'       => isset($_COOKIE['pos_receipt_printer']) ? (int) $_COOKIE['pos_receipt_printer'] : 0,
			'default_kitchen' => ($k = $this->printers_mdl->activeByRole('kitchen')) ? (int) $k[0]->id_printer : 0,
		);
		$this->renderPanel('printers');
	}

	/** This device prints its receipts on the chosen receipt printer (cookie, one year). Empty = the default receipt printer. */
	public function set_station(){

		$this->requirePost();

		$id = (int) $this->input->post('id_printer');
		$p = $id ? $this->printers_mdl->get($id) : NULL;

		if( $id && (!$p || $p->role !== 'receipt' || !$p->active) ){ $this->fail('Elija una impresora de recibos activa'); }

		setcookie('pos_receipt_printer', $id ? (string) $id : '', array('expires' => $id ? time() + 365 * 86400 : time() - 3600, 'path' => '/', 'httponly' => TRUE, 'samesite' => 'Lax'));

		$this->audit->log('print.station', 'printer', $id ?: NULL, array('best_effort' => TRUE,
			'summary' => $id ? 'Esta computadora imprime recibos en '.$p->name : 'Esta computadora usa la impresora de recibos predeterminada'));

		$this->json(array('type' => 'success', 'message' => $id ? 'Esta computadora imprimirá los recibos en '.$p->name : 'Esta computadora usará la impresora de recibos predeterminada'));
	}

	public function register(){

		$this->form(0);
	}

	public function edit($id = 0){

		$this->form((int) $id);
	}

	private function form($id){

		$view = array('tk' => $this->token(), 'agents' => array_values(array_filter($this->printers_mdl->agents(), function($a){ return $a->active; })));

		if( $id ){
			$p = $this->printers_mdl->get($id);
			if( !$p ){
				$this->session->set_userdata('view_data', array('status' => array('class' => 'error', 'message' => 'Impresora inválida')));
				redirect('printers');
			}
			$view['printer'] = $p;
		}

		$this->content_data = $view;
		$this->renderPanel('form_printer');
	}

	public function save($id = 0){

		$this->requirePost();
		$id = (int) $id;

		$name = $this->cleanText($this->input->post('name'), 60);
		if( $name === NULL ){ $this->fail('Escriba el nombre (máx. 60 caracteres, sin símbolos < >)'); }

		$role = (string) $this->input->post('role');
		if( !in_array($role, array('receipt', 'kitchen'), TRUE) ){ $this->fail('Elija para qué se usa la impresora'); }

		$type = (string) $this->input->post('conn_type');
		if( !in_array($type, array('network', 'windows', 'agent'), TRUE) ){ $this->fail('Elija el tipo de conexión'); }

		$address = trim((string) $this->input->post('address'));
		$port = 9100;
		$id_agent = NULL;

		if( $type === 'network' ){

			if( !preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,148}[A-Za-z0-9])?$/', $address) ){
				$this->fail('Escriba la dirección IP o el nombre de red de la impresora (ej. 192.168.1.50)');
			}
			$port_in = trim((string) $this->input->post('port'));
			$port = $port_in === '' ? 9100 : (preg_match('/^\d{1,5}$/', $port_in) ? (int) $port_in : 0);
			if( $port < 1 || $port > 65535 ){ $this->fail('El puerto debe estar entre 1 y 65535'); }
		}
		elseif( $type === 'agent' ){

			$id_agent = (int) $this->input->post('id_agent');
			$agent = $id_agent ? $this->printers_mdl->agent($id_agent) : NULL;
			if( !$agent || !$agent->active ){ $this->fail('Elija la estación de impresión (la PC donde está conectada la impresora)'); }

			// the name is whatever Windows calls the printer ON THAT PC: only control characters and < > are refused
			if( $address === '' || mb_strlen($address, 'UTF-8') > 150 || preg_match('/[\x00-\x1f<>]/', $address) ){
				$this->fail('Escriba el nombre de la impresora tal como aparece en Windows en esa PC');
			}
		}
		else{

			// the same rules the Windows connector enforces: a printer name, a LPT/COM port or smb://host/printer
			$valid = preg_match('/^[\d\w-]+(\s[\d\w-]+)*$/', $address) || preg_match('/^(LPT\d|COM\d)$/', $address)
				|| preg_match('/^smb:\/\/([\s\d\w-]+(:[\s\d\w-]+)?@)?([\d\w-]+\.)*[\d\w-]+\/([\d\w-]+\/)?[\d\w-]+(\s[\d\w-]+)*$/', $address);
			if( !$valid || mb_strlen($address, 'UTF-8') > 150 ){
				$this->fail('Nombre de impresora inválido: use letras, números, guiones y espacios (como aparece en Windows) o smb://equipo/impresora');
			}
		}

		if( $id && !$this->printers_mdl->get($id) ){ $this->fail('Impresora inválida'); }
		if( $this->printers_mdl->nameTaken($name, $id) ){ $this->fail('Ya existe una impresora con ese nombre'); }

		$new = $this->printers_mdl->save($id, array('name' => $name, 'role' => $role, 'conn_type' => $type, 'address' => $address, 'port' => $port, 'id_agent' => $id_agent));
		if( !$new ){ $this->fail('No se pudo guardar la impresora'); }

		$this->ok($id ? 'Impresora actualizada' : 'Impresora creada', array('redirect' => base_url('printers')));
	}

	/** IP the agents must use to reach this server (the address the admin typed may be "localhost"). */
	private function serverHint(){

		$host = $this->input->server('HTTP_HOST');
		if( $host && !preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d+)?$/i', $host) ){ return rtrim(base_url(), '/'); }

		$ip = $this->input->server('SERVER_ADDR');
		if( !$ip || $ip === '::1' || $ip === '127.0.0.1' ){ $ip = gethostbyname((string) gethostname()); } // the PC's LAN address
		return 'http://'.($ip && $ip !== '127.0.0.1' ? $ip : 'IP-DEL-SERVIDOR');
	}

	/** Creates a print station (the PC with a USB printer). The token is returned ONCE. */
	public function agent_create(){

		$this->requirePost();

		$name = $this->cleanText($this->input->post('name'), 60);
		if( $name === NULL ){ $this->fail('Escriba el nombre de la estación (ej. Caja 2, Cocina; sin símbolos < >)'); }
		if( $this->printers_mdl->agentNameTaken($name) ){ $this->fail('Ya existe una estación con ese nombre'); }

		$token = bin2hex(random_bytes(24));
		if( !$this->printers_mdl->createAgent($name, $token) ){ $this->fail('No se pudo crear la estación'); }

		$this->ok('Estación creada. Copie el token ahora: no se vuelve a mostrar', array('token' => $token, 'name' => $name, 'server' => $this->serverHint()));
	}

	public function agent_rotate(){

		$this->requirePost();

		$a = $this->printers_mdl->agent($this->input->post('id_agent'));
		if( !$a ){ $this->fail('Estación inválida'); }

		$token = bin2hex(random_bytes(24));
		if( !$this->printers_mdl->rotateAgentToken($a->id_agent, $token) ){ $this->fail('No se pudo regenerar el token'); }

		$this->ok('Token regenerado. El anterior ya no funciona; cópielo ahora', array('token' => $token, 'name' => $a->name, 'server' => $this->serverHint()));
	}

	public function agent_toggle(){

		$this->requirePost();

		$a = $this->printers_mdl->agent($this->input->post('id_agent'));
		if( !$a ){ $this->fail('Estación inválida'); }

		$this->printers_mdl->setAgentActive($a->id_agent, !$a->active);
		$this->ok($a->active ? 'Estación desactivada' : 'Estación reactivada');
	}

	public function delete($id = 0){

		$p = $this->printers_mdl->get($id);
		if( $p ){ $this->printers_mdl->setActive($id, 0); }

		$this->session->set_userdata('view_data', array('status' => $p ? array('class' => 'success', 'message' => 'Impresora desactivada (el historial de impresión la conserva)') : array('class' => 'warning', 'message' => 'Impresora inválida')));
		redirect('printers');
	}

	public function restore($id = 0){

		$p = $this->printers_mdl->get($id);
		if( $p ){ $this->printers_mdl->setActive($id, 1); }

		$this->session->set_userdata('view_data', array('status' => $p ? array('class' => 'success', 'message' => 'Impresora reactivada') : array('class' => 'warning', 'message' => 'Impresora inválida')));
		redirect('printers');
	}

	/** Sends a test page to a printer. */
	public function test(){

		$this->requirePost();

		$p = $this->printers_mdl->get($this->input->post('id_printer'));
		if( !$p ){ $this->fail('Impresora inválida'); }
		if( !$p->active ){ $this->fail('La impresora está desactivada'); }

		$res = $this->printing->send($p->role, 'test', 'printer', $p->id_printer, 'Prueba: '.$p->name, $this->printing->testPage($p), $this->id_admin, $p);

		$this->audit->log('print.test', 'printer', $p->id_printer, array('best_effort' => TRUE,
			'summary' => 'Prueba de impresión en '.$p->name.($res['ok'] ? '' : ' (falló)'), 'after' => array('id_job' => $res['id_job'], 'ok' => $res['ok'], 'error' => $res['error'])));

		$this->json(array('type' => $res['ok'] ? 'success' : 'error', 'message' => $res['ok'] ? ($p->conn_type === 'agent' ? 'Página de prueba enviada a la estación; se imprimirá en unos segundos' : 'Página de prueba enviada a '.$p->name) : 'No se pudo imprimir: '.$res['error']));
	}

	/* ---------- queue ---------- */

	public function queue(){

		$status = (string) $this->input->get('status');

		$this->content_data = array(
			'jobs'   => $this->printers_mdl->jobs($status),
			'counts' => $this->printers_mdl->countByStatus(),
			'filter' => $status,
			'tk'     => $this->token(),
		);
		$this->renderPanel('print_queue');
	}

	/** Re-sends a failed (or stuck) job with its stored bytes. */
	public function retry(){

		$this->requirePost();

		$job = $this->printers_mdl->job($this->input->post('id_job'));
		if( !$job ){ $this->fail('Trabajo no encontrado'); }
		if( $job->status === 'printed' ){ $this->fail('Ese trabajo ya se imprimió; use Reimprimir'); }

		list($ok, $error) = $this->printing->deliver($job->id_job);

		$this->audit->log('print.retry', 'print_job', $job->id_job, array('best_effort' => TRUE,
			'summary' => 'Reintento de impresión #'.$job->id_job.' ('.$job->title.')'.($ok ? '' : ' (falló)'), 'after' => array('ok' => $ok, 'error' => $error)));

		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $ok ? ($this->viaAgent($job) ? 'Enviado a la estación; se imprimirá en unos segundos' : 'Impreso') : 'No se pudo imprimir: '.$error));
	}

	/** True when the job's printer is on another PC (the job is only queued for its agent, not printed yet). */
	private function viaAgent($job){

		$p = $job->id_printer ? $this->printers_mdl->get($job->id_printer) : NULL;

		return $p && $p->conn_type === 'agent';
	}

	/** Prints the same bytes again as a NEW job (a copy of a ticket that already printed). */
	public function reprint(){

		$this->requirePost();

		$job = $this->printers_mdl->job($this->input->post('id_job'));
		if( !$job ){ $this->fail('Trabajo no encontrado'); }

		list($ok, $error, $id_new) = $this->printing->reprint($job->id_job, $this->id_admin);

		$this->audit->log('print.reprint', 'print_job', $job->id_job, array('best_effort' => TRUE,
			'summary' => 'Reimpresión de #'.$job->id_job.' ('.$job->title.')'.($ok ? '' : ' (falló)'), 'after' => array('new_job' => $id_new, 'ok' => $ok, 'error' => $error)));

		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $ok ? ($this->viaAgent($job) ? 'Enviado a la estación; se imprimirá en unos segundos' : 'Reimpreso') : 'No se pudo imprimir: '.$error));
	}

	/** Text view of a stored job (ESC/POS control codes removed) to see what was sent. */
	public function preview($id = 0){

		$job = $this->printers_mdl->job($id);
		if( !$job ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->content_data = array('job' => $job, 'text' => $this->plainText((string) $job->payload));
		$this->renderPanel('print_preview');
	}

	/** Strips ESC/POS commands; keeps printable text and line breaks. Approximate: images are dropped. */
	private function plainText($b){

		$esc = array('@' => 0, 'E' => 1, 'G' => 1, '-' => 1, 'a' => 1, 'd' => 1, 'M' => 1, 't' => 1, '2' => 0, '3' => 1, '!' => 1, '{' => 1, 'J' => 1, 'r' => 1, 'p' => 3, '=' => 1, 'S' => 0, 'L' => 0);
		$gs  = array('!' => 1, 'B' => 1, 'H' => 1, 'h' => 1, 'w' => 1, 'f' => 1, 'a' => 1, 'r' => 1);

		$out = '';
		$n = strlen($b);

		for( $i = 0; $i < $n; $i++ ){

			$c = ord($b[$i]);

			if( $c === 0x1B && $i + 1 < $n ){          // ESC
				$cmd = $b[$i + 1];
				if( $cmd === '*' && $i + 4 < $n ){     // ESC * m nL nH + data
					$i += 4 + (ord($b[$i + 3]) + 256 * ord($b[$i + 4])) * (in_array(ord($b[$i + 2]), array(0, 1), TRUE) ? 1 : 3);
				}
				else{
					$i += 1 + (isset($esc[$cmd]) ? $esc[$cmd] : 0);
				}
			}
			elseif( $c === 0x1D && $i + 1 < $n ){      // GS
				$cmd = $b[$i + 1];
				if( $cmd === 'v' && $i + 7 < $n ){     // GS v 0 m xL xH yL yH + raster data (the logo)
					$w = ord($b[$i + 4]) + 256 * ord($b[$i + 5]);
					$h = ord($b[$i + 6]) + 256 * ord($b[$i + 7]);
					$i += 7 + $w * $h;
					$out .= "[imagen]
";
				}
				elseif( $cmd === 'V' ){ $i += ($i + 2 < $n && ord($b[$i + 2]) >= 65) ? 3 : 2; $out .= "
--------- corte ---------
"; }
				elseif( $cmd === '(' && $i + 4 < $n ){ // GS ( L pL pH ... graphics: skip its length
					$i += 4 + ord($b[$i + 3]) + 256 * ord($b[$i + 4]);
				}
				else{ $i += 1 + (isset($gs[$cmd]) ? $gs[$cmd] : 0); }
			}
			elseif( $c === 0x0A ){ $out .= "
"; }
			elseif( $c >= 0x20 && $c < 0x7F ){ $out .= $b[$i]; }
		}

		return $out;
	}
}
