<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Audit trail viewer (admin only): list with filters, timeline of a tab / bill / sale, CSV export and a versioned JSON API
 * (/audit/api/v1/events, /audit/api/v1/entity/{type}/{id}). Security events (login.*, admin.*) are visible to super only.
 * The contract (fields, actions) is documented in .claude/docs/audit_events.md so the Rust backend can serve the same endpoints.
 */
class Audit_trail extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){

			if( $this->uri->segment(2) === 'api' ){

				$this->jsonOut(403, array('error' => 'forbidden', 'message' => 'Se requiere un administrador'));
			}

			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('audit_mdl');
	}

	private function jsonOut( $status, $data ){

		$this->output->set_status_header($status)->set_content_type('application/json', 'utf-8')->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
		$this->output->_display();
		exit;
	}

	private function validDate( $v ){

		return ( is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== FALSE ) ? $v : '';
	}

	private function filters(){

		$int = function($k){ $v = $this->input->get($k); return ctype_digit((string)$v) ? (int)$v : 0; };
		$txt = function($k, $max){ return mb_substr(trim((string)$this->input->get($k)), 0, $max, 'UTF-8'); };

		$f = array(
			'action' => $txt('action', 60), 'id_admin' => $int('id_admin'), 'entity_type' => $txt('entity_type', 40), 'entity_id' => $txt('entity_id', 40),
			'id_tab' => $int('id_tab'), 'id_bill' => $int('id_bill'), 'id_sale' => $int('id_sale'), 'result' => $txt('result', 10),
			'request_id' => $txt('request_id', 40), 'q' => $txt('q', 100),
			'from' => $this->validDate($this->input->get('from')), 'to' => $this->validDate($this->input->get('to'))
		);

		if( $f['from'] !== '' && $f['to'] !== '' && $f['from'] > $f['to'] ){ list($f['from'], $f['to']) = array($f['to'], $f['from']); }

		return $f;
	}

	/* ---------- screens ---------- */

	public function index(){

		$f = $this->filters();
		$after = ctype_digit((string)$this->input->get('after')) ? (int)$this->input->get('after') : 0;

		list($rows, $next) = $this->audit_mdl->search($f, Audit_mdl::PAGE, $after, 'desc', $this->is_super());

		$this->content_data = array(
			'f' => $f, 'rows' => $rows, 'next' => $next, 'title' => 'Auditoría', 'timeline' => NULL,
			'actions' => $this->audit_mdl->actions(), 'actors' => $this->audit_mdl->actors(), 'is_super' => $this->is_super()
		);
		$this->renderPanel('audit');
	}

	/** Everything that happened around one tab / bill / sale, oldest first. */
	public function timeline( $kind = '', $id = 0 ){

		if( !isset(Audit_mdl::$timeline_kinds[$kind]) || !ctype_digit((string)$id) || (int)$id < 1 ){

			show_404();
		}

		$f = $this->filters();
		$f[Audit_mdl::$timeline_kinds[$kind]] = (int)$id;

		list($rows, $next) = $this->audit_mdl->search($f, 500, 0, 'asc', $this->is_super());

		$names = array('tab' => 'la cuenta de mesa', 'bill' => 'la cuenta', 'sale' => 'la venta');

		$this->content_data = array(
			'f' => $f, 'rows' => $rows, 'next' => NULL, 'title' => 'Línea de tiempo de '.$names[$kind].' #'.(int)$id, 'timeline' => $kind,
			'actions' => array(), 'actors' => array(), 'is_super' => $this->is_super()
		);
		$this->renderPanel('audit');
	}

	public function export(){

		$f = $this->filters();
		list($rows) = $this->audit_mdl->search($f, 5000, 0, 'desc', $this->is_super());

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="auditoria_'.date('Ymd_His').'.csv"');
		$out = fopen('php://output', 'w');
		fwrite($out, "\xEF\xBB\xBF");
		fputcsv($out, array('id', 'fecha_hora_local', 'usuario', 'rol', 'ip', 'terminal', 'accion', 'resultado', 'entidad', 'id_entidad', 'mesa', 'cuenta', 'venta', 'resumen', 'antes', 'despues', 'request_id'));

		foreach( $rows as $r ){

			// a leading = + - @ would be evaluated as a formula by spreadsheets
			$safe = function($v){ return ( is_string($v) && $v !== '' && strpos("=+-@\t\r", $v[0]) !== FALSE ) ? "'".$v : $v; };

			fputcsv($out, array($r->id, Audit_mdl::fmt($r->occurred_at), $safe($r->admin_name), $r->role, $r->ip, $r->terminal_id, $r->action, $r->result,
				$r->entity_type, $safe($r->entity_id), $r->id_tab, $r->id_bill, $r->id_sale, $safe($r->summary), $safe($r->before_json), $safe($r->after_json), $r->request_id));
		}

		fclose($out);
		exit;
	}

	/* ---------- JSON API v1 (GET, admin session) ---------- */

	/**
	 * GET /audit/api/v1/events?action=bill.&id_admin=&entity_type=&entity_id=&id_tab=&id_bill=&id_sale=&result=&q=&from=&to=&limit=&after=&order=
	 * GET /audit/api/v1/entity/{tab|bill|sale|<entity_type>}/{id}
	 * Response: {"data":[event...],"next_cursor":<id|null>} ; pass next_cursor back as ?after= (keyset pagination).
	 */
	public function api( $version = '', $what = '', $a = '', $b = '' ){

		if( $this->input->method(TRUE) !== 'GET' ){

			$this->output->set_header('Allow: GET');
			$this->jsonOut(405, array('error' => 'method_not_allowed', 'message' => 'Use GET'));
		}

		if( $version !== 'v1' ){

			$this->jsonOut(404, array('error' => 'unknown_version', 'message' => 'Versión de API desconocida'));
		}

		$f = $this->filters();
		$limit = ctype_digit((string)$this->input->get('limit')) ? max(1, min(500, (int)$this->input->get('limit'))) : 50;
		$after = ctype_digit((string)$this->input->get('after')) ? (int)$this->input->get('after') : 0;
		$order = $this->input->get('order') === 'asc' ? 'asc' : 'desc';

		if( $what === 'entity' ){

			if( $a === '' || $b === '' || !ctype_digit((string)$b) ){

				$this->jsonOut(422, array('error' => 'invalid_entity', 'message' => 'Use /entity/{type}/{id}'));
			}

			if( isset(Audit_mdl::$timeline_kinds[$a]) ){ $f[Audit_mdl::$timeline_kinds[$a]] = (int)$b; $order = $this->input->get('order') === 'desc' ? 'desc' : 'asc'; }
			else{ $f['entity_type'] = mb_substr($a, 0, 40); $f['entity_id'] = (string)$b; }
		}
		elseif( $what !== 'events' ){

			$this->jsonOut(404, array('error' => 'not_found', 'message' => 'Recurso desconocido'));
		}

		list($rows, $next) = $this->audit_mdl->search($f, $limit, $after, $order, $this->is_super());

		$this->jsonOut(200, array('data' => array_map(array('Audit_mdl', 'present'), $rows), 'next_cursor' => $next));
	}
}
