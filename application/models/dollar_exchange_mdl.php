<?php if( !defined('BASEPATH') ){ exit(); }

class Dollar_exchange_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function save($data){

		$this->db->update('dollar_exchange',$data);
	}

	public function getExchange(){

		return $this->db->get('dollar_exchange')->row();
	}
}
