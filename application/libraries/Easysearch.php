<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
	
class Easysearch{

	private $CI;
	private $query;
	private $config;
	private $post_get;
	private $default_limit;
	private $initial_where;

	public function __construct(){

		$this->CI =& get_instance();

		$this->query = '';
		$this->config = array();
		$this->initial_where = NULL;
		$this->post_get = array_merge( $this->CI->input->post(), $this->CI->input->get() );
		$this->default_limit = 50;
	}

	public function setCustomGetVars( $custom_get_vars ){

		$this->post_get = array_merge( $this->post_get, $custom_get_vars );
	}	

	public function setInitialWhere( $where ){

		$this->initial_where = $where;
	}

	public function search( $config = array() ){

		if( !isset($config['query']) ){

			throw new Exception('Empty query');
		}

		$this->config = $config;
		
		$this->addSearchFields();
		$data['query_no_limit'] = $this->query;
		$this->addGroupByOrder();
		$no_limit_result = $this->CI->db->query( $this->query )->result();
		$data['no_limit_count'] = count($no_limit_result);		
		$this->addLimit();
		$data['query_limit'] = $this->query;


		$result = $this->CI->db->query( $this->query )->result();

		unset( $this->post_get['start'] );
		$get_query = http_build_query($this->post_get);
		$url = base_url(uri_string()) . '/?' . $get_query;

		$data['get_query'] = $get_query;

		$this->CI->load->library('pagination');
		$pagination_config['base_url'] = $url;
		$pagination_config['total_rows'] = count( $no_limit_result );
		$pagination_config['per_page'] = $this->config['limit'];
		$pagination_config['num_links'] = ceil( count( $no_limit_result ) / $this->config['limit'] );
		$pagination_config['prev_link'] = '«';
		$pagination_config['prev_tag_open'] = '<li>';
		$pagination_config['prev_tag_close'] = '</li>';
		$pagination_config['next_link'] = '»';
		$pagination_config['next_tag_open'] = '<li>';
		$pagination_config['next_tag_close'] = '</li>';
		$pagination_config['num_tag_open'] = '<li>';
		$pagination_config['num_tag_close'] = '</li>';
		$pagination_config['full_tag_open'] = '<ul class="pagination pagination-sm">';
		$pagination_config['full_tag_close'] = '</ul>';
		$pagination_config['cur_tag_open'] = '<li class="active"><a href="#">';
		$pagination_config['cur_tag_close'] = '</a></li>';
		$pagination_config['last_link'] = FALSE;
		$pagination_config['first_link'] = FALSE;
		$pagination_config['page_query_string'] = TRUE;
		$pagination_config['query_string_segment'] = 'start';

		$this->CI->pagination->initialize($pagination_config);

		if( isset($this->config['return_as']) ){

			$data[ $this->config['return_as'] ] = $result;
		}
		else{

			$data['result'] = $result;
		}

		$data['pagination'] = $this->CI->pagination->create_links();
		$data['get'] = $this->CI->input->get();

		if( !empty($_POST) ){

			$data['post'] = $this->CI->input->post();
		}

		return $data;
	}

	private function addSearchFields(){
		
		$where = '';

		if( !empty($this->config['fields']) ){

			foreach( $this->config['fields'] as $key => $field_info ){

				if( isset( $this->post_get[$key] ) && !isset($field_info['having']) ){

					if( $field_info['type'] == 'numeric' && is_numeric($this->post_get[$key]) ){

						$where .= $field_info['column'] . ' = ' . $this->post_get[$key] . ' and ';
					}
					else if( $field_info['type'] == 'string' && !empty($this->post_get[$key]) ){

						$where .= $field_info['column'] . ' like
						 "%'. $this->post_get[$key] . '%" and ';
					}
					else if( $field_info['type'] == 'array' && is_array($this->post_get[$key]) && !empty($this->post_get[$key]) ){

						$where .= $field_info['column'] . ' in ( '.implode(',',$this->post_get[$key]).') and ';
					}
					else if( $field_info['type'] == 'date' ){

						if( isset($this->post_get['date_start']) && !empty($this->post_get['date_start']) ){

							$date_start = str_replace('/', '-', $this->post_get['date_start']);
							$date_start = date('Y-m-d',strtotime($date_start));

							if( isset($this->post_get['date_end']) && !empty($this->post_get['date_end']) ){

								$date_end = str_replace('/', '-', $this->post_get['date_end']);
								$date_end = date('Y-m-d 23:59:59',strtotime($date_end));
								$where .= $field_info['column'].' between "'.$date_start.'" AND "'.$date_end.'" and ';
							}

						}
					}
				}
			}
		}

		if( !empty($this->initial_where) ){

			if( !empty($where) ){

				$where = $this->initial_where . ' and ' . $where;
			}
			else{

				$where = $this->initial_where;
			}
		}

		if( !empty($where) ){

			$where = ' where ' . $where;
			$last_and = strrpos($where,'and');

			if( $last_and ){

				$where = substr_replace($where,'',$last_and,strlen('and'));
			}
			
		}

		$this->query = $this->config['query'] . $where;
	}

