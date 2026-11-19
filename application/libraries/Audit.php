<?php if( !defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Central audit trail (table audit_log, migration 022). Contract: .claude/docs/audit_events.md
 *
 * $this->audit->log('bill.merge', 'bill', $id, array('before' => ..., 'after' => ..., 'tab' => .., 'bill' => .., 'sale' => .., 'summary' => 'texto'));
 *
 * - Writes on the CALLER's DB connection, so inside a transaction the row commits or rolls back with the action.
 *   Call it inside the runTx()/trans_start closure (a retried closure re-writes it, a rolled back one leaves nothing).
 * - Never opens a transaction itself.
 * - Fail-closed by default (a failed insert throws/returns FALSE so the caller can roll back); pass 'best_effort' => TRUE
 *   for events that must never break the user flow (login, logout, denied attempts).
 */
class Audit{

	const SCHEMA_VERSION = 1;
	const MAX_JSON = 60000;

	private $CI;
	private $request_id = NULL;

	public function __construct(){

		$this->CI =& get_instance();
	}

	/** One id per HTTP request: rows of the same request/transaction share it. Callers (Rust/Tauri) may send X-Request-Id. */
	public function requestId(){

		if( $this->request_id === NULL ){

			$hdr = $this->CI->input->get_request_header('X-Request-Id');

			if( $hdr && preg_match('/^[A-Za-z0-9_-]{8,40}$/', $hdr) ){

				$this->request_id = $hdr;
			}
			else{

				$this->request_id = sprintf('%s%s', dechex(time()), bin2hex(random_bytes(6)));
			}
		}

		return $this->request_id;
	}

	/** Strips 4-byte chars (the app connection is 3-byte utf8) and caps the size. */
	private function json( $v ){

		if( $v === NULL ){

			return NULL;
		}

		$s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

		if( $s === FALSE ){

			return NULL;
		}

		$s = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', $s);

		if( strlen($s) > self::MAX_JSON ){

			return json_encode(array('truncated' => TRUE, 'bytes' => strlen($s)));
		}

		return $s;
	}

	private function cut( $v, $n ){

		if( $v === NULL || $v === '' ){

			return NULL;
		}

		$v = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '?', (string)$v);

		return function_exists('mb_substr') ? mb_substr($v, 0, $n, 'UTF-8') : substr($v, 0, $n);
	}

	private function intOrNull( $v ){

		return ( $v === NULL || $v === '' || $v === FALSE ) ? NULL : (int)$v;
	}

	/**
	 * @param string $action       dotted verb, e.g. 'order_item.void'
	 * @param string $entity_type  e.g. 'order_item'
	 * @param mixed  $entity_id
	 * @param array  $o            before, after, tab, bill, sale, summary, result (ok|denied|error), actor (array id_admin,admin_name,role), best_effort
	 * @return bool
	 */
	public function log( $action, $entity_type = NULL, $entity_id = NULL, $o = array() ){

		$best = !empty($o['best_effort']);

		try{

			$db = $this->CI->db;

			$actor = isset($o['actor']) ? $o['actor'] : array(
				'id_admin' => $this->CI->session->userdata('id_admin'),
				'admin_name' => $this->CI->session->userdata('admin_name'),
				'role' => $this->CI->session->userdata('role')
			);

			$result = isset($o['result']) && in_array($o['result'], array('ok','denied','error'), TRUE) ? $o['result'] : 'ok';
			$terminal = $this->CI->input->get_request_header('X-Terminal-Id');
			$router = isset($this->CI->router) ? $this->CI->router : NULL;

			$row = array(
				'schema_version' => self::SCHEMA_VERSION,
				'occurred_at' => gmdate('Y-m-d H:i:s').'.'.str_pad((string)(int)((microtime(TRUE) - floor(microtime(TRUE))) * 1000), 3, '0', STR_PAD_LEFT),
				'request_id' => $this->requestId(),
				'source' => 'php',
				'id_admin' => $this->intOrNull($actor['id_admin']),
				'admin_name' => $this->cut($actor['admin_name'], 100),
				'role' => $this->cut($actor['role'], 20),
				'ip' => $this->cut(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL, 45),
				'terminal_id' => $this->cut($terminal, 64),
				'user_agent' => $this->cut(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : NULL, 255),
				'request' => $router ? $this->cut($router->fetch_class().'/'.$router->fetch_method(), 120) : NULL,
				'action' => $this->cut($action, 60),
				'result' => $result,
				'entity_type' => $this->cut($entity_type, 40),
				'entity_id' => $this->cut($entity_id, 40),
				'id_tab' => $this->intOrNull(isset($o['tab']) ? $o['tab'] : NULL),
				'id_bill' => $this->intOrNull(isset($o['bill']) ? $o['bill'] : NULL),
				'id_sale' => $this->intOrNull(isset($o['sale']) ? $o['sale'] : NULL),
				'summary' => $this->cut(isset($o['summary']) ? $o['summary'] : NULL, 255),
				'before_json' => $this->json(isset($o['before']) ? $o['before'] : NULL),
				'after_json' => $this->json(isset($o['after']) ? $o['after'] : NULL)
			);

			$ok = $db->insert('audit_log', $row);

			if( !$ok ){

				log_message('error', 'Audit insert failed: '.$action);
			}

			return (bool)$ok;
		}
		catch( Exception $e ){

			log_message('error', 'Audit exception ('.$action.'): '.$e->getMessage());

			if( $best ){

				return FALSE;
			}

			throw $e;
		}
	}

	/** Current row of a table as an array (NULL when missing); used to snapshot before/after values. */
	public function row( $table, $pk, $id ){

		$r = $this->CI->db->get_where($table, array($pk => $id))->row_array();

		return $r ? $r : NULL;
	}

	/** Only the columns whose value changed. Never includes password/hash columns. Returns array(before, after). */
	public function diff( $before, $after, $skip = array() ){

		$b = array(); $a = array();
		$before = is_array($before) ? $before : array();
		$after = is_array($after) ? $after : array();

		foreach( $after as $k => $v ){

			if( in_array($k, $skip, TRUE) || preg_match('/pass|hash/i', $k) ){ continue; }

			$old = array_key_exists($k, $before) ? $before[$k] : NULL;

			$same = ( is_numeric($old) && is_numeric($v) ) ? ( abs((float)$old - (float)$v) < 0.0000001 ) : ( (string)$old === (string)$v );

			if( !$same ){

				$b[$k] = $old;
				$a[$k] = $v;
			}
		}

		return array($b, $a);
	}

	/** Logs a change of one row (before/after = full rows, reduced to the differing columns). No row is written when nothing changed. */
	public function change( $action, $entity_type, $entity_id, $before, $after, $o = array() ){

		list($b, $a) = $this->diff($before, $after, isset($o['skip']) ? $o['skip'] : array());

		if( !$a && empty($o['force']) ){ return TRUE; }

		if( $before === NULL ){

			$after = is_array($after) ? $after : array();
			foreach( array_keys($after) as $k ){ if( preg_match('/pass|hash/i', $k) ){ unset($after[$k]); } }
		}

		$o['before'] = $before === NULL ? NULL : $b;
		$o['after'] = $before === NULL ? $after : $a;

		return $this->log($action, $entity_type, $entity_id, $o);
	}

	/** A refused action (permission, stock, locked bill...). Never breaks the flow. */
	public function denied( $action, $entity_type = NULL, $entity_id = NULL, $o = array() ){

		$o['result'] = 'denied';
		$o['best_effort'] = TRUE;

		return $this->log($action, $entity_type, $entity_id, $o);
	}
}
