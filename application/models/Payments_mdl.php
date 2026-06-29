<?php if( !defined('BASEPATH') ){ exit(); }

class Payments_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function savePayment( $data ){

		$this->db->insert_batch('sale_payments', $data);
	}

	public function getPayment( $data ){
		return $this->db->get_where('sale_payments', array('id_sale' => $data))->result();
	}
}