<?php if( !defined('BASEPATH') ){ exit(); }

/** Table reservations: list per day, create, cancel, mark no-show, seat (opens the table's tab). JSON {type,message}. */
class Reservations extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('reservations_mdl');
		$this->load->model('floor_mdl');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	private function requirePost(){

		if( !formSubmited() ){
			$this->fail('Sesión inválida, recargue la página e intente de nuevo');
		}
	}

	public function index(){

		$tk = $this->session->userdata('tk');
		if( !$tk ){
			$tk = generateToken();
			$this->session->set_userdata('tk', $tk);
		}

		$date = $this->input->get('date');
		if( !$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ){
			$date = date('Y-m-d');
		}

		$this->load->model('settings_mdl');

		$this->content_data = array_merge($this->content_data, array(
			'tk'               => $tk,
			'date'             => $date,
			'reservations'     => $this->reservations_mdl->byDate($date),
			'tables'           => $this->reservations_mdl->tables(),
			'preselect_table'  => (int) $this->input->get('table'),
			'default_minutes'  => (int) $this->settings_mdl->get('reservation_default_minutes', 90),
			'employees'        => $this->db->select('id_employee, name')->where('active', 1)->order_by('name')->get('employees')->result(),
		));

		$this->renderPanel('reservations');
	}

	public function save(){

		$this->requirePost();

		$id_table = (int) $this->input->post('id_table');
		$name     = trim((string) $this->input->post('name'));
		$phone    = trim((string) $this->input->post('phone'));
		$party    = (int) $this->input->post('party_size');
		$minutes  = (int) $this->input->post('duration');
		$notes    = trim((string) $this->input->post('notes'));

		if( !$this->floor_mdl->table($id_table) ){
			$this->fail('Mesa no encontrada');
		}
		if( $name === '' || mb_strlen($name, 'UTF-8') > 120 ){
			$this->fail('Nombre del cliente inválido');
		}
		if( mb_strlen($phone, 'UTF-8') > 30 || mb_strlen($notes, 'UTF-8') > 255 ){
			$this->fail('Teléfono o notas demasiado largos');
		}
		if( $party < 1 || $party > 99 ){
			$this->fail('Número de personas inválido');
		}
		if( $minutes < 15 || $minutes > 480 ){
			$this->fail('Duración inválida (15 a 480 minutos)');
		}

		$start = DateTime::createFromFormat('Y-m-d H:i', $this->input->post('date').' '.$this->input->post('time'));
		if( !$start ){
			$this->fail('Fecha u hora inválida');
		}
		if( $start->getTimestamp() < time() - 15 * 60 ){
			$this->fail('La hora de la reserva ya pasó');
		}

		$end   = clone $start;
		$end->modify('+'.$minutes.' minutes');
		$start_s = $start->format('Y-m-d H:i:s');
		$end_s   = $end->format('Y-m-d H:i:s');

		if( $this->reservations_mdl->overlaps($id_table, $start_s, $end_s) ){
			$this->fail('La mesa ya tiene una reserva en ese horario');
		}

		$this->reservations_mdl->create(array(
			'id_table'   => $id_table,
			'name'       => $name,
			'phone'      => $phone !== '' ? $phone : NULL,
			'party_size' => $party,
			'start_at'   => $start_s,
			'end_at'     => $end_s,
			'notes'      => $notes !== '' ? $notes : NULL,
			'id_admin'   => $this->id_admin,
		));

		$this->json(array('type' => 'success', 'message' => 'Reserva registrada'));
	}

	private function activeReservation(){

		$r = $this->reservations_mdl->get($this->input->post('id_reservation'));
		if( !$r || $r->status !== 'active' ){
			$this->fail('La reserva ya no está activa');
		}
		return $r;
	}

	public function cancel(){

		$this->requirePost();
		$r = $this->activeReservation();
		$this->reservations_mdl->setStatus($r->id_reservation, 'cancelled');
		$this->json(array('type' => 'success', 'message' => 'Reserva cancelada'));
	}

	public function no_show(){

		$this->requirePost();
		$r = $this->activeReservation();
		$this->reservations_mdl->setStatus($r->id_reservation, 'no_show');
		$this->json(array('type' => 'success', 'message' => 'Reserva marcada como no llegó'));
	}

	/** Seat the party: open a tab for the table (guests = party size) and link it to the reservation. */
	public function seat(){

		$this->requirePost();
		$r      = $this->activeReservation();
		$id_emp = (int) $this->input->post('id_employee');

		if( !$id_emp || !$this->db->where('id_employee', $id_emp)->where('active', 1)->count_all_results('employees') ){
			$this->fail('Debe seleccionar un mesero');
		}

		$id_tab = $this->floor_mdl->openTab($r->id_table, $id_emp, $r->party_size, $this->id_admin);
		if( !$id_tab ){
			$this->fail('La mesa ya está ocupada');
		}

		$this->reservations_mdl->setStatus($r->id_reservation, 'seated', $id_tab);
		$this->json(array('type' => 'success', 'message' => 'Mesa abierta para la reserva', 'id_tab' => $id_tab));
	}
}
