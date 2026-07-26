<?php if( !defined('BASEPATH') ){ exit(); }

class Settings_mdl extends CI_Model{

	public function __construct(){

		parent::__construct();
	}

	public function get( $key, $default = null ){

		$row = $this->db->get_where('settings', array( 'setting_key' => $key ))->row();

		return $row ? $row->setting_value : $default;
	}

	public function getAll(){

		return $this->db->get('settings')->result();
	}

	public function save( $key, $value ){

		$this->db->where('setting_key', $key)->update('settings', array( 'setting_value' => $value ));
	}
}
