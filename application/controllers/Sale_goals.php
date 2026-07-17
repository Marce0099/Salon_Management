<?php if( !defined('BASEPATH') ){ exit(); }

class Sale_goals extends Admin_Controller{

	public function __construct(){

		parent::__construct();
        $this->load->model('sale_goals_mdl');
	}

	public function index(){

		if(!$this->is_admin()){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->library('easysearch');

        $search['query'] = 'SELECT
        g.id_goal,
        g.month_goal,
        g.year_goal,
        g.amount,
        prof.Ganancias
        FROM goals g
        LEFT JOIN 
        (
            SELECT 
            YEAR(S.date_time) AS Año, 
            MONTH(S.date_time) AS Mes,
            SUM(ROUND(SP.quantity * (SP.price - (SP.price * (SP.discount / 100))),2)) AS "Ganancias"
            FROM sales as S
            INNER JOIN sale_products as SP on S.id_sale = SP.id_sale
            GROUP BY Año, Mes
        ) prof ON prof.Año=g.year_goal and prof.Mes=g.month_goal';
        $search['order_by'] = array( 'year_goal' => 'DESC','month_goal' => 'DESC'  );
        $search['return_as'] = 'sale_goal';
        $search['limit'] = 100;
        $search['fields'] = array(

                'month_goal' => array( 'column' => 'month_goal', 'type' => 'numeric' ),
                'year_goal' => array( 'column' => 'year_goal', 'type' => 'numeric' )
                //'amount' => array( 'column' => 'amount', 'type' => 'decimal' )
            );

        $data = $this->easysearch->search($search);

		$this->content_data = $data;
		$this->footer_data = load_data($this->session);
		$this->renderPanel('sale_goal');
	}

    public function register(){

        if( formPosted() ){

            $this->form_validation->set_rules('month','','required|numeric|max_length[2]|greater_than[0]|less_than[13]');
            $this->form_validation->set_rules('year','','required|numeric|exact_length[4]');
            $this->form_validation->set_rules('goal','','required|numeric');

            if( $this->form_validation->run() ){

                $data = $this->input->post();

                try {
                    if(!$this->sale_goals_mdl->save($data)){
                        $view_data['status'] = array( 'class' => 'error', 'message' => 'No se ha podido guardar cambios' );
                        $this->form($view_data);
                    }

                    $view_data['status'] = array( 'class' => 'success', 'message' => 'Se ha guardado meta' );
                    $this->session->set_userdata('view_data',$view_data);
                    redirect('sale_goals');
                } catch (\Throwable $th) {
                    $view_data['status'] = array( 'class' => 'error', 'message' => 'Ya existe registro de meta de venta' );
                    $this->form($view_data);
                }
                
            }
            else{

                $view_data['status'] = array( 'class' => 'error', 'message' => 'Datos invalidos' );
                $this->form($view_data);
            }
        }
        else{

            $this->form();
        }
    }

    private function form( $view_data = array() ){

        $this->footer_data = $view_data;
        $this->renderPanel('form_sale_goals');
    }
}