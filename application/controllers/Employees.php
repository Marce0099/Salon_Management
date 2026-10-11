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
		$this->content_data['tk'] = $this->issueToken();

		$this->renderPanel('employees');
	}

	private function issueToken(){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);

		return $tk;
	}

	private function setFormRules(){

		$this->form_validation->set_rules('name','Nombre','trim|required|max_length[200]');
		$this->form_validation->set_rules('phone','Teléfono','trim|max_length[100]');
	}

	// only these columns may be written from the form (the raw POST used to be passed straight to
	// the model, so `active` or `id_employee` could be overwritten)
	private function formData(){

		return array(
			'name' => $this->input->post('name'),
			'phone' => $this->input->post('phone'),
		);
	}

	public function form_employees( $view_data = array() ){

		$view_data['tk'] = $this->issueToken();
		$this->content_data = $view_data;
		$this->renderPanel('form_employees');
	}

	public function register(){

		if( formPosted() ){

			$this->setFormRules();

			try{

				if( !formSubmited() ){

					throw new Exception('Sesión inválida, recargue la página e intente de nuevo');
				}

				if( $this->form_validation->run() ){

					$employee_code = $this->employees_mdl->save( $this->formData() );

					$response = array( 'type' => 'success', 'message' => 'Empleado registrado con el código: '.$employee_code, 'tk' => $this->issueToken() );

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

			if( !$view_data['employee'] || !$view_data['employee']->active ){

				throw new Exception('Vendedor inválido',1);
			}

			if( formPosted() ){

				$this->setFormRules();

				if( !formSubmited() ){

					throw new Exception('Sesión inválida, recargue la página e intente de nuevo');
				}

				if( $this->form_validation->run() ){

					$this->employees_mdl->edit( $id_employee, $this->formData() );

					$response = array( 'type' => 'success', 'message' => 'Vendedor editado', 'edit' => true, 'tk' => $this->issueToken() );

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

			// state-changing GET: the link carries the page's form token (?tk=)
			if( !$this->input->get('tk') || $this->input->get('tk') !== $this->session->userdata('tk') ){

				throw new Exception('Sesión inválida, recargue la página e intente de nuevo');
			}

			$employee = $this->employees_mdl->getEmployee( $id_employee );

			if( !$employee || !$employee->active ){

				throw new Exception('Empleado inválido');
			}

			$this->employees_mdl->delete($id_employee);
			$this->issueToken();

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

			$employee = $this->employees_mdl->getEmployee($id_employee);
			$start = DateTime::createFromFormat('!d-m-Y', (string) $date_start);
			$end = DateTime::createFromFormat('!d-m-Y', (string) $date_end);

			$error = NULL;

			if( !$employee ){
				$error = 'Vendedor inválido';
			}
			elseif( !$start || !$end || $start->format('d-m-Y') !== $date_start || $end->format('d-m-Y') !== $date_end ){
				$error = 'Fecha inválida';
			}
			elseif( $start > $end ){
				$error = 'La fecha inicial no puede ser posterior a la final';
			}

			if( $error ){

				$sess_data['status'] = array( 'class' => 'error', 'message' => $error );
				$this->session->set_userdata( 'view_data', $sess_data );
				redirect('employees/commissions');
			}

			$data['employee'] = $employee;
			$data['sales'] = $this->employees_mdl->getSales($id_employee,$date_start,$date_end);
			$data['interface'] = $this->employees_mdl;
			$data['id_employee'] = $id_employee;
			$data['date_start'] = $date_start;
			$data['date_end'] = $date_end;

			$this->content_data = $data;
			$this->renderPanel('employee_commissions');
		}
		else{

			$data['employees'] = $this->employees_mdl->getActiveEmployees();
			$data['test'] = $this->employees_mdl;
			$this->content_data = array_merge($data, load_data($this->session));
			$this->renderPanel('form_commissions');
		}
	}
}