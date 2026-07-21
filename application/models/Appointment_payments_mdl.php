<?php if( !defined('BASEPATH') ){ exit(); }

class Appointment_payments_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function getForAppointment( $id_appointment ){

		return $this->db->get_where( 'appointment_payments', array( 'id_appointment' => $id_appointment ) )->row();
	}
}
