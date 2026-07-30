<?php if( !defined('BASEPATH') ){ exit(); }

class Payments_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function savePayment( $data ){

		// a coupon code redeemed by a concurrent, still-uncommitted sale is rejected by the
		// uniq_cupon_code constraint; on this PHP/PDO version (default PDO::ERRMODE_EXCEPTION
		// since PHP 8.1) that surfaces as a normal catchable PDOException, which the caller
		// (Sales::register()) wraps in a try/catch for exactly this case
		$this->db->insert_batch('sale_payments', $data);
	}

	public function getPayment( $data ){
		return $this->db->get_where('sale_payments', array('id_sale' => $data))->result();
	}

	public function getUsedCouponCodes( $codes ){

		$rows = $this->db->select('cupon_code')
			->where('payment_method', 'cupon')
			->where_in('cupon_code', $codes)
			->get('sale_payments')->result();

		return array_map(function($r){ return $r->cupon_code; }, $rows);
	}
}