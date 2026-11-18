<?php if( !defined('BASEPATH') ){ exit(); }

class Returns extends Admin_Controller{

	public function __construct(){
		parent::__construct();

		// purchasing is admin-only (the menu already hides it; this blocks direct URLs and AJAX calls)
		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

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

            $this->form_validation->set_rules('id_invoice',          '', 'required|is_natural_no_zero');
            $this->form_validation->set_rules('date_time',           '', 'required');
            $this->form_validation->set_rules('id_lines[]',          '', 'required');
            $this->form_validation->set_rules('quantities_returns[]','', 'required');

            if( !formSubmited() ){

                // CSRF: the form's session token is missing or stale
                $view_data['status'] = array( 'class' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo' );
                $this->form($view_data);
                return;
            }

            if( $this->form_validation->run() ){

                $data       = $this->input->post();
                $id_invoice = (int)$data['id_invoice'];

                // the invoice is the source of truth: the provider (possibly none) is taken from it
                $invoice = $this->db->get_where('invoices', array( 'id_invoice' => $id_invoice ))->row();

                if( !$invoice ){
                    $view_data['status'] = array( 'class' => 'error', 'message' => 'La factura seleccionada no existe' );
                    $this->form($view_data);
                    return;
                }

                $id_provider = $invoice->id_provider === NULL ? NULL : (int)$invoice->id_provider;
                $posted      = trim((string)$this->input->post('id_provider'));

                // the provider select is only a filter; when a specific one was chosen it must match the invoice
                if( ( ctype_digit($posted) && (int)$posted > 0 && (int)$posted !== (int)$id_provider )
                    || ( $posted === 'none' && $id_provider !== NULL ) ){
                    $view_data['status'] = array( 'class' => 'error', 'message' => 'La factura seleccionada no pertenece al proveedor indicado' );
                    $this->form($view_data);
                    return;
                }

                $dt = DateTime::createFromFormat('d/m/Y', trim((string)$data['date_time']));
                $dt_errors = DateTime::getLastErrors();

                if( !$dt || ($dt_errors && ($dt_errors['warning_count'] || $dt_errors['error_count'])) ){

                    $view_data['status'] = array( 'class' => 'error', 'message' => 'Fecha inválida' );
                    $this->form($view_data);
                    return;
                }

                $date = $dt->format('Y-m-d');

                // deadlocks / lock timeouts (PDOException) are retried; business refusals (plain Exception) are shown as they are
                $error = NULL;

                for( $try = 0; $try < 3; $try++ ){

                    $this->db->trans_begin();

                    try{

                        $id_return = $this->returns_mdl->save($id_provider, $date, $id_invoice);
                        $this->returns_mdl->saveProductsReturn(
                            $id_return, $data['id_lines'], isset($data['modes']) ? $data['modes'] : array(), $data['quantities_returns'], $id_invoice
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

                        try{ $this->db->trans_rollback(); }catch( Exception $e2 ){ /* already rolled back by the server */ }

                        if( $e instanceof PDOException ){

                            log_message('error', 'Return failed (try '.($try + 1).'): '.$e->getMessage());
                            $error = 'Hay mucha actividad en este momento, intente de nuevo';
                            usleep(mt_rand(20000, 90000));
                            continue;
                        }

                        $error = $e->getMessage();
                        break;
                    }
                }

                $view_data['status'] = array( 'class' => 'error', 'message' => $error );
                $this->form($view_data);
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

        // shortcut from the invoice details page: returns/register?invoice={id}
        $this->content_data['prefill'] = NULL;
        $pre = $this->input->get('invoice');

        if( $pre !== NULL && ctype_digit((string)$pre) && (int)$pre > 0 ){
            $this->content_data['prefill'] = $this->returns_mdl->getInvoiceForReturn((int)$pre);
        }

        $this->renderPanel('form_returns');
    }

    public function getInvoicesByProvider(){

        // '' = all providers, 'none' = receipts without provider, digits = that provider
        $id_provider = trim((string)$this->input->post('id_provider'));

        if( $id_provider !== '' && $id_provider !== 'none' && ( !ctype_digit($id_provider) || (int)$id_provider < 1 ) ){
            echo json_encode(array( 'invoices' => array() ));
            return;
        }

        echo json_encode(array( 'invoices' => $this->returns_mdl->getInvoicesByProvider($id_provider) ));
    }

    public function getProductsByInvoice(){

        $id_invoice = $this->input->post('id_invoice');

        if( $id_invoice ){
            // one entry per invoice line (box line / loose line) with the units still returnable
            echo json_encode(array( 'products' => $this->returns_mdl->invoiceLines((int)$id_invoice) ));
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