<?php if( !defined('BASEPATH') ){ exit(); }

require_once FCPATH.'print/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/**
 * Ordering on an open tab + kitchen ticket (comanda). Stock leaves when an order is sent and returns when a line is voided/replaced;
 * bill checkout does not deduct it again. A print failure never fails the order.
 */
class Kitchen extends Admin_Controller{

	public function __construct(){

		parent::__construct();
		$this->load->model('kitchen_mdl');
		$this->load->model('settings_mdl');
	}

	private function json($data){

		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	private function fail($message){

		$this->json(array('type' => 'error', 'message' => $message));
	}

	/** Stock rejection: same error JSON plus the fresh availability of every stocked/recipe product so the screen can update itself. */
	private function failStock($message){

		$avail = array();
		foreach( $this->kitchen_mdl->menu() as $m ){
			if( $m->stocked ){ $avail[$m->id] = (int) $m->available; }
		}

		$this->json(array('type' => 'error', 'message' => $message, 'availability' => $avail));
	}

	private function requirePost(){

		if( !formSubmited() ){
			$this->fail('Sesión inválida, recargue la página e intente de nuevo');
		}
	}

	private function notFound(){

		echo "No se encontro resultado para esta pagina";
		exit;
	}

	/** Order-taking screen for an open tab. */
	public function tab($id_tab = 0){

		$tab = $this->kitchen_mdl->tab($id_tab);
		if( !$tab || $tab->status !== 'open' ){
			redirect('floor');
		}

		$tk = $this->session->userdata('tk');
		if( !$tk ){
			$tk = generateToken();
			$this->session->set_userdata('tk', $tk);
		}

		$this->load->model('bills_mdl');

		$this->content_data = array_merge($this->content_data, array(
			'tk'         => $tk,
			'tab'        => $tab,
			'open_bills' => $this->bills_mdl->openBills($id_tab),
			'menu'       => $this->kitchen_mdl->menu(),
			'categories' => $this->kitchen_mdl->categories(),
			'orders'     => $this->kitchen_mdl->tabOrders($id_tab),
			'total'      => $this->kitchen_mdl->tabTotal($id_tab),
			'is_admin'   => $this->is_admin(),
			'rate'       => $this->bills_mdl->sellRate(),
		));

		$this->renderPanel('kitchen_tab');
	}

	public function send(){

		$this->requirePost();

		$tab = $this->kitchen_mdl->tab($this->input->post('id_tab'));
		if( !$tab || $tab->status !== 'open' ){
			$this->fail('La mesa ya no está abierta');
		}

		$raw = json_decode((string) $this->input->post('items'), TRUE);
		if( !is_array($raw) || !count($raw) || count($raw) > 60 ){
			$this->fail('No hay productos en el pedido');
		}

		$lines = array();
		$asked = array(); // qty per stocked product across lines
		$asked_recipe = array(); // portions per recipe dish across lines

		foreach( $raw as $r ){

			$qty = isset($r['quantity']) ? (int) $r['quantity'] : 0;
			if( $qty < 1 || $qty > 99 ){
				$this->fail('Cantidad inválida');
			}

			$p = $this->kitchen_mdl->product(isset($r['id_product']) ? $r['id_product'] : 0);
			if( !$p ){
				$this->fail('Producto no disponible');
			}

			$notes  = isset($r['notes']) ? trim((string) $r['notes']) : '';
			$course = isset($r['course']) ? (int) $r['course'] : 1;
			if( mb_strlen($notes, 'UTF-8') > 255 ){
				$this->fail('La nota es demasiado larga');
			}
			if( $course < 1 || $course > 5 ){
				$course = 1;
			}

			if( !$p->is_service ){
				$asked[(int) $p->id_product] = (isset($asked[(int) $p->id_product]) ? $asked[(int) $p->id_product] : 0) + $qty;
				$avail = $this->kitchen_mdl->available($p->id_product);
				if( $asked[(int) $p->id_product] > $avail ){
					$this->failStock('Stock insuficiente de '.$p->name.' (disponible: '.$avail.')');
				}
			}

			// recipe dishes: the ingredient stock must cover every portion asked (across all lines of the order)
			$portions = $this->kitchen_mdl->recipePortions($p->id_product);
			if( $portions !== NULL ){
				$asked_recipe[(int) $p->id_product] = (isset($asked_recipe[(int) $p->id_product]) ? $asked_recipe[(int) $p->id_product] : 0) + $qty;
				if( $asked_recipe[(int) $p->id_product] > $portions ){
					$this->failStock($portions > 0 ? 'Solo quedan '.$portions.' porciones de '.$p->name.' (ingredientes insuficientes)' : $p->name.' está agotado (faltan ingredientes)');
				}
			}

			$lines[] = array(
				'id_product' => (int) $p->id_product, 'name' => $p->name, 'quantity' => $qty, 'price' => (float) $p->price_sell,
				'notes' => $notes, 'course' => $course, 'prints_kitchen' => (int) $p->prints_kitchen,
			);
		}

		// target bill: the one chosen on screen if it is an open bill of this tab, else the first open bill (or a new one)
		$this->load->model('bills_mdl');
		$id_bill = 0;
		$chosen  = $this->bills_mdl->get($this->input->post('id_bill'));
		if( $chosen && (int) $chosen->id_tab === (int) $tab->id_tab && $chosen->status === 'open' && !$chosen->parent_bill ){
			$id_bill = (int) $chosen->id_bill;
		}
		else{
			$open = $this->bills_mdl->openBills($tab->id_tab);
			$id_bill = $open ? (int) $open[0]->id_bill : $this->bills_mdl->create($tab->id_tab);
		}

		$id_order = $this->kitchen_mdl->createOrder($tab->id_tab, $this->id_admin, $lines, $id_bill);
		if( $id_order === -1 ){
			$this->failStock('Otro pedido tomó las existencias primero. Se actualizó la disponibilidad');
		}
		if( $id_order === -2 ){
			$this->failStock('Otro pedido tomó los ingredientes primero ('.$this->kitchen_mdl->short_ingredient.'). Se actualizó la disponibilidad');
		}
		if( $id_order === -3 ){
			$this->fail('Hay mucha actividad en este momento, intente de nuevo');
		}
		if( !$id_order ){
			$this->fail('No se pudo guardar el pedido');
		}

		$print = $this->printTicket($id_order);

		$this->json(array(
			'type'     => 'success',
			'message'  => $print['ok'] ? 'Pedido enviado a cocina' : 'Pedido guardado, pero no se pudo imprimir la comanda',
			'printed'  => $print['ok'],
			'id_order' => $id_order,
		));
	}

	public function reprint(){

		$this->requirePost();

		if( !$this->kitchen_mdl->order($this->input->post('id_order')) ){
			$this->fail('Pedido no encontrado');
		}

		$print = $this->printTicket((int) $this->input->post('id_order'));

		$this->json(array('type' => $print['ok'] ? 'success' : 'error', 'message' => $print['ok'] ? 'Comanda reimpresa' : 'No se pudo imprimir: '.$print['error']));
	}

	/** Void a sent line, whole or `quantity` units of it (waiters and admins; waiters cannot touch collected bills). */
	public function void_item(){

		$this->requirePost();

		$item   = $this->kitchen_mdl->item($this->input->post('id_order_item'));
		$reason = trim((string) $this->input->post('reason'));
		$qty    = (int) $this->input->post('quantity');

		if( !$item || $item->status !== 'active' ){
			$this->fail('El producto ya fue anulado');
		}
		if( $reason === '' || mb_strlen($reason, 'UTF-8') > 255 ){
			$this->fail('Indique el motivo de la anulación');
		}

		list($ok, $msg) = $this->kitchen_mdl->voidItem($item->id_order_item, $reason, $this->id_admin, $qty, $this->is_admin());
		$this->json(array('type' => $ok ? 'success' : 'error', 'message' => $msg));
	}

	/** Swap a sent line (or part of it) for another product in one step; the new product prints its own comanda. */
	public function replace_item(){

		$this->requirePost();

		$item = $this->kitchen_mdl->item($this->input->post('id_order_item'));
		if( !$item || $item->status !== 'active' ){
			$this->fail('El producto ya fue anulado');
		}

		$qty     = (int) $this->input->post('quantity');            // units to replace (0 = all)
		$new_qty = (int) $this->input->post('new_quantity');
		$new     = $this->kitchen_mdl->product($this->input->post('id_product'));
		$notes   = trim((string) $this->input->post('notes'));

		if( !$new ){
			$this->fail('Producto no disponible');
		}
		if( (int) $new->id_product === (int) $item->id_product ){
			$this->fail('Elija un producto distinto (para quitar unidades use Anular)');
		}
		if( $new_qty < 1 || $new_qty > 99 ){
			$this->fail('Cantidad inválida');
		}
		if( mb_strlen($notes, 'UTF-8') > 255 ){
			$this->fail('La nota es demasiado larga');
		}

		$reason = 'Cambio por '.mb_substr($new->name, 0, 200, 'UTF-8');
		list($ok, $msg, $id_order) = $this->kitchen_mdl->replaceItem($item->id_order_item, $qty, $new, $new_qty, $notes, $reason, $this->id_admin, $this->is_admin());

		if( !$ok ){
			$this->fail($msg);
		}

		$print = $this->printTicket($id_order);
		$this->json(array('type' => 'success', 'message' => $print['ok'] ? 'Producto cambiado y comanda enviada' : 'Producto cambiado, pero no se pudo imprimir la comanda', 'printed' => $print['ok'], 'id_order' => $id_order));
	}

	/** Printable HTML copy of a ticket (also the fallback when the thermal printer is unavailable). */
	public function ticket($id_order = 0){

		$order = $this->kitchen_mdl->order($id_order);
		if( !$order ){
			$this->notFound();
		}

		$this->content_data = array_merge($this->content_data, array(
			'order' => $order,
			'tab'   => $this->kitchen_mdl->tab($order->id_tab),
			'items' => $this->kitchen_mdl->orderItems($order->id_order),
		));

		$this->renderPanel('kitchen_ticket');
	}

	/* ---------- printing ---------- */

	private function ascii($s){

		return strtr((string) $s, array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
			'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
			'¿' => '?', '¡' => '!',
		));
	}

