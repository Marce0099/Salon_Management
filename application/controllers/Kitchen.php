<?php if( !defined('BASEPATH') ){ exit(); }

require_once FCPATH.'print/autoload.php';
use Mike42\Escpos\Printer;

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
		$stock = array();
		foreach( $this->kitchen_mdl->menu() as $m ){
			if( $m->stocked ){
				$avail[$m->id] = (int) $m->available;
				$stock[$m->id] = array('base' => $m->base, 'rec' => $m->rec);
			}
		}

		$this->audit->denied('order.send', 'order', NULL, array('tab' => (int) $this->input->post('id_tab') ?: NULL, 'summary' => 'Envío a cocina rechazado: '.$message));

		$this->json(array('type' => 'error', 'message' => $message, 'availability' => $avail, 'stock' => $stock));
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
		$has_mods = array(); // products with at least one modified line: their recipe check is done on the net need below
		foreach( $raw as $r0 ){
			if( isset($r0['id_product'], $r0['modifiers']) && is_array($r0['modifiers']) && count($r0['modifiers']) ){ $has_mods[(int) $r0['id_product']] = TRUE; }
		}
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

			// modifiers picked for this line: must belong to the dish; their ingredient removals are clamped to the recipe
			$this->load->model('modifiers_mdl');
			$this->load->model('ingredients_mdl');
			$rec = $this->ingredients_mdl->recipesFor(array((int) $p->id_product));
			list($mod_err, $mods) = $this->modifiers_mdl->resolve($p->id_product, isset($r['modifiers']) ? $r['modifiers'] : array(), isset($rec[(int) $p->id_product]) ? $rec[(int) $p->id_product] : NULL);
			if( $mod_err ){
				$this->fail($mod_err);
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
			if( $portions !== NULL && empty($has_mods[(int) $p->id_product]) ){
				$asked_recipe[(int) $p->id_product] = (isset($asked_recipe[(int) $p->id_product]) ? $asked_recipe[(int) $p->id_product] : 0) + $qty;
				if( $asked_recipe[(int) $p->id_product] > $portions ){
					$this->failStock($portions > 0 ? 'Solo quedan '.$portions.' porciones de '.$p->name.' (ingredientes insuficientes)' : $p->name.' está agotado (faltan ingredientes)');
				}
			}

			$lines[] = array(
				'id_product' => (int) $p->id_product, 'name' => $p->name, 'quantity' => $qty, 'price' => (float) $p->price_sell_cor,
				'notes' => $notes, 'course' => $course, 'prints_kitchen' => (int) $p->prints_kitchen, 'modifiers' => $mods,
			);
		}

		// products with modified lines: all their lines together must fit the ingredient stock once each line's removals are taken off
		foreach( array_keys($has_mods) as $pid ){

			$rec = $this->ingredients_mdl->recipesFor(array($pid));
			if( empty($rec[$pid]) ){ continue; }

			$need = array();
			$pname = '';
			foreach( $lines as $l ){
				if( (int) $l['id_product'] !== (int) $pid ){ continue; }
				$pname = $l['name'];
				$removal = Modifiers_mdl::removalMap($l['modifiers']);
				foreach( $rec[$pid] as $rr ){
					$per = max(0, round((float) $rr->quantity - (isset($removal[(int) $rr->id_ingredient]) ? $removal[(int) $rr->id_ingredient] : 0), 3));
					$need[(int) $rr->id_ingredient] = (isset($need[(int) $rr->id_ingredient]) ? $need[(int) $rr->id_ingredient] : 0) + round($per * (int) $l['quantity'], 3);
				}
			}

			foreach( $rec[$pid] as $rr ){
				if( isset($need[(int) $rr->id_ingredient]) && round($need[(int) $rr->id_ingredient], 3) > round((float) $rr->stock, 3) ){
					$this->failStock('Ingredientes insuficientes para '.$pname.' (falta '.$rr->name.')');
				}
			}
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

		if( $print['state'] === 'failed' ){
			$message = 'Pedido guardado, pero no se pudo imprimir la comanda'.($print['stations'] ? '. '.$this->printing->waitingMessage($print['stations']) : '');
		}
		elseif( $print['state'] === 'waiting' ){
			$message = 'Pedido guardado. '.$this->printing->waitingMessage($print['stations']);
		}
		else{
			$message = 'Pedido enviado a cocina';
		}

		$this->json(array(
			'type'     => 'success',
			'message'  => $message,
			'printed'  => $this->isOut($print),
			'id_order' => $id_order,
		));
	}

	public function reprint(){

		$this->requirePost();

		$ord = $this->kitchen_mdl->order($this->input->post('id_order'));
		if( !$ord ){
			$this->fail('Pedido no encontrado');
		}

		$print = $this->printTicket((int) $this->input->post('id_order'));
		$now = $this->kitchen_mdl->order($ord->id_order);
		$this->audit->log('order.reprint', 'order', $ord->id_order, array('tab' => $ord->id_tab, 'best_effort' => TRUE,
			'summary' => 'Comanda reimpresa #'.$ord->id_order.($print['state'] === 'failed' ? ' (falló)' : ($print['state'] === 'waiting' ? ' (en espera)' : '')),
			'before' => array('print_status' => $ord->print_status, 'printed_at' => $ord->printed_at, 'print_error' => $ord->print_error),
			'after' => array('print_status' => $now ? $now->print_status : NULL, 'print_error' => $now ? $now->print_error : NULL)));

		if( $print['state'] === 'failed' ){
			$this->fail('No se pudo imprimir: '.$print['error']);
		}
		if( $print['state'] === 'waiting' ){
			$this->json(array('type' => 'notice', 'message' => $this->printing->waitingMessage($print['stations'])));
		}

		$this->json(array('type' => 'success', 'message' => $print['state'] === 'skipped' ? 'El pedido ya no tiene platillos para cocina' : 'Comanda reimpresa'));
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

		list($ok, $msg, $old, $voided) = $this->kitchen_mdl->voidItem($item->id_order_item, $reason, $this->id_admin, $qty, $this->is_admin());
		if( !$ok ){
			$this->fail($msg);
		}

		// the kitchen has the comanda on paper: tell it to stop preparing those units
		$slip = $this->printVoidTicket($old, $voided, $reason);

		if( $slip['state'] === 'failed' ){
			$msg .= ', pero no se pudo imprimir el aviso: avise a cocina';
		}
		elseif( $slip['state'] === 'waiting' ){
			$msg .= '. Aviso a cocina: '.$this->printing->waitingMessage($slip['stations']);
		}
		elseif( $slip['state'] !== 'skipped' ){
			$msg .= ' y aviso enviado a cocina';
		}

		$this->json(array('type' => 'success', 'message' => $msg, 'printed' => $this->isOut($slip)));
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

		// modifiers of the old line that also apply to the new dish are kept ("sin queso" survives a swap between two burgers)
		$this->load->model('modifiers_mdl');
		$this->load->model('ingredients_mdl');
		$keep = $this->modifiers_mdl->compatibleIds($new->id_product, $this->modifiers_mdl->itemModifierIds($item->id_order_item));
		$rec = $this->ingredients_mdl->recipesFor(array((int) $new->id_product));
		list($mod_err, $mods) = $this->modifiers_mdl->resolve($new->id_product, $keep, isset($rec[(int) $new->id_product]) ? $rec[(int) $new->id_product] : NULL);
		if( $mod_err ){ $this->fail($mod_err); }

		$reason = 'Cambio por '.mb_substr($new->name, 0, 200, 'UTF-8');
		list($ok, $msg, $id_order, $old, $voided) = $this->kitchen_mdl->replaceItem($item->id_order_item, $qty, $new, $new_qty, $notes, $reason, $this->id_admin, $this->is_admin(), $mods);

		if( !$ok ){
			$this->fail($msg);
		}

		// cancellation of the old dish first, then the comanda of the new one
		$slip  = $this->printVoidTicket($old, $voided, $reason);
		$print = $this->printTicket($id_order);

		$states   = array($slip['state'], $print['state']);
		$stations = array_merge($slip['stations'], $print['stations']);

		if( in_array('failed', $states, TRUE) ){
			$message = 'Producto cambiado, pero no se pudo imprimir '.($slip['state'] !== 'failed' ? 'la comanda' : ($print['state'] !== 'failed' ? 'el aviso de anulación' : 'en cocina')).': avise a cocina';
			if( $stations ){ $message .= '. '.$this->printing->waitingMessage($stations); }
		}
		elseif( in_array('waiting', $states, TRUE) ){
			$message = 'Producto cambiado. '.$this->printing->waitingMessage($stations);
		}
		elseif( $print['state'] !== 'skipped' ){
			$message = 'Producto cambiado y comanda enviada';
		}
		else{
			$message = $slip['state'] !== 'skipped' ? 'Producto cambiado y aviso enviado a cocina' : 'Producto cambiado';
		}

		$this->json(array('type' => 'success', 'message' => $message, 'printed' => $this->isOut($slip) && $this->isOut($print), 'id_order' => $id_order));
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

	/** TRUE unless the ticket failed or is waiting for an offline station. */
	private function isOut($print){

		return $print['state'] !== 'failed' && $print['state'] !== 'waiting';
	}

	/**
	 * Renders the comanda(s) and sends them through the print queue. The order is split into ONE ticket per kitchen printer
	 * (menu category -> printer, default kitchen printer for the rest). Older unprinted comandas of the order are cancelled so
	 * a reprint never comes out twice. Records the outcome on the order; never throws.
	 * Returns array(state skipped|printed|sent|waiting|failed, error, stations that are offline).
	 */
	private function printTicket($id_order){

		$order = $this->kitchen_mdl->order($id_order);
		$tab   = $this->kitchen_mdl->tab($order->id_tab);
		$items = array_values(array_filter($this->kitchen_mdl->orderItems($id_order), function($i){ return $i->prints_kitchen && $i->status === 'active'; }));

		if( !count($items) ){
			$this->load->library('printing');
			$this->printing->cancelOrderComandas($id_order);
			$this->kitchen_mdl->setPrint($id_order, 'skipped');
			return array('state' => 'skipped', 'error' => '', 'stations' => array());
		}

		$this->load->library('printing');

		$route = $this->printing->routeProducts(array_map(function($i){ return (int) $i->id_product; }, $items));

		$groups = array();
		foreach( $items as $i ){

			$pr = isset($route[(int) $i->id_product]) ? $route[(int) $i->id_product] : NULL;
			$k  = $pr ? (int) $pr->id_printer : 0;

			if( !isset($groups[$k]) ){ $groups[$k] = array('printer' => $pr, 'items' => array()); }
			$groups[$k]['items'][] = $i;
		}

		$errors = array();
		$stations = array();
		$state = 'printed';

		// render everything first: the older jobs are only cancelled when there is something to replace them
		foreach( $groups as $k => $g ){

			try{
				$groups[$k]['bytes'] = $this->renderTicket($order, $tab, $g['items'], $g['printer']);
			}
			catch( \Throwable $e ){

				$errors[] = ($g['printer'] ? $g['printer']->name : 'sin impresora').': '.$e->getMessage();
				log_message('error', 'kitchen ticket render failed: '.$e->getMessage());
			}
		}

		if( $errors ){
			$this->kitchen_mdl->setPrint($id_order, 'failed', implode(' | ', $errors));
			return array('state' => 'failed', 'error' => implode(' | ', $errors), 'stations' => array());
		}

		$this->printing->cancelOrderComandas($id_order);

		foreach( $groups as $g ){

			// the library records printed/failed on the order itself (and keeps the bytes for retry/reprint)
			$res = $this->printing->send('kitchen', 'comanda', 'order', $order->id_order,
				'Comanda #'.$order->id_order.' - Mesa '.$tab->table_label.($g['printer'] ? ' - '.$g['printer']->name : ''), $g['bytes'], $this->id_admin, $g['printer']);

			if( $res['state'] === 'failed' ){
				$state = 'failed';
				$errors[] = ($g['printer'] ? $g['printer']->name : 'sin impresora').': '.$res['error'];
			}
			elseif( $res['state'] === 'waiting' ){
				$stations[] = $res['station'];
				if( $state !== 'failed' ){ $state = 'waiting'; }
			}
			elseif( $res['state'] === 'sent' && $state === 'printed' ){
				$state = 'sent';
			}
		}

		return array('state' => $state, 'error' => implode(' | ', $errors), 'stations' => $stations);
	}

	/**
	 * Cancellation slip for the kitchen: $qty units of a sent line ($item = the line before the void) must not be prepared.
	 * Printed on the line's current kitchen printer. Nothing is printed for lines that never go to the kitchen, closed tables
	 * or already collected bills (late corrections). Runs after the void is committed; never throws.
	 * Returns the same shape as printTicket().
	 */
	private function printVoidTicket($item, $qty, $reason){

		if( !$item || !$item->prints_kitchen || $item->tab_status !== 'open' || $item->bill_status === 'paid' ){
			return array('state' => 'skipped', 'error' => '', 'stations' => array());
		}

		$this->load->library('printing');

		try{

			$this->load->model('modifiers_mdl');
			$lines = $this->modifiers_mdl->attachTexts(array($item));
			$line  = $lines[0];
			$tab   = $this->kitchen_mdl->tab($item->id_tab);
			$route = $this->printing->routeProducts(array((int) $item->id_product));
			$station = isset($route[(int) $item->id_product]) ? $route[(int) $item->id_product] : NULL;

			list($printer, $conn) = $this->printing->canvas();

			$this->ticketHeader($printer, $station, $item->id_order);
			$printer->setJustification(Printer::JUSTIFY_CENTER);
			$printer->setEmphasis(TRUE);
			$printer->setTextSize(2, 2);
			$printer->text("** ANULADO **\n");
			$printer->setTextSize(1, 1);
			$printer->setEmphasis(FALSE);
			$printer->text("NO PREPARAR\n");
			$this->ticketTable($printer, $tab, time());

			$this->ticketLine($printer, $qty, $line);

			$printer->text("------------------------------------------\n");
			$printer->text(wordwrap('Motivo: '.$this->ascii($reason), 42, "\n", TRUE)."\n");
			$printer->feed(3);
			$printer->cut();

			// ref_type `void` keeps the slip out of the order's print status, which describes its comandas
			$res = $this->printing->send('kitchen', 'comanda', 'void', $item->id_order_item,
				'Anulación pedido #'.$item->id_order.' - Mesa '.$tab->table_label.($station ? ' - '.$station->name : ''),
				$this->printing->bytes($printer, $conn), $this->id_admin, $station);
		}
		catch( \Throwable $e ){

			log_message('error', 'void ticket failed for order item #'.(int) $item->id_order_item.': '.$e->getMessage());
			return array('state' => 'failed', 'error' => $e->getMessage(), 'stations' => array());
		}

		return array('state' => $res['state'], 'error' => $res['error'], 'stations' => $res['state'] === 'waiting' ? array($res['station']) : array());
	}

	/** ESC/POS bytes of one comanda for one station (kitchen printer row, or NULL when none is configured). */
	private function renderTicket($order, $tab, $items, $station){

		list($printer, $conn) = $this->printing->canvas();

		$this->ticketHeader($printer, $station, $order->id_order);
		$this->ticketTable($printer, $tab, strtotime($order->created_at));

		$course = NULL;
		foreach( $items as $i ){

			if( $course !== (int) $i->course ){
				$course = (int) $i->course;
				$printer->setEmphasis(TRUE);
				$printer->text("-- Tiempo ".$course." --\n");
				$printer->setEmphasis(FALSE);
			}

			$this->ticketLine($printer, $i->quantity, $i);
		}

		$printer->text("------------------------------------------\n");
		$printer->feed(3);
		$printer->cut();

		return $this->printing->bytes($printer, $conn);
	}

	/** Station name and order number, centered. */
	private function ticketHeader($printer, $station, $id_order){

		$printer->setJustification(Printer::JUSTIFY_CENTER);
		$printer->setTextSize(2, 2);
		$printer->text(($station ? strtoupper(mb_substr($this->ascii($station->name), 0, 20, 'UTF-8')) : 'COCINA')."\n");
		$printer->setTextSize(1, 1);
		$printer->text("Pedido #".(int) $id_order."\n");
		$printer->feed(1);
	}

	/** Table, waiter and time block. */
	private function ticketTable($printer, $tab, $time){

		$printer->setJustification(Printer::JUSTIFY_LEFT);
		$printer->setEmphasis(TRUE);
		$printer->text("Mesa: ".$this->ascii($tab->table_label)." (".$this->ascii($tab->area_name).")\n");
		$printer->setEmphasis(FALSE);
		$printer->text("Mesero: ".$this->ascii($tab->waiter)."\n");
		$printer->text("Hora: ".date('d/m/Y h:i A', $time)."\n");
		$printer->text("------------------------------------------\n");
	}

	/** One dish: quantity and name in double size, then its modifiers and note. */
	private function ticketLine($printer, $qty, $line){

		$printer->setTextSize(2, 2);
		$printer->text((int) $qty." x ".$this->ascii($line->product_name)."\n");
		$printer->setTextSize(1, 1);

		foreach( $line->mods as $mod_text ){
			// bold, wrapped at 36 chars; continuation lines stay indented so they never look detached from their dish
			$printer->setEmphasis(TRUE);
			foreach( explode("\n", wordwrap($this->ascii($mod_text), 36, "\n", TRUE)) as $n => $part ){
				$printer->text(($n === 0 ? "   ** " : "      ").$part."\n");
			}
			$printer->setEmphasis(FALSE);
		}

		if( $line->notes ){
			$printer->text("   >> ".$this->ascii($line->notes)."\n");
		}
	}
}
