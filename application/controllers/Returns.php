<?php if( !defined('BASEPATH') ){ exit(); }

class Returns extends Admin_Controller{

	public function __construct(){
		parent::__construct();
		$this->load->model('returns_mdl');
	}

    public function index(){

        $this->load->library('easysearch');

        $search['query'] = 'select R.date_time,RP.*,P.provider from returns R left outer join providers P on
        R.id_provider = P.id_provider inner join returns_products RP on R.id_return = RP.id_return';
        $search['order_by'] = array( 'R.id_return' => 'desc' );
        $search['group_by'] = array('R.id_return');
        $search['return_as'] = 'returns';
        $search['limit'] = 100;
        $search['fields'] = array(

                'id_provider' => array( 'column' => 'P.id_provider', 'type' => 'numeric' ),
                'date_start' => array( 'column' => 'date_time', 'type' => 'date' ),
                'date_end' => array( 'column' => 'date_time', 'type' => 'date' )
            );

        $data = $this->easysearch->search($search);

        foreach( $data['returns'] as $r ){

            $sql = 'select sum(RP.quantity * RP.price_cost) as total_cost from returns_products RP inner join returns R
            on RP.id_return = R.id_return where RP.id_return = '.$r->id_return;

            $r->total_cost = $this->db->query($sql)->row('total_cost');
        }

        $data['totals'] = $this->returns_mdl->getTotalReturns(array_filter($_GET),$data['query_no_limit']);

        $data['providers'] = $this->db->query('select * from providers')->result();
        $this->content_data = $data;
        $this->footer_data = load_data($this->session);

        $this->renderPanel('returns');
    }

    public function register(){

        if( formPosted() ){

            $this->form_validation->set_rules('id_provider',         '', 'required|is_natural_no_zero');
            $this->form_validation->set_rules('id_invoice',          '', 'required|is_natural_no_zero');
            $this->form_validation->set_rules('date_time',           '', 'required');
            $this->form_validation->set_rules('id_products[]',       '', 'required');
            $this->form_validation->set_rules('quantities_returns[]','', 'required');

            if( !formSubmited() ){

                // CSRF: the form's session token is missing or stale
                $view_data['status'] = array( 'class' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo' );
                $this->form($view_data);
                return;
            }

            if( $this->form_validation->run() ){

                $data        = $this->input->post();
                $id_provider = (int)$data['id_provider'];
                $id_invoice  = (int)$data['id_invoice'];

                $invoice = $this->db->get_where('invoices', array(
                    'id_invoice'  => $id_invoice,
                    'id_provider' => $id_provider,
                ))->row();

                if( !$invoice ){
                    $view_data['status'] = array( 'class' => 'error', 'message' => 'La factura seleccionada no pertenece al proveedor indicado' );
                    $this->form($view_data);
                    return;
                }

                $date = date('Y-m-d', strtotime( str_replace('/', '-', $data['date_time']) ));

                $this->db->trans_begin();

                try{

                    $id_return = $this->returns_mdl->save($id_provider, $date, $id_invoice);
                    $this->returns_mdl->saveProductsReturn(
                        $id_return, $data['id_products'], $data['quantities_returns'], $id_invoice
                    );

                    if( $this->db->trans_status() === FALSE ){
                        throw new Exception('No se ha podido registrar la devolución');
                    }

                    $this->db->trans_commit();

                    $view_data['status'] = array( 'class' => 'success', 'message' => 'Devolución de productos registrada e inventario actualizado' );
                    $this->session->set_userdata('view_data', $view_data);
                    redirect('returns');
                }
                catch( Exception $e ){

                    $this->db->trans_rollback();
                    $view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );
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
        $this->content_data['tk'] = generateToken();
        $this->session->set_userdata('tk', $this->content_data['tk']);
        $this->content_data['providers'] = $this->db->get_where('providers', array( 'active' => 1 ))->result();
        $this->renderPanel('form_returns');
    }

    public function getInvoicesByProvider(){

        $id_provider = $this->input->post('id_provider');

        if( $id_provider ){
            echo json_encode(array( 'invoices' => $this->returns_mdl->getInvoicesByProvider($id_provider) ));
        }
    }

    public function getProductsByInvoice(){

        $id_invoice = $this->input->post('id_invoice');

        if( $id_invoice ){
            echo json_encode(array( 'products' => $this->returns_mdl->getProductsByInvoice($id_invoice) ));
        }
    }

    public function details( $id_return = 0 ){

        $data['return'] = $this->returns_mdl->getReturnsDetails((int)$id_return);

        if( !$data['return'] ){

            $view_data['status'] = array( 'class' => 'error', 'message' => 'Datos invalidos' );
            $this->session->set_userdata('view_data',$view_data);
            redirect('returns');
            exit();
        }

        $this->content_data = $data;
        $this->renderPanel('return_details');
    }
}