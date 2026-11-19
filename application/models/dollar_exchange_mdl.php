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
		$this->audit->change('exchange.update', 'dollar_exchange', 1, $before ? (array) $before : array(), array_merge($before ? (array) $before : array(), $data), array(
			'summary' => 'Tipo de cambio actualizado'
		));
		$this->db->trans_complete();
	}

	public function getExchange(){

		return $this->db->get('dollar_exchange')->row();
	}
}
