<?php if( !defined('BASEPATH') ){ exit(); }

class Dollar_exchange_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function save($data){

		$allowed = array('buy_price', 'sell_price');
		$data = array_intersect_key((array) $data, array_flip($allowed));

		if( !$data ){ return; }

		$before = $this->getExchange();

		$this->db->trans_start();
		$this->db->update('dollar_exchange',$data);
		$this->syncCordobaPrices();
		$this->audit->change('exchange.update', 'dollar_exchange', 1, $before ? (array) $before : array(), array_merge($before ? (array) $before : array(), $data), array(
			'summary' => 'Tipo de cambio actualizado'
		));
		$this->db->trans_complete();
	}

	// C$ prices are the source of truth: when the rate moves, re-derive the legacy dollar columns
	// so every dollar-based reader keeps showing the same C$ price.
	private function syncCordobaPrices(){

		$row = $this->db->select('sell_price')->get('dollar_exchange')->row();
		$rate = $row ? (float) $row->sell_price : 0;
		if( $rate <= 0 ){ return; }

		$this->db->query('UPDATE products SET price_sell = ROUND(price_sell_cor / ?, 6) WHERE price_sell_cor IS NOT NULL', array($rate));
		$this->db->query('UPDATE products SET price_cost = ROUND(price_cost_cor / ?, 6) WHERE price_cost_cor IS NOT NULL', array($rate));
		$this->db->query('UPDATE product_packs SET price_cost = ROUND(price_cost_cor / ?, 2) WHERE price_cost_cor IS NOT NULL', array($rate));
	}

	public function getExchange(){

		return $this->db->get('dollar_exchange')->row();
	}
}
