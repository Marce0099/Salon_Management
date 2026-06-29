<?php if( !defined('BASEPATH') ) exit();

class Sale_goals_mdl extends CI_Model{

    public function __construct(){

        parent::__construct();
    }

    public function save( $data ){

        $dato = [
            'month_goal' => $data['month'],
            'year_goal' => $data['year'],
            'amount' => $data['goal']
        ];

    	return $this->db->insert( 'goals', $dato );
    }

    public function getMonths(){
        return [
            '1' => 'Enero',
            '2' => 'Febrero',
            '3' => 'Marzo',
            '4' => 'Abril',
            '5' => 'Mayo',
            '6' => 'Junio',
            '7' => 'Julio',
            '8' => 'Agosto',
            '9' => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre',
        ];
    }

    public function getMonthName($month){
        $months = $this->getMonths();

        return $months[$month];
    }
}