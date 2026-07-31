<?php if( !defined('BASEPATH') ){ exit(); }

class Employees extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('employees_mdl');
	}

	public function index(){

		$view_data = load_data($this->session);

		$this->load->library('easysearch');

		$search['query'] = 'select * from employees where active="1"';
		$search['return_as'] = 'employees';
		$data = $this->easysearch->search($search);
		
		$this->content_data = array_merge($data,$view_data);

		$this->renderPanel('employees');
	}

	private function setFormRules(){

		$this->form_validation->set_rules('name','trim|required');
	}

	public function form_employees( $view_data = array() ){

		$this->content_data = $view_data;
		$this->renderPanel('form_employees');
	}

	public function register(){

		if( formPosted() ){

			$this->setFormRules();

			try{

				if( $this->form_validation->run() ){

					$data = $this->input->post();

					$employee_code = $this->employees_mdl->save( $data );

					$response = array( 'type' => 'success', 'message' => 'Empleado registrado con el código: '.$employee_code );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');	
				}
			}
			catch( Exception $e ){

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode( $response );
			}
		}
		else{

			$this->form_employees();
		}
	}

	public function edit($id_employee = 0){

		try{

			$view_data['employee'] = $this->employees_mdl->getEmployee($id_employee);

			if( !$view_data['employee'] ){

				throw new Exception('Vendedor inválido',1);
			}

			if( formPosted() ){

				$this->setFormRules();

				if( $this->form_validation->run() ){

					$data = $this->input->post();

					$this->employees_mdl->edit( $id_employee, $data );

					$response = array( 'type' => 'success', 'message' => 'Vendedor editado', 'edit' => true );

					echo json_encode( $response );
				}
				else{

					throw new Exception('Hay valores inválidos en el formulario');
				}

			}
			else{

				$this->form_employees($view_data);
			}
		}	
		catch( Exception $e ){

			if( $e->getCode() == 1 ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

				$this->session->set_userdata( 'view_data', $sess_data );
				redirect('employees');
			}
			else{

				$response = array( 'type' => 'error', 'message' => $e->getMessage() );
				echo json_encode($response);
			}
		}
	}

	public function delete( $id_employee = 0 ){

		try{

			$employee = $this->employees_mdl->getEmployee( $id_employee );

			if( !$employee ){

				throw new Exception('Empleado inválido');
			}

			$this->employees_mdl->delete($id_employee);

			$view_data['status'] = array( 'class' => 'success', 'message' => 'Empleado eliminado' );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('employees');

		}
		catch( Exception $e ){

			$view_data['status'] = array( 'class' => 'error', 'message' => $e->getMessage() );

			$this->session->set_userdata( 'view_data', $view_data );
			redirect('employees');
		}
	}

	public function commissions(){

		if( formPosted() ){

			$id_employee = (int) $this->input->post('id_employee');
			$date_start = $this->input->post('date_start');
			$date_end = $this->input->post('date_end');

			$data['employee'] = $this->employees_mdl->getEmployee($id_employee);
			$data['sales'] = $this->employees_mdl->getSales($id_employee,$date_start,$date_end);
			$data['interface'] = $this->employees_mdl;
			$data['date_start'] = $date_start;
			$data['date_end'] = $date_end;

			$this->content_data = $data;
			$this->renderPanel('employee_commissions');
		}
		else{

			$data['employees'] = $this->employees_mdl->getActiveEmployees();
			$data['test'] = $this->employees_mdl;
			$this->content_data = $data;
			$this->renderPanel('form_commissions');
		}
	}
}