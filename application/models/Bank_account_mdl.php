<?php if( !defined('BASEPATH') ){ exit(); }

class Bank_account_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function save($data){

		$this->db->insert( 'bank_account', $data );
	}

	public function dishabilitate($id_account){

		$this->db->where('id_account', $id_account);
		$this->db->update('bank_account', ["active" => 0]);
	}

	public function getAccounts(){
		$this->db->where('active', 1);
		return $this->db->get('bank_account')->result();
	}

	public function getAccount($token_account){

		return $this->db->get_where('bank_account', array('token_account' => $token_account))->row();
	}
}