	/** Sends the ticket to the kitchen printer. Records the outcome on the order; never throws. */
	private function printTicket($id_order){

		$order = $this->kitchen_mdl->order($id_order);
		$tab   = $this->kitchen_mdl->tab($order->id_tab);
		$items = array_filter($this->kitchen_mdl->orderItems($id_order), function($i){ return $i->prints_kitchen && $i->status === 'active'; });

		if( !count($items) ){
			$this->kitchen_mdl->setPrint($id_order, 'skipped');
			return array('ok' => TRUE, 'error' => '');
		}

		$name = trim((string) $this->settings_mdl->get('kitchen_printer_name', 'COCINA'));
		if( $name === '' ){
			$this->kitchen_mdl->setPrint($id_order, 'failed', 'Impresora de cocina no configurada');
			return array('ok' => FALSE, 'error' => 'Impresora de cocina no configurada');
		}

		ob_start(); // the connector emits PHP warnings on failure; they must not leak into the JSON answer

		try{

			$printer = new Printer(new WindowsPrintConnector($name));
			$printer->initialize();

			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$printer->setTextSize(2, 2);
			$printer->text("COCINA\n");
			$printer->setTextSize(1, 1);
			$printer->text("Pedido #".$order->id_order."\n");
			$printer->feed(1);

			$printer->setJustification(Printer::JUSTIFY_LEFT);
			$printer->setEmphasis(TRUE);
			$printer->text("Mesa: ".$this->ascii($tab->table_label)." (".$this->ascii($tab->area_name).")\n");
			$printer->setEmphasis(FALSE);
			$printer->text("Mesero: ".$this->ascii($tab->waiter)."\n");
			$printer->text("Hora: ".date('d/m/Y h:i A', strtotime($order->created_at))."\n");
			$printer->text("------------------------------------------\n");

			$course = NULL;
			foreach( $items as $i ){

				if( $course !== (int) $i->course ){
					$course = (int) $i->course;
					$printer->setEmphasis(TRUE);
					$printer->text("-- Tiempo ".$course." --\n");
					$printer->setEmphasis(FALSE);
				}

				$printer->setTextSize(2, 2);
				$printer->text($i->quantity." x ".$this->ascii($i->product_name)."\n");
				$printer->setTextSize(1, 1);

				if( $i->notes ){
					$printer->text("   >> ".$this->ascii($i->notes)."\n");
				}
			}

			$printer->text("------------------------------------------\n");
			$printer->feed(3);
			$printer->cut();
			$printer->close();
			ob_end_clean();

			$this->kitchen_mdl->setPrint($id_order, 'printed');
			return array('ok' => TRUE, 'error' => '');
		}
		catch( \Throwable $e ){

			ob_end_clean();
			$this->kitchen_mdl->setPrint($id_order, 'failed', $e->getMessage());
			log_message('error', 'kitchen ticket print failed: '.$e->getMessage());
			return array('ok' => FALSE, 'error' => $e->getMessage());
		}
	}
}
