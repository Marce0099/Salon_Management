<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * Shift inventory report: administrators (and super) count what each storage holds. Informational only.
 * Super-only: editing a counted storage, cancelling a shift (storages are registered in Storages).
 */
class Shift_inventory extends Admin_Controller{

	public function __construct(){

		parent::__construct();

		if( !$this->is_admin() ){
			echo "No se encontro resultado para esta pagina";
			exit;
		}

		$this->load->model('shift_inventory_mdl', 'inv');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message, $extra = array()){

		$this->json(array_merge(array('type' => 'error', 'message' => $message), $extra));
	}

	private function ok($message, $extra = array()){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		$this->json(array_merge(array('type' => 'success', 'message' => $message, 'tk' => $tk), $extra));
	}

	private function token(){

		$tk = generateToken();
		$this->session->set_userdata('tk', $tk);
		return $tk;
	}

	private function flash($class, $message, $to){

		$this->session->set_userdata('view_data', array('status' => array('class' => $class, 'message' => $message)));
		redirect($to);
	}

	private function cleanText($v, $max){

		$v = trim(preg_replace('/\s+/', ' ', (string) $v));
		if( mb_strlen($v, 'UTF-8') > $max || preg_match('/[<>]/', $v) || preg_match('/[\x{10000}-\x{10FFFF}]/u', $v) ){ return NULL; }

		return $v;
	}

	/** Spanish text for a refused write. */
	private function refusal($code, $data){

		switch( $code ){
			case 'closed':   return 'Este inventario de turno ya está cerrado: no se aceptan más registros';
			case 'taken':    return 'Este almacén ya fue inventariado por '.htmlspecialchars((string) $data->counted_by_name, ENT_QUOTES, 'UTF-8').' a las '.date('h:i A', strtotime($data->counted_at)).'. Solo el super puede editarlo';
			case 'notfound': return 'Registro no encontrado';
			case 'busy':     return 'Hay mucha actividad, intente de nuevo en unos segundos';
			case 'pending':  return 'Aún hay '.(int) $data.' almacén(es) sin inventariar';
			case 'exists':   return 'Ya hay un inventario de turno abierto';
			case 'nostorage':return 'No hay almacenes activos. El super debe registrarlos en Almacenes';
			default:         return is_string($data) && $data !== '' ? $data : 'No se pudo completar la operación';
		}
	}

	public function index(){

		$this->footer_data = load_data($this->session);
		$this->content_data = array(
			'current' => $this->inv->current(),
			'history' => $this->inv->history(),
			'tk' => $this->token(),
			'active_storages' => (int) $this->db->where('active', 1)->count_all_results('inv_storages'),
		);
		$this->renderPanel('shift_inventory');
	}

	/** POST: opens a report (all admins may open it). */
	public function open(){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }

		$label = $this->cleanText($this->input->post('label'), 80);
		if( $label === NULL ){ $this->fail('El nombre del turno es inválido (máx. 80 caracteres, sin símbolos < >)'); }
		if( $label === '' ){ $label = 'Turno '.date('d/m/Y H:i'); }

		list($code, $data) = $this->inv->openShift($label);
		if( $code !== 'ok' ){
			$this->audit->denied('shift_inv.open', 'inv_shift', NULL, array('summary' => 'Apertura de inventario de turno rechazada: '.$code));
			$this->fail($this->refusal($code, $data));
		}

