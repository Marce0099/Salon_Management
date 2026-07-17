<?php if( !defined('BASEPATH') ){ exit(); }
	
	function formSubmited(){

		$CI =& get_instance();

		$submitted = $CI->input->post() != NULL && $CI->input->post('tk') == $CI->session->userdata('tk');

		if( $submitted ){

			$CI->session->set_userdata('tk','');
		}

		return $submitted;
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

?>