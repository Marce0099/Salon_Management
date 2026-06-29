<?php if( !defined('BASEPATH') ){ exit(); }

class Statistics extends Admin_Controller{

	public function __construct(){

		parent::__construct();
	}

	public function index(){
	}

	public function sales(){

		$time = strtotime(date('Y-m-d'));
		$week_start = date('Y-m-d',strtotime("last Sunday", $time));
		$week_end = date('Y-m-d',strtotime("next Saturday", $time));
	}
}