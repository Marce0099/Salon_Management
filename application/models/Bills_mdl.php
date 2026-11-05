<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Floating bills of a tab: create, rename, move items (whole or partial quantity), merge, equal split.
 * Money: subtotal = sum(qty*unit_price) of active lines (or amount_override for equal-split children);
 * tip and IVA come from settings and are ADDED on top of the subtotal (tip_rate % if tip_enabled, tax_rate_iva %).
 * Every mutating method runs in a transaction and re-validates state, so concurrent waiters can't corrupt a bill.
 */
class Bills_mdl extends CI_Model{

	public function get($id_bill){

		return $this->db->where('id_bill', (int) $id_bill)->get('bills')->row();
	}

	public function forTab($id_tab){

		return $this->db->where('id_tab', (int) $id_tab)->where_in('status', array('open', 'split', 'paid'))->order_by('id_bill')->get('bills')->result();
	}

	/** Open (editable) bills that are not equal-split children being paid separately... i.e. where new items may go. */
	public function openBills($id_tab){

		return $this->db->where('id_tab', (int) $id_tab)->where('status', 'open')->where('parent_bill IS NULL', NULL, FALSE)->order_by('id_bill')->get('bills')->result();
	}

	public function nextLabel($id_tab){

		$n = $this->db->where('id_tab', (int) $id_tab)->where('parent_bill IS NULL', NULL, FALSE)->count_all_results('bills') + 1;
		do{
			$label = 'Cuenta '.$n++;
			$taken = $this->db->where('id_tab', (int) $id_tab)->where('label', $label)->count_all_results('bills') > 0;
		} while( $taken );

		return $label;
	}

	public function create($id_tab, $label = ''){

		$label = trim($label) !== '' ? trim($label) : $this->nextLabel($id_tab);
		$this->db->insert('bills', array('id_tab' => (int) $id_tab, 'label' => $label));
		return (int) $this->db->insert_id();
	}

	public function rename($id_bill, $label){

		$this->db->where('id_bill', (int) $id_bill)->update('bills', array('label' => $label));
	}

	public function items($id_bill){

		return $this->db->where('id_bill', (int) $id_bill)->where('status', 'active')->order_by('id_order_item')->get('order_items')->result();
	}

	public function rates(){

		$this->load->model('settings_mdl');
		$tip_on = $this->settings_mdl->get('tip_enabled', '1') === '1';

		return array(
			'tip' => $tip_on ? (float) $this->settings_mdl->get('tip_rate', 10) : 0.0,
			'iva' => (float) $this->settings_mdl->get('tax_rate_iva', 0),
		);
	}

	public function totals($bill, $rates = NULL){

		$rates = $rates ?: $this->rates();

		if( $bill->parent_bill ){
			return $this->childTotals($bill, $rates);
		}

		if( $bill->amount_override !== NULL ){
			$sub = (float) $bill->amount_override;
		}
		else{
			$row = $this->db->query('SELECT IFNULL(SUM(quantity * unit_price),0) AS s FROM order_items WHERE id_bill = ? AND status = "active"', array((int) $bill->id_bill))->row();
			$sub = (float) $row->s;
		}

		$tip = round($sub * $rates['tip'] / 100, 2);
		$iva = round($sub * $rates['iva'] / 100, 2);

		return array('subtotal' => round($sub, 2), 'tip' => $tip, 'iva' => $iva, 'total' => round($sub + $tip + $iva, 2));
	}

	/**
	 * Share of an equal split: every component (subtotal, tip, IVA) of the parent's totals is divided with the same rule
	 * (floor to cents for all but the last part, the last part takes the remainder) so the parts always add up to the parent to the cent.
	 */
	private function childTotals($bill, $rates){

		$parent = $this->get($bill->parent_bill);
		$pt     = $this->totals($parent, $rates);
		$ids    = array_map(function($r){ return (int) $r->id_bill; }, $this->db->select('id_bill')->where('parent_bill', (int) $parent->id_bill)->order_by('id_bill')->get('bills')->result());
		$n      = max(1, count($ids));
		$pos    = array_search((int) $bill->id_bill, $ids, TRUE);
		$last   = ($pos === $n - 1);

		$out = array();
		foreach( array('subtotal', 'tip', 'iva') as $k ){
			$share = floor($pt[$k] * 100 / $n) / 100;
			$out[$k] = $last ? round($pt[$k] - $share * ($n - 1), 2) : $share;
		}
		$out['total'] = round($out['subtotal'] + $out['tip'] + $out['iva'], 2);

		return $out;
	}

	/** Lock the tab's bills for the rest of the transaction. */
	private function lockTab($id_tab){

		$this->db->query('SELECT id_bill FROM bills WHERE id_tab = ? FOR UPDATE', array((int) $id_tab));
	}

	/** Move $qty of an order line to another bill (id_target 0 = brand new bill). Returns array(ok, message, id_bill). */
	public function moveItem($id_item, $id_target, $qty){

		$item = $this->db->where('id_order_item', (int) $id_item)->get('order_items')->row();
		if( !$item ){ return array(FALSE, 'Producto no encontrado', 0); }

		$this->db->trans_start();
		$this->lockTab($item->id_tab);

		$item = $this->db->query('SELECT * FROM order_items WHERE id_order_item = ? FOR UPDATE', array((int) $id_item))->row();
		$res  = $this->doMove($item, (int) $id_target, (int) $qty);

		$this->db->trans_complete();

		return $this->db->trans_status() ? $res : array(FALSE, 'No se pudo mover el producto', 0);
	}

