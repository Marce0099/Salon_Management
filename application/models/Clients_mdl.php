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

		return $this->db->get_where( 'clients', array( 'id_client' => $id_client ) )->row();
	}
	public function getClientByIdentification( $id_card_client ){

		return $this->db->get_where( 'clients', array( 'id_card_client ' => $id_card_client  ) )->row();
	}

	public function edit( $id_client, $data ){

		$this->db->update('clients',$data,array( 'id_client' => $id_client ));
	}

	public function delete($id_client){

		$this->db->delete( 'clients', array( 'id_client' => $id_client ) );
	}
}