<?php if( !defined('BASEPATH') ){ exit(); }

class Clients_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getDateFormat($date){		
		return Date("d/m/Y", strtotime($date));
	}

	public function save( $data ){

		if( !empty($data['id_card_client']) ){
			$exists = $this->db->where( 'id_card_client', $data['id_card_client'] )->count_all_results('clients');
			if( $exists > 0 ){
				throw new Exception( 'El cliente ya ha sido registrado' );
			}
		}

		return $this->db->insert( 'clients', $data );
	}

	public function getClient( $id_client ){

		return $this->db->get_where( 'clients', array( 'id_client' => $id_client, 'active' => 1 ) )->row();
	}

	public function getInactiveClient( $id_client ){

		return $this->db->get_where( 'clients', array( 'id_client' => $id_client, 'active' => 0 ) )->row();
	}

	public function getClientByIdentification( $id_card_client ){

		$this->db->where('active', 1);
		return $this->db->get_where( 'clients', array( 'id_card_client ' => $id_card_client  ) )->row();
	}

	public function edit( $id_client, $data ){

		$this->db->update('clients',$data,array( 'id_client' => $id_client ));
	}

	public function delete($id_client){

		$this->db->where('id_client', $id_client)->update('clients', ['active' => 0]);
	}

	public function reactivate($id_client){

		$this->db->where('id_client', $id_client)->update('clients', ['active' => 1]);
	}

	public function searchActive($id_card_client = '', $name = '', $surname = '', $phone = '', $email = ''){

		$sql = 'SELECT id_client, id_card_client, name_client, surname_client, phone_client, email_client
		        FROM clients WHERE active = 1';

		if( !empty($id_card_client) ){
			$safe = $this->db->escape_like_str($id_card_client);
			$sql .= ' AND id_card_client LIKE "%'.$safe.'%"';
		}
		if( !empty($name) ){
			$safe = $this->db->escape_like_str($name);
			$sql .= ' AND name_client LIKE "%'.$safe.'%"';
		}
		if( !empty($surname) ){
			$safe = $this->db->escape_like_str($surname);
			$sql .= ' AND surname_client LIKE "%'.$safe.'%"';
		}
		if( !empty($phone) ){
			$safe = $this->db->escape_like_str($phone);
			$sql .= ' AND phone_client LIKE "%'.$safe.'%"';
		}
		if( !empty($email) ){
			$safe = $this->db->escape_like_str($email);
			$sql .= ' AND email_client LIKE "%'.$safe.'%"';
		}

		$sql .= ' ORDER BY name_client ASC LIMIT 50';

		return $this->db->query($sql)->result();
	}
}