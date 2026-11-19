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

		$old = $this->db->where('setting_key', $key)->get('settings')->row();

		$this->db->trans_start();
		$this->db->where('setting_key', $key)->update('settings', array( 'setting_value' => $value ));

		if( $old && (string) $old->setting_value !== (string) $value ){

			$this->audit->log('settings.update', 'setting', $key, array(
				'summary' => 'Configuración cambiada: '.$key,
				'before' => array('value' => $old->setting_value), 'after' => array('value' => $value)
			));
		}
		$this->db->trans_complete();
	}
}
