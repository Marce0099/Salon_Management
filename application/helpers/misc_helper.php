<?php if( !defined('BASEPATH') ){ exit(); }
	
	function formSubmited(){

		$CI =& get_instance();

		return $CI->input->post() != NULL && $CI->input->post('tk') == $CI->session->userdata('tk');
	}

	function toAscii($url = NULL){

		$table = array(
		    'Á'=>'A',
			'Ç'=>'c',
			'É'=>'e',
			'Í'=>'i',
			'Ñ'=>'n',
			'Ó'=>'o',
			'Ú'=>'u',
			'á'=>'a',
			'ç'=>'c',
			'é'=>'e',
		    'í'=>'i',
			'ñ'=>'n',
			'ó'=>'o',
			'ú'=>'u',
		);
		$url = strtr($url, $table);
		//Añadimos los guiones
		$url = strtolower(trim($url));
		$url = preg_replace('/[^a-z0-9-]/', '-', $url);
		$url = preg_replace('/-+/', "-", $url);
		return $url;
		unset($url);
	}

	function formPosted(){

		return !empty($_POST);
	}

	function generateToken(){

		return md5(uniqid(mt_rand(), true));
	}

	function resolveLogoUrl($setting_key, $default_asset){

		$CI =& get_instance();
		$CI->load->model('settings_mdl');
		$filename = $CI->settings_mdl->get($setting_key);

		if( !empty($filename) && file_exists(FCPATH . 'uploads/logos/' . $filename) ){
			return base_url('uploads/logos/' . $filename);
		}

		return base_url('assets/img/' . $default_asset);
	}

	function resolveLogoPath($setting_key, $default_asset){

		$CI =& get_instance();
		$CI->load->model('settings_mdl');
		$filename = $CI->settings_mdl->get($setting_key);

		if( !empty($filename) && file_exists(FCPATH . 'uploads/logos/' . $filename) ){
			return str_replace("\\",'/', FCPATH . 'uploads/logos/' . $filename);
		}

		return str_replace("\\",'/', FCPATH . 'assets/img/' . $default_asset);
	}

?>