	private function addDateFields(){


	}

	private function addGroupByOrder(){

		$order_by = '';

		if( isset($this->config['group_by']) ){

			$this->query .= ' group by '. implode(',', $this->config['group_by']);

			if( !empty($this->config['fields']) ){

				$having = '';

				foreach( $this->config['fields'] as $key => $field_info ){

					if( isset($field_info['having']) && isset($this->post_get[$key]) ){

						if( $field_info['type'] == 'numeric' && is_numeric($this->post_get[$key]) ){

							if( isset( $this->post_get[$field_info['column'].'_cmp'] ) && !empty($this->post_get[$field_info['column'].'_cmp'] ) ){

								if( $this->post_get[$field_info['column'].'_cmp'] == 'gt' ){

									$having .= ' CAST('.$field_info['column'] . ' as decimal) > ' . $this->post_get[$key] . ' and ';
								}
								else if( $this->post_get[$field_info['column'].'_cmp'] == 'lt' ){

									$having .= ' CAST('.$field_info['column'] . ' as decimal) < ' . $this->post_get[$key] . ' and ';
								}
								else{

									$having .= ' CAST('.$field_info['column'] . ' as decimal) = ' . $this->post_get[$key] . ' and ';
								}
							}
						}
						else if( $field_info['type'] == 'string' && !empty($this->post_get[$key]) ){

							$having .= $field_info['column'] . ' like "%'. $this->post_get[$key] . '%" and ';
						}
						else if( $field_info['type'] == 'array_strings' && !empty($this->post_get[$key]) ){

							$array_strings = explode(',',$this->post_get[$key]);

							if( !empty($array_strings) ){

								foreach( $array_strings as $str ){

									$having .= $field_info['column'] . ' like "%'. trim($str) . '%" or ';
									$or = TRUE;
								}
							}
						}
					}
				}

				if( !empty($having) ){

					$end_rpl = trim( substr($having,-4) ) == 'and' ? 'and' : 'or';

					$last_and = strrpos($having,$end_rpl);

					$this->query .= ' having ' . substr_replace($having,'',$last_and,strlen($end_rpl));

				}
			}
		}

		if( !empty($this->config['fields']) ){

			$tmp_order_by = '';

			foreach( $this->post_get as $field => $val ){

				if( strstr($field, 'order_') ){

					$field_name = substr($field,6);

					if( array_key_exists($field_name, $this->config['fields'] ) ){

						$column = $this->config['fields'][$field_name]['column'];
						$type = $this->config['fields'][$field_name]['type'];

						if( $val == 'asc' || $val == 'desc' ){

							if( $type == 'numeric' )
								$tmp_order_by .= 'cast('.$column.' as int) '.$val.',';
							else
								$tmp_order_by .= $column.' '.$val.',';
						}
					}
				}
			}

			if( !empty($tmp_order_by) ){

				$tmp_order_by = ' order by ' . $tmp_order_by;
				$order_by = rtrim($tmp_order_by,',');
			}
		}

		if( empty($order_by) && isset($this->config['order_by']) ){

			$order_by = ' order by ';

			foreach( $this->config['order_by'] as $field => $mode ){

				$order_by .= $field . ' ' . $mode . ',';
			}

			$order_by = rtrim($order_by,',');
		}

		$this->query .= $order_by;

	}

	private function addLimit(){

		$start = isset($this->post_get['start']) && is_numeric($this->post_get['start']) ? $this->post_get['start'] : 0;
		$limit = isset($this->config['limit']) ? $this->config['limit'] : $this->default_limit;
		$this->config['limit'] = $limit;

		$this->query .= ' limit ' . $start . ',' . $limit;
	}

	public static function getFieldOrder( $field ){

		$get_fields = $_GET;
		$field_order = array();

		if( !isset($get_fields[$field]) ){

			$get_fields[$field] = 'desc';
			$field_order['mode'] = 'desc';
 		}
 		else{

 			$order_mode = $get_fields[$field];
			$field_order['mode'] = $order_mode == 'desc' ? 'asc' : 'desc';
			$get_fields[$field] = $field_order['mode'];
 		}

 		$link_url = current_url() . '/?' . http_build_query($get_fields);

 		$field_order['link'] = $link_url;

		return $field_order;
	}

	public static function getValue( $field ){

		return isset($_GET[$field]) ? $_GET[$field] : '';
	}

	public static function postValue( $field ){

		return isset($_POST[$field]) ? $_POST[$field] : '';
	}
}