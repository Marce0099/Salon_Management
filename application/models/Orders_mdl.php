<?php if( !defined('BASEPATH') ){ exit(); }

class Orders_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getAll(){

		$sql = 'select O.*,P.provider from orders O inner join providers P
		on O.id_provider = P.id_provider order by id_order desc';

		return $this->db->query($sql)->result();
	}

	public function save( $data ){

		$this->db->insert( 'orders', $data );

		return $this->db->get_where( 'orders',array('id_order' => $this->db->insert_id() ) )->row('id_order');
	}

	public function saveOrderProduct($data){

		$this->db->insert('orders_products',$data);
	}

	public function getOrder( $id_order ){

		return $this->db->get_where('orders',array('id_order'=>$id_order))->row();
	}

	public function delete( $id_order ){

		$this->db->delete( 'orders', array( 'id_order' => $id_order ) );
	}

	public function setFile($id_order,$file){

		$this->db->update('orders',array('file'=>$file),array('id_order'=>$id_order));
	}
}
