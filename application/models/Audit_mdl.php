<?php if( !defined('BASEPATH') ) exit('No direct script access allowed');

/**
 * Read side of the audit trail (table audit_log, migration 022). Shared by the /audit screen, the JSON API
 * (/audit/api/v1/...) and the CSV export, so all of them filter the same way. Writing is done by the Audit library.
 */
class Audit_mdl extends CI_Model{

	const PAGE = 50;

	/** Entities that can be followed as a timeline. */
	public static $timeline_kinds = array('tab' => 'id_tab', 'bill' => 'id_bill', 'sale' => 'id_sale');

	public function __construct(){

		parent::__construct();
	}

	/** Local (America/Managua) date 'Y-m-d' to the UTC datetime that bounds it. */
	public static function localToUtc( $date, $end_of_day = FALSE ){

		$tz = new DateTimeZone(date_default_timezone_get());
		$dt = DateTime::createFromFormat('Y-m-d H:i:s', $date.($end_of_day ? ' 23:59:59' : ' 00:00:00'), $tz);

		if( !$dt ){ return NULL; }

		$dt->setTimezone(new DateTimeZone('UTC'));

		return $dt->format('Y-m-d H:i:s');
	}

	/** Stored UTC datetime to the local display string (or ISO-8601 UTC when $iso). */
	public static function fmt( $utc, $iso = FALSE ){

		if( !$utc ){ return NULL; }

		$dt = new DateTime($utc, new DateTimeZone('UTC'));

		if( $iso ){ return $dt->format('Y-m-d\TH:i:s.v\Z'); }

		$dt->setTimezone(new DateTimeZone(date_default_timezone_get()));

		return $dt->format('d/m/Y H:i:s');
	}

	/**
	 * Filters: action (exact or prefix 'bill.' / 'bill.*'), id_admin, entity_type, entity_id, id_tab, id_bill, id_sale, result,
	 * request_id, q (summary text), from / to (local dates). $security = FALSE hides login.* and admin.* rows (super-only).
	 * $order 'desc' pages backwards with $after (id < after); 'asc' pages forwards (id > after).
	 * Returns array(rows, next_cursor).
	 */
	public function search( $f, $limit = self::PAGE, $after = 0, $order = 'desc', $security = FALSE ){

		$limit = max(1, min(1000, (int)$limit));
		$sql = ' where 1=1'; $b = array();

		if( !empty($f['action']) ){

			$a = rtrim((string)$f['action'], '*');

			if( substr($a, -1) === '.' ){ $sql .= " and action like ? escape '!'"; $b[] = $this->db->escape_like_str($a).'%'; }
			else{ $sql .= ' and action = ?'; $b[] = $a; }
		}

		if( !empty($f['id_admin']) ){ $sql .= ' and id_admin = ?'; $b[] = (int)$f['id_admin']; }
		if( !empty($f['entity_type']) ){ $sql .= ' and entity_type = ?'; $b[] = (string)$f['entity_type']; }
		if( isset($f['entity_id']) && $f['entity_id'] !== '' && $f['entity_id'] !== NULL ){ $sql .= ' and entity_id = ?'; $b[] = (string)$f['entity_id']; }
		if( !empty($f['id_tab']) ){ $sql .= ' and id_tab = ?'; $b[] = (int)$f['id_tab']; }
		if( !empty($f['id_bill']) ){ $sql .= ' and id_bill = ?'; $b[] = (int)$f['id_bill']; }
		if( !empty($f['id_sale']) ){ $sql .= ' and id_sale = ?'; $b[] = (int)$f['id_sale']; }
		if( !empty($f['result']) && in_array($f['result'], array('ok','denied','error'), TRUE) ){ $sql .= ' and result = ?'; $b[] = $f['result']; }
		if( !empty($f['request_id']) ){ $sql .= ' and request_id = ?'; $b[] = (string)$f['request_id']; }
		if( isset($f['q']) && trim((string)$f['q']) !== '' ){ $sql .= " and summary like ? escape '!'"; $b[] = '%'.$this->db->escape_like_str(trim((string)$f['q'])).'%'; }

		if( !empty($f['from']) && ($u = self::localToUtc($f['from'])) ){ $sql .= ' and occurred_at >= ?'; $b[] = $u; }
		if( !empty($f['to']) && ($u = self::localToUtc($f['to'], TRUE)) ){ $sql .= ' and occurred_at <= ?'; $b[] = $u; }

		if( !$security ){ $sql .= " and action not like 'login.%' and action not like 'admin.%'"; }

		$asc = ( $order === 'asc' );

		if( $after > 0 ){ $sql .= $asc ? ' and id > ?' : ' and id < ?'; $b[] = (int)$after; }

		$rows = $this->db->query('select * from audit_log'.$sql.' order by id '.( $asc ? 'asc' : 'desc' ).' limit '.($limit + 1), $b)->result();

		$next = NULL;

		if( count($rows) > $limit ){

			array_pop($rows);
			$last = end($rows);
			$next = (int)$last->id;
		}

		return array($rows, $next);
	}

	/** Public shape of a row (used by the API and the CSV): decoded JSON, ISO-8601 UTC time. */
	public static function present( $r ){

		return array(
			'id' => (int)$r->id,
			'schema_version' => (int)$r->schema_version,
			'occurred_at' => self::fmt($r->occurred_at, TRUE),
			'request_id' => $r->request_id,
			'source' => $r->source,
			'actor' => array('id_admin' => $r->id_admin === NULL ? NULL : (int)$r->id_admin, 'name' => $r->admin_name, 'role' => $r->role),
			'ip' => $r->ip,
			'terminal_id' => $r->terminal_id,
			'user_agent' => $r->user_agent,
			'request' => $r->request,
			'action' => $r->action,
			'result' => $r->result,
			'entity' => array('type' => $r->entity_type, 'id' => $r->entity_id),
			'context' => array('id_tab' => $r->id_tab === NULL ? NULL : (int)$r->id_tab, 'id_bill' => $r->id_bill === NULL ? NULL : (int)$r->id_bill, 'id_sale' => $r->id_sale === NULL ? NULL : (int)$r->id_sale),
			'summary' => $r->summary,
			'before' => $r->before_json === NULL ? NULL : json_decode($r->before_json, TRUE),
			'after' => $r->after_json === NULL ? NULL : json_decode($r->after_json, TRUE)
		);
	}

	/** Distinct actions and actors for the filter drop-downs. */
	public function actions(){

		$out = array();
		foreach( $this->db->query('select distinct action from audit_log order by action')->result() as $r ){ $out[] = $r->action; }

		return $out;
	}

	public function actors(){

		return $this->db->query('select id_admin, max(admin_name) as admin_name from audit_log where id_admin is not null group by id_admin order by admin_name')->result();
	}
}