	private function doMove($item, $id_target, $qty){

		if( $item->status !== 'active' ){ return array(FALSE, 'El producto está anulado', 0); }

		$from = $this->get($item->id_bill);
		if( !$from || $from->status !== 'open' || $from->parent_bill ){ return array(FALSE, 'La cuenta de origen no se puede modificar', 0); }

		if( $qty < 1 || $qty > (int) $item->quantity ){ return array(FALSE, 'Cantidad inválida', 0); }

		if( $id_target === 0 ){
			$id_target = $this->create($item->id_tab);
		}
		$to = $this->get($id_target);
		if( !$to || (int) $to->id_tab !== (int) $item->id_tab || $to->status !== 'open' || $to->parent_bill ){ return array(FALSE, 'La cuenta destino no es válida', 0); }
		if( (int) $to->id_bill === (int) $from->id_bill ){ return array(FALSE, 'El producto ya está en esa cuenta', 0); }

		if( $qty === (int) $item->quantity ){
			$this->db->where('id_order_item', (int) $item->id_order_item)->update('order_items', array('id_bill' => (int) $to->id_bill));
		}
		else{
			// partial: shrink the original line and clone the moved part into the target bill
			$this->db->where('id_order_item', (int) $item->id_order_item)->update('order_items', array('quantity' => (int) $item->quantity - $qty));
			$clone = (array) $item;
			unset($clone['id_order_item']);
			$clone['quantity'] = $qty;
			$clone['id_bill']  = (int) $to->id_bill;
			$this->db->insert('order_items', $clone);
		}

		return array(TRUE, 'Producto movido', (int) $to->id_bill);
	}

	/** Move every line of $id_from into $id_into and void the empty source. */
	public function merge($id_from, $id_into){

		$from = $this->get($id_from);
		$into = $this->get($id_into);

		if( !$from || !$into || (int) $from->id_tab !== (int) $into->id_tab || $from->id_bill === $into->id_bill ){ return array(FALSE, 'Cuentas inválidas'); }

		$this->db->trans_start();
		$this->lockTab($from->id_tab);

		$from = $this->get($id_from);
		$into = $this->get($id_into);

		$ok = $from->status === 'open' && !$from->parent_bill && $into->status === 'open' && !$into->parent_bill;
		if( $ok ){
			$this->db->where('id_bill', (int) $from->id_bill)->update('order_items', array('id_bill' => (int) $into->id_bill));
			$this->db->where('id_bill', (int) $from->id_bill)->update('bills', array('status' => 'void'));
		}

		$this->db->trans_complete();

		return $ok ? array(TRUE, 'Cuentas unidas') : array(FALSE, 'Solo se pueden unir cuentas abiertas');
	}

	/** Divide a bill into $parts equal shares: the bill becomes 'split' and $parts child bills (open) are created. */
	public function splitEqual($id_bill, $parts){

		$bill = $this->get($id_bill);
		if( !$bill ){ return array(FALSE, 'Cuenta no encontrada'); }

		$this->db->trans_start();
		$this->lockTab($bill->id_tab);

		$bill = $this->get($id_bill);
		$res  = array(FALSE, 'Solo se puede dividir una cuenta abierta');

		if( $bill->status === 'open' && !$bill->parent_bill ){

			$sub = $this->totals($bill, array('tip' => 0, 'iva' => 0));
			$sub = $sub['subtotal'];

			if( $sub <= 0 ){
				$res = array(FALSE, 'La cuenta no tiene consumo para dividir');
			}
			else{
				$share = floor($sub * 100 / $parts) / 100;
				$acc = 0.0;
				for( $k = 1; $k <= $parts; $k++ ){
					$amount = $k < $parts ? $share : round($sub - $acc, 2); // last part absorbs the rounding cents
					$acc += $amount;
					$this->db->insert('bills', array(
						'id_tab' => (int) $bill->id_tab, 'label' => $bill->label.' · parte '.$k.'/'.$parts,
						'parent_bill' => (int) $bill->id_bill, 'amount_override' => $amount,
					));
				}
				$this->db->where('id_bill', (int) $bill->id_bill)->update('bills', array('status' => 'split'));
				$res = array(TRUE, 'Cuenta dividida en '.$parts.' partes');
			}
		}

		$this->db->trans_complete();

		return $res;
	}

	/** Remove an equal split (only while none of its parts is paid). */
	public function undoSplit($id_bill){

		$bill = $this->get($id_bill);
		if( !$bill ){ return array(FALSE, 'Cuenta no encontrada'); }

		$this->db->trans_start();
		$this->lockTab($bill->id_tab);

		$bill = $this->get($id_bill);
		$paid = $this->db->where('parent_bill', (int) $bill->id_bill)->where('status', 'paid')->count_all_results('bills');
		$res  = array(FALSE, 'Esta cuenta no está dividida');

		if( $bill->status === 'split' ){
			if( $paid > 0 ){
				$res = array(FALSE, 'Ya hay partes cobradas; no se puede deshacer la división');
			}
			else{
				$this->db->where('parent_bill', (int) $bill->id_bill)->delete('bills');
				$this->db->where('id_bill', (int) $bill->id_bill)->update('bills', array('status' => 'open'));
				$res = array(TRUE, 'División deshecha');
			}
		}

		$this->db->trans_complete();

		return $res;
	}
}
