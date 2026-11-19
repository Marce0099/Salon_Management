<?php if( !defined('BASEPATH') ){ exit(); }

class Clients_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getDateFormat($date){		
		return empty($date) ? '' : Date("d/m/Y", strtotime($date));
	}

	public function save( $data ){

		if( !empty($data['id_card_client']) ){
			$exists = $this->db->where( 'id_card_client', $data['id_card_client'] )->count_all_results('clients');
			if( $exists > 0 ){
				throw new Exception( 'El cliente ya ha sido registrado' );
			}
		}

		$this->db->trans_start();
		$ok = $this->db->insert( 'clients', $data );
		$this->audit->change('client.create', 'client', $this->db->insert_id(), NULL, $data, array('summary' => 'Cliente creado'));
		$this->db->trans_complete();

		return $ok;
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

		$before = $this->audit->row('clients', 'id_client', $id_client);

		$this->db->trans_start();
		$this->db->update('clients',$data,array( 'id_client' => $id_client ));
		$this->audit->change('client.update', 'client', $id_client, $before, $this->audit->row('clients', 'id_client', $id_client), array('summary' => 'Cliente modificado'));
		$this->db->trans_complete();
	}

	private function setActive($id_client, $active, $action, $summary){

		$this->db->trans_start();
		$this->db->where('id_client', $id_client)->update('clients', ['active' => $active]);
		$this->audit->log($action, 'client', $id_client, array('summary' => $summary, 'before' => array('active' => 1 - $active), 'after' => array('active' => $active)));
		$this->db->trans_complete();
	}

	public function delete($id_client){

		$this->setActive($id_client, 0, 'client.deactivate', 'Cliente desactivado');
	}

	public function reactivate($id_client){

		$this->setActive($id_client, 1, 'client.reactivate', 'Cliente reactivado');
	}

	public function toggleVip($id_client){

		$before = $this->audit->row('clients', 'id_client', $id_client);

		$this->db->trans_start();
		$this->db->where('id_client', $id_client)->set('is_vip_client', '1 - is_vip_client', false)->update('clients');
		$this->audit->change('client.vip_toggle', 'client', $id_client, $before, $this->audit->row('clients', 'id_client', $id_client), array('summary' => 'Cliente VIP cambiado'));
		$this->db->trans_complete();
	}

	public function searchActive($id_card_client = '', $name = '', $surname = '', $phone = '', $email = ''){

		$sql = 'SELECT id_client, id_card_client, name_client, surname_client, phone_client, email_client, is_vip_client,
		        DATE_FORMAT(birth_client, "%m-%d") = DATE_FORMAT(CURDATE(), "%m-%d") AS is_birthday_today
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

	public function searchActiveByTerm($term){

		$safe = $this->db->escape_like_str($term);

		$sql = 'SELECT id_client, id_card_client, name_client, surname_client, phone_client, email_client, is_vip_client,
		        DATE_FORMAT(birth_client, "%m-%d") = DATE_FORMAT(CURDATE(), "%m-%d") AS is_birthday_today
		        FROM clients WHERE active = 1
		        AND (name_client LIKE "%'.$safe.'%"
		             OR surname_client LIKE "%'.$safe.'%"
		             OR CONCAT(name_client, " ", surname_client) LIKE "%'.$safe.'%")
		        ORDER BY name_client ASC LIMIT 50';

		return $this->db->query($sql)->result();
	}
}