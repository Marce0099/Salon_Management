<?php if( !defined('BASEPATH') ) exit();

	/** Audit of product cost changes (table product_cost_history). Writers: Invoices_mdl::receive, Products edit/save/import. */
	class Cost_history_mdl extends CI_Model{

		public function __construct(){

			parent::__construct();
		}

		private function dec( $v ){

			return ( $v === NULL || $v === '' || !is_numeric($v) ) ? NULL : round((float)$v, 4);
		}

		/** Inserts one row; call it inside the caller's transaction. Costs are per base unit in dollars. */
		public function log( $id_product, $source, $old_cost, $new_cost, $extra = array() ){

			$new = $this->dec($new_cost);

			if( $new === NULL ){

				return FALSE;
			}

			$row = array(
				'id_product' => (int)$id_product,
				'source' => $source,
				'id_invoice' => isset($extra['id_invoice']) ? $extra['id_invoice'] : NULL,
				'id_invoice_product' => isset($extra['id_invoice_product']) ? $extra['id_invoice_product'] : NULL,
				'id_provider' => isset($extra['id_provider']) ? $extra['id_provider'] : NULL,
				'old_cost' => $this->dec($old_cost),
				'new_cost' => $new,
				'quantity' => isset($extra['quantity']) ? (int)$extra['quantity'] : NULL,
				'old_sell' => isset($extra['old_sell']) ? $this->dec($extra['old_sell']) : NULL,
				'new_sell' => isset($extra['new_sell']) ? $this->dec($extra['new_sell']) : NULL,
				'id_admin' => $this->session->userdata('id_admin') ? (int)$this->session->userdata('id_admin') : NULL,
				'created_at' => !empty($extra['created_at']) ? $extra['created_at'] : date('Y-m-d H:i:s')
			);

			return $this->db->insert('product_cost_history', $row);
		}

		/** Filters shared by the summary and the detail: array(sql, binds) over alias H. */
		private function where( $f ){

			$sql = ' where 1=1'; $b = array();

			if( !empty($f['id_product']) ){ $sql .= ' and H.id_product = ?'; $b[] = (int)$f['id_product']; }
			if( !empty($f['id_provider']) ){ $sql .= ' and H.id_provider = ?'; $b[] = (int)$f['id_provider']; }
			if( !empty($f['source']) && in_array($f['source'], array('purchase','manual','import'), TRUE) ){ $sql .= ' and H.source = ?'; $b[] = $f['source']; }
			if( !empty($f['from']) ){ $sql .= ' and H.created_at >= ?'; $b[] = $f['from'].' 00:00:00'; }
			if( !empty($f['to']) ){ $sql .= ' and H.created_at <= ?'; $b[] = $f['to'].' 23:59:59'; }
			if( !empty($f['only_changes']) ){ $sql .= ' and (H.old_cost is null or H.old_cost <> H.new_cost)'; }

			return array($sql, $b);
		}

		/** One row per product: purchases, last/min/max cost and the weighted average (by units) in the range. */
		public function summary( $f ){

			list($w, $b) = $this->where($f);

			$sql = 'select P.id_product, P.name, P.barcode, P.price_cost as current_cost, P.price_sell as current_sell,
				count(*) as n, min(H.new_cost) as min_cost, max(H.new_cost) as max_cost,
				sum(case when H.quantity > 0 then H.new_cost * H.quantity end) / nullif(sum(case when H.quantity > 0 then H.quantity end), 0) as avg_cost,
				substring_index(group_concat(H.new_cost order by H.created_at desc, H.id_cost_history desc), ",", 1) as last_cost,
				max(H.created_at) as last_date
				from product_cost_history H inner join products P on P.id_product = H.id_product'.$w.'
				group by P.id_product order by P.name';

			return $this->db->query($sql, $b)->result();
		}

		/** Stats of one product in the range (same shape as a summary row, or NULL). */
		public function productStats( $f ){

			$rows = $this->summary($f);

			return $rows ? $rows[0] : NULL;
		}

		public function rows( $f, $limit = 500 ){

			list($w, $b) = $this->where($f);

			$sql = 'select H.*, I.invoice_no, PV.provider, A.username
				from product_cost_history H
				left join invoices I on I.id_invoice = H.id_invoice
				left join providers PV on PV.id_provider = H.id_provider
				left join admins A on A.id_admin = H.id_admin'.$w.'
				order by H.created_at desc, H.id_cost_history desc limit '.(int)$limit;

			return $this->db->query($sql, $b)->result();
		}
	}
