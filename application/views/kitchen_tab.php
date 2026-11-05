<div class="page-title" style="margin-bottom:6px;">
	<h3 style="display:inline-block;">Mesa <?php echo htmlspecialchars($tab->table_label); ?>
		<small><?php echo htmlspecialchars($tab->area_name); ?> · <?php echo htmlspecialchars((string) $tab->waiter); ?> · <?php echo (int) $tab->guests; ?> <i class="fa fa-user"></i></small></h3>
	<a href="<?php echo base_url('floor'); ?>" class="btn btn-default pull-right"><i class="fa fa-arrow-left"></i> Plano</a>
</div>
<div class="clearfix"></div>

<div class="row">
	<!-- menu browser -->
	<div class="col-md-7">
		<input type="search" id="m-search" class="form-control" placeholder="Buscar en el menú..." style="margin-bottom:8px;">
		<div class="area-tabs" id="m-cats"></div>
		<div id="m-grid" class="menu-grid"></div>
	</div>

	<!-- cart + consumption -->
	<div class="col-md-5">
		<div class="x_panel">
			<div class="x_title"><h2 style="font-size:16px;">Nuevo pedido</h2><div class="clearfix"></div></div>
			<div id="cart"><p class="text-muted" id="cart-empty">Toque un producto para agregarlo.</p></div>
			<button type="button" class="btn btn-success btn-block btn-lg" id="btn-send" disabled><i class="fa fa-cutlery"></i> Enviar a cocina</button>
		</div>

		<div class="x_panel">
			<div class="x_title"><h2 style="font-size:16px;">Consumo de la mesa</h2>
				<span class="pull-right" style="font-size:16px;"><strong id="tab-total">$<?php echo number_format($total, 2); ?></strong></span><div class="clearfix"></div></div>
			<?php if( empty($orders) ) : ?>
			<p class="text-muted">Aún no se ha enviado nada a cocina.</p>
			<?php endif; ?>
			<?php foreach( $orders as $o ) : ?>
			<div class="order-block">
				<div class="order-head">
					<strong>Pedido #<?php echo (int) $o->id_order; ?></strong>
					<span class="text-muted"><?php echo date('h:i A', strtotime($o->created_at)); ?></span>
					<?php if( $o->print_status === 'printed' ) : ?><span class="label label-success">Impreso</span>
					<?php elseif( $o->print_status === 'failed' ) : ?><span class="label label-danger" title="<?php echo htmlspecialchars((string) $o->print_error); ?>">No impreso</span>
					<?php elseif( $o->print_status === 'skipped' ) : ?><span class="label label-default">Sin cocina</span><?php endif; ?>
					<span class="pull-right">
						<a href="<?php echo base_url('kitchen/ticket/'.(int) $o->id_order); ?>" class="btn btn-xs btn-default" target="_blank"><i class="fa fa-file-text-o"></i></a>
						<button type="button" class="btn btn-xs btn-default js-reprint" data-id="<?php echo (int) $o->id_order; ?>"><i class="fa fa-print"></i> Reimprimir</button>
					</span>
				</div>
				<ul class="list-unstyled">
				<?php foreach( $o->items as $i ) : ?>
					<li class="<?php echo $i->status === 'void' ? 'item-void' : ''; ?>">
						<?php echo (int) $i->quantity; ?> × <?php echo htmlspecialchars($i->product_name); ?>
						<span class="text-muted">$<?php echo number_format($i->quantity * $i->unit_price, 2); ?></span>
						<?php if( $i->course > 1 ) : ?><span class="label label-info">T<?php echo (int) $i->course; ?></span><?php endif; ?>
						<?php if( $i->notes ) : ?><br><small class="text-muted"><i class="fa fa-comment-o"></i> <?php echo htmlspecialchars($i->notes); ?></small><?php endif; ?>
						<?php if( $i->status === 'void' ) : ?><br><small class="text-danger">Anulado: <?php echo htmlspecialchars((string) $i->void_reason); ?></small>
						<?php elseif( $is_admin ) : ?><button type="button" class="btn btn-xs btn-link text-danger js-void" data-id="<?php echo (int) $i->id_order_item; ?>">Anular</button><?php endif; ?>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
			<?php endforeach; ?>
			<?php if( !$is_admin && !empty($orders) ) : ?><p class="text-muted"><small>Para anular un producto enviado, pida a un administrador.</small></p><?php endif; ?>
		</div>
	</div>
