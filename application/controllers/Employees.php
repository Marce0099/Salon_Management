<?php if( !defined('BASEPATH') ){ exit(); }

class Employees extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('employees_mdl');

		// vendedores, their data and everyone's commissions are admin-only
		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}
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

	private function importColumns(){

		return array(
			array('key' => 'name',  'label' => 'Nombre',   'required' => TRUE,  'text' => TRUE, 'width' => 35, 'note' => 'Texto, máximo 200 caracteres'),
			array('key' => 'phone', 'label' => 'Teléfono', 'required' => FALSE, 'text' => TRUE, 'width' => 20, 'note' => 'Texto, máximo 100 caracteres'),
		);
	}

	public function template(){

		$this->load->library('excel_import');
		$this->excel_import->sendTemplate('plantilla_vendedores.xlsx', $this->importColumns(), array(
			'Un vendedor se considera repetido cuando coincide el Nombre.',
		));
	}

	public function import(){

		$this->load->library('excel_import');

		if( !formSubmited() ){
			echo json_encode(array('type' => 'error', 'message' => 'Sesión inválida, recargue la página e intente de nuevo'));
			return;
		}

		$db = $this->db;

		$key = function($d){ return mb_strtolower(trim($d['name']), 'UTF-8'); };

		$response = $this->excel_import->run(array(
			'columns' => $this->importColumns(),
			'db' => $db,
			'validate' => function(&$d){

				if( $d['name'] === '' ) return 'El nombre es obligatorio';
				if( mb_strlen($d['name'], 'UTF-8') > 200 ) return 'El nombre supera 200 caracteres';
				if( mb_strlen($d['phone'], 'UTF-8') > 100 ) return 'El teléfono supera 100 caracteres';

				return NULL;
			},
			'key' => $key,
			'existing' => function() use ($db, $key){

				$keys = array();
				foreach( $db->select('name')->get('employees')->result_array() as $r ){
					$keys[] = $key(array('name' => (string) $r['name']));
				}
				return $keys;
			},
			'insert' => function($rows) use ($db){

				$batch = array();
				foreach( $rows as $d ){
					$batch[] = array( 'name' => $d['name'], 'phone' => $d['phone'] !== '' ? $d['phone'] : NULL );
				}

				if( $db->insert_batch('employees', $batch) !== count($batch) ){
					throw new Exception('insert_batch failed');
				}
			},
		));

		$response['tk'] = $this->issueToken();

		echo json_encode($response);
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