		$this->ok('Inventario de turno abierto', array('redirect' => base_url('shift_inventory/view/'.(int) $data)));
	}

	/** Centralized page: storage status, combined totals, close/print. */
	public function view($id = 0){

		$shift = $this->inv->get($id);
		if( !$shift ){ $this->flash('error', 'Inventario de turno inválido', 'shift_inventory'); }

		$storages = $this->inv->storages($shift->id_shift);
		$pending = 0;
		foreach( $storages as $s ){ if( $s->status === 'pending' ){ $pending++; } }

		$this->footer_data = load_data($this->session);
		$this->content_data = array(
			'shift' => $shift, 'storages' => $storages, 'pending' => $pending, 'tk' => $this->token(),
			'lines' => $this->inv->linesByStorage($shift->id_shift),
			'totals' => $this->inv->totals($shift->id_shift),
		);
		$this->renderPanel('shift_inventory_view');
	}

	/** Count form of one storage. Counted storages: only super (edit) while the shift is open. */
	public function count($id_ss = 0){

		$ss = $this->inv->storage($id_ss);
		if( !$ss ){ $this->flash('error', 'Almacén inválido', 'shift_inventory'); }

		$back = 'shift_inventory/view/'.(int) $ss->id_shift;

		if( $ss->shift_status !== 'open' ){ $this->flash('warning', $this->refusal('closed', NULL), $back); }

		if( $ss->status === 'counted' && !$this->is_super() ){
			$this->audit->denied('shift_inv.edit', 'inv_shift_storage', (int) $id_ss, array('summary' => 'Edición rechazada: '.$ss->storage_name.' ya inventariado (solo super)'));
			$this->flash('warning', $this->refusal('taken', $ss), $back);
		}

		$lines = array();
		foreach( $this->inv->lines($id_ss) as $l ){
			$lines[] = array('id_product' => (int) $l->id_product, 'name' => $l->product_name, 'barcode' => $l->barcode, 'id_pack' => $l->id_pack === NULL ? NULL : (int) $l->id_pack, 'qty' => (int) $l->qty, 'note' => (string) $l->note);
		}

		$this->footer_data = load_data($this->session);
		$this->content_data = array('ss' => $ss, 'lines' => $lines, 'tk' => $this->token(), 'is_edit' => $ss->status === 'counted');
		$this->renderPanel('form_shift_count');
	}

	/** POST: lines = JSON [{id_product, id_pack|null, qty, note}]. First save wins. */
	public function count_save($id_ss = 0){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }

		$ss = $this->inv->storage($id_ss);
		if( !$ss ){ $this->fail('Almacén inválido'); }

		$raw = json_decode((string) $this->input->post('lines'), TRUE);
		if( !is_array($raw) || count($raw) > Shift_inventory_mdl::MAX_LINES ){ $this->fail('Lista de productos inválida (máx. '.Shift_inventory_mdl::MAX_LINES.' líneas)'); }

		$empty = (int) $this->input->post('is_empty') === 1;
		if( $empty && $raw ){ $this->fail('Un almacén vacío no puede tener productos'); }
		if( !$empty && !$raw ){ $this->fail('Agregue productos o marque el almacén como vacío'); }

		$lines = array();
		foreach( $raw as $r ){

			$idp = isset($r['id_product']) ? (string) $r['id_product'] : '';
			$qty = isset($r['qty']) ? (string) $r['qty'] : '';
			if( !preg_match('/^\d{1,9}$/', $idp) ){ $this->fail('Hay un producto inválido'); }
			if( !preg_match('/^\d{1,6}$/', $qty) || (int) $qty < 1 ){ $this->fail('La cantidad debe ser un número entero mayor que 0'); }

			$pack = isset($r['id_pack']) && $r['id_pack'] !== NULL && $r['id_pack'] !== '' ? (string) $r['id_pack'] : NULL;
			if( $pack !== NULL && !preg_match('/^\d{1,9}$/', $pack) ){ $this->fail('Presentación inválida'); }

			$note = $this->cleanText(isset($r['note']) ? $r['note'] : '', 150);
			if( $note === NULL ){ $this->fail('Una nota es inválida (máx. 150 caracteres, sin símbolos < >)'); }

			$lines[] = array('id_product' => (int) $idp, 'id_pack' => $pack === NULL ? NULL : (int) $pack, 'qty' => (int) $qty, 'note' => $note === '' ? NULL : $note);
		}

		$notes = $this->cleanText($this->input->post('notes'), 255);
		if( $notes === NULL ){ $this->fail('Las observaciones son inválidas (máx. 255 caracteres, sin símbolos < >)'); }

		// A counted storage can only be edited by super; everyone else loses the race.
		$edit = $ss->status === 'counted' && $this->is_super();

		list($code, $data) = $this->inv->saveCount((int) $id_ss, $lines, $empty, $notes === '' ? NULL : $notes, $edit);

		if( $code !== 'ok' ){

			if( $code === 'taken' ){
				$this->audit->denied('shift_inv.count', 'inv_shift_storage', (int) $id_ss, array('summary' => 'Conteo rechazado: '.$ss->storage_name.' ya fue inventariado por '.$data->counted_by_name));
			}
			elseif( $code === 'closed' ){
				$this->audit->denied('shift_inv.count', 'inv_shift_storage', (int) $id_ss, array('summary' => 'Conteo rechazado: inventario de turno cerrado ('.$ss->storage_name.')'));
			}

			$this->fail($this->refusal($code, $data), array('code' => $code, 'redirect' => base_url('shift_inventory/view/'.(int) $ss->id_shift)));
		}

		$this->ok($edit ? 'Conteo corregido' : 'Almacén inventariado', array('redirect' => base_url('shift_inventory/view/'.(int) $ss->id_shift)));
	}

	/** POST: marks the report done (every storage counted). */
	public function close($id = 0){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }

		list($code, $data) = $this->inv->close((int) $id);
		if( $code !== 'ok' ){
			$this->audit->denied('shift_inv.close', 'inv_shift', (int) $id, array('summary' => 'Cierre de inventario de turno rechazado: '.$code));
			$this->fail($this->refusal($code, $data));
		}

		$this->ok('Inventario de turno terminado', array('redirect' => base_url('shift_inventory/view/'.(int) $id)));
	}

	/** POST (super): voids an open report. */
	public function cancel($id = 0){

		if( !formSubmited() ){ $this->fail('Sesión inválida, recargue la página e intente de nuevo'); }

		if( !$this->is_super() ){
			$this->audit->denied('shift_inv.cancel', 'inv_shift', (int) $id, array('summary' => 'Anulación rechazada: requiere super'));
			$this->fail('Solo el super puede anular un inventario de turno');
		}

		list($code, $data) = $this->inv->cancel((int) $id);
		if( $code !== 'ok' ){ $this->fail($this->refusal($code, $data)); }

		$this->ok('Inventario de turno anulado', array('redirect' => base_url('shift_inventory')));
	}

	/** Standalone printable report (done or open shifts; open ones show a "borrador" mark). */
	public function print_report($id = 0){

		$shift = $this->inv->get($id);
		if( !$shift ){ $this->flash('error', 'Inventario de turno inválido', 'shift_inventory'); }

		$this->load->model('settings_mdl');

		$this->load->view('shift_inventory_print', array(
			'shift' => $shift, 'storages' => $this->inv->storages($shift->id_shift),
			'lines' => $this->inv->linesByStorage($shift->id_shift), 'totals' => $this->inv->totals($shift->id_shift),
			'company' => $this->settings_mdl->get('company_name'),
		));
	}
}