</div>

<style>
	.menu-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(130px,1fr)); gap:8px; }
	.menu-item{ background:#fff; border:1px solid #d5dde5; border-radius:6px; padding:10px 8px; text-align:center; cursor:pointer; min-height:78px; user-select:none; }
	.menu-item:active{ background:#e8f7f3; }
	.menu-item.disabled{ opacity:.45; cursor:not-allowed; }
	.menu-item .mi-name{ font-weight:600; line-height:1.2; }
	.menu-item .mi-price{ color:#1ABB9C; font-weight:700; }
	.menu-item .mi-stock{ font-size:11px; color:#999; }
	.cart-line{ border-bottom:1px solid #eee; padding:8px 0; }
	.cart-line .qty{ display:inline-flex; align-items:center; gap:6px; }
	.cart-line .qty button{ width:34px; height:34px; }
	.order-block{ border-top:1px solid #eee; padding:8px 0; }
	.item-void{ text-decoration:line-through; opacity:.6; }
	.item-void small{ text-decoration:none; display:inline-block; }
</style>

<script>
(function(){

	var TK = <?php echo json_encode($tk); ?>;
	var ID_TAB = <?php echo (int) $tab->id_tab; ?>;
	var MENU = <?php echo json_encode($menu); ?>;
	var CATS = <?php echo json_encode($categories); ?>;
	var BASE = '<?php echo base_url('kitchen'); ?>/';
	var cart = [];       // {id, name, price, qty, notes, course, available}
	var catFilter = -1;  // -1 all, 0 none, else id

	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 3000 }); }
	function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }
	function money(n){ return '$' + (Math.round(n * 100) / 100).toFixed(2); }

	/* ---- menu ---- */
	function renderCats(){
		var $c = $('#m-cats').empty();
		function chip(id, name){ $c.append($('<button type="button" class="btn btn-default"></button>').text(name).attr('data-cat', id).toggleClass('active', catFilter === id)); }
		chip(-1, 'Todo');
		$.each(CATS, function(_, c){ chip(parseInt(c.id, 10), c.name); });
		chip(0, 'Otros');
	}

	function renderMenu(){
		var q = $.trim($('#m-search').val()).toLowerCase(), $g = $('#m-grid').empty(), n = 0;
		$.each(MENU, function(_, p){
			if( catFilter !== -1 && parseInt(p.cat, 10) !== catFilter ){ return; }
			if( q && p.name.toLowerCase().indexOf(q) === -1 ){ return; }
			var out = p.stocked && p.available <= 0;
			$g.append($('<div class="menu-item"></div>').toggleClass('disabled', out).attr('data-id', p.id)
				.html('<div class="mi-name">' + esc(p.name) + '</div><div class="mi-price">' + money(p.price) + '</div>' +
					(p.stocked ? '<div class="mi-stock">' + (out ? 'Agotado' : p.available + ' disp.') + '</div>' : '')));
			n++;
		});
		if( !n ){ $g.html('<p class="text-muted">Sin resultados.</p>'); }
	}

	$('#m-cats').on('click', 'button', function(){ catFilter = parseInt($(this).attr('data-cat'), 10); renderCats(); renderMenu(); });
	$('#m-search').on('input', renderMenu);

	$('#m-grid').on('click', '.menu-item', function(){
		var p = MENU.filter(function(x){ return x.id === parseInt($(this).attr('data-id'), 10); }.bind(this))[0];
		if( !p || (p.stocked && p.available <= 0) ){ return; }
		var line = cart.filter(function(l){ return l.id === p.id && !l.notes; })[0];
		var inCart = cart.filter(function(l){ return l.id === p.id; }).reduce(function(a, l){ return a + l.qty; }, 0);
		if( p.stocked && inCart + 1 > p.available ){ notify('error', 'No hay más stock de ' + p.name); return; }
		if( line ){ line.qty++; } else { cart.push({ id: p.id, name: p.name, price: p.price, qty: 1, notes: '', course: 1, stocked: p.stocked, available: p.available }); }
		renderCart();
	});

	/* ---- cart ---- */
	function renderCart(){
		var $c = $('#cart').empty();
		if( !cart.length ){ $c.append('<p class="text-muted">Toque un producto para agregarlo.</p>'); }
		$.each(cart, function(i, l){
			$c.append(
				'<div class="cart-line" data-i="' + i + '">' +
				'<div><strong>' + esc(l.name) + '</strong> <span class="pull-right">' + money(l.price * l.qty) + '</span></div>' +
				'<div class="qty"><button type="button" class="btn btn-default js-minus">−</button><span style="min-width:24px;text-align:center;">' + l.qty +
				'</span><button type="button" class="btn btn-default js-plus">+</button>' +
				'<select class="form-control js-course" style="width:auto;display:inline-block;margin-left:8px;"><option value="1">Tiempo 1</option><option value="2">Tiempo 2</option><option value="3">Tiempo 3</option></select>' +
				'<button type="button" class="btn btn-link text-danger js-del"><i class="fa fa-trash"></i></button></div>' +
				'<input type="text" class="form-control js-notes" placeholder="Nota (sin cebolla, término medio...)" maxlength="255" style="margin-top:6px;" value="' + esc(l.notes) + '">' +
				'</div>');
			$c.find('.cart-line[data-i="' + i + '"] .js-course').val(l.course);
		});
		$('#btn-send').prop('disabled', !cart.length);
	}

	function idx(el){ return parseInt($(el).closest('.cart-line').attr('data-i'), 10); }

	$('#cart').on('click', '.js-plus', function(){
		var l = cart[idx(this)];
		var total = cart.filter(function(x){ return x.id === l.id; }).reduce(function(a, x){ return a + x.qty; }, 0);
		if( l.stocked && total + 1 > l.available ){ notify('error', 'No hay más stock de ' + l.name); return; }
		if( l.qty >= 99 ){ return; }
		l.qty++; renderCart();
	});
	$('#cart').on('click', '.js-minus', function(){ var l = cart[idx(this)]; if( l.qty > 1 ){ l.qty--; renderCart(); } });
	$('#cart').on('click', '.js-del', function(){ cart.splice(idx(this), 1); renderCart(); });
	$('#cart').on('input', '.js-notes', function(){ cart[idx(this)].notes = $(this).val(); });
	$('#cart').on('change', '.js-course', function(){ cart[idx(this)].course = parseInt($(this).val(), 10); });

	$('#btn-send').on('click', function(){
		var $b = $(this).prop('disabled', true);
		var items = cart.map(function(l){ return { id_product: l.id, quantity: l.qty, notes: l.notes, course: l.course }; });
		$.post(BASE + 'send', { tk: TK, id_tab: ID_TAB, items: JSON.stringify(items) }, function(r){
			if( r.type === 'success' ){
				notify(r.printed ? 'success' : 'notice', r.message);
				setTimeout(function(){ location.reload(); }, 1200);
			} else {
				notify('error', r.message); $b.prop('disabled', false);
			}
		}, 'json').fail(function(){ notify('error', 'Respuesta inesperada del servidor; se recargará para evitar duplicados'); setTimeout(function(){ location.reload(); }, 1800); });
	});

	/* ---- consumption actions ---- */
	$('.js-reprint').on('click', function(){
		$.post(BASE + 'reprint', { tk: TK, id_order: $(this).data('id') }, function(r){ notify(r.type === 'success' ? 'success' : 'error', r.message); }, 'json');
	});

	$('.js-void').on('click', function(){
		var id = $(this).data('id');
		bootbox.prompt('Motivo de la anulación', function(reason){
			if( reason === null ){ return; }
			$.post(BASE + 'void_item', { tk: TK, id_order_item: id, reason: reason }, function(r){
				notify(r.type === 'success' ? 'success' : 'error', r.message);
				if( r.type === 'success' ){ setTimeout(function(){ location.reload(); }, 800); }
			}, 'json');
		});
	});

	renderCats(); renderMenu(); renderCart();
})();
</script>
