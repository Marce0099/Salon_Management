<?php if( !defined('BASEPATH') ){ exit(); }

class Bank_account_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function save($data){

		$this->db->trans_start();
		$this->db->insert( 'bank_account', $data );
		$id = $this->db->insert_id();
		$this->audit->change('bank_account.create', 'bank_account', $id, NULL, $data, array('summary' => 'Cuenta bancaria creada'));
		$this->db->trans_complete();
	}

	public function dishabilitate($id_account){

		$before = $this->audit->row('bank_account', 'id_account', $id_account);

		$this->db->trans_start();
		$this->db->where('id_account', $id_account);
		$this->db->update('bank_account', ["active" => 0]);
		$this->audit->change('bank_account.disable', 'bank_account', $id_account, $before, array_merge((array) $before, array('active' => 0)), array('summary' => 'Cuenta bancaria deshabilitada', 'force' => TRUE));
		$this->db->trans_complete();
	}

	public function update($id_account, $data){

		$before = $this->audit->row('bank_account', 'id_account', $id_account);

		$this->db->trans_start();
		$this->db->where('id_account', $id_account);
		$this->db->update('bank_account', $data);
		$this->audit->change('bank_account.update', 'bank_account', $id_account, $before, $this->audit->row('bank_account', 'id_account', $id_account), array('summary' => 'Cuenta bancaria modificada'));
		$this->db->trans_complete();
	}

	public function getAccounts(){
		$this->db->where('active', 1);
		return $this->db->get('bank_account')->result();
	}

	public function getAccount($token_account){

		return $this->db->get_where('bank_account', array('token_account' => $token_account))->row();
	}
}