<?php if( !defined('BASEPATH') ){ exit(); }

if( !function_exists('load_data') ){

	function load_data( $data ){

		$array_data = array();

		if( is_array($data) ){

			foreach( $data as $key => $val ){

				$array_data[$key] = $val;
			}
		}
		else{

			if( $data->userdata('view_data') ){

				$array_data = $data->userdata('view_data');
				$data->set_userdata('view_data',NULL);
			}
			
		}

		return $array_data;
	}

	function filtersToStringQuery( $filters ){

		$f_query = '';

		foreach( $filters as $key => $val ){

			if( $f_query != '' ){

				$f_query .= ' and ';
			}

			if( substr($key,0,1) == '_' ){

				$k = substr($key,1);
				$k = preg_replace('/_/','.',$k,1);
				$f_query .= $k . '=' . $val;
			}
			else{

				$f_query .= $key . '=' . $val;
			}
			
		}

		return $f_query;
	}

	function getResultArray($table,$col,$id_table=NULL,$id_val=NULL){

		$CI =& get_instance();

		$data = array();

		if( $id_table ){

			$result = $CI->db->get_where( $table, array( $id_table => $id_val ) )->result();
		}
		else{

			$result = $CI->db->get( $table)->result();
		}
		

		foreach( $result as $rs ){

			$data[] = $rs->{$col};
		}

		return $data;
	}

 	function arrayToObject( $array ){

		/*$object = new stdClass();

		foreach( $array as $key => $val ){

			$object->$key = $val;
		}

		return $object;*/

		return json_decode( json_encode( $array ) );
	}

	function getArrayObjectValues( $arr_object, $key ){

		$values = array();

		foreach( $arr_object as $item ){

			$values[] = $item->{$key};
		}

		return $values;
	}
}
