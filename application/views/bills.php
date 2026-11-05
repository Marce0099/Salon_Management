<?php
function bills_money($n){ global $rate; return 'C$'.number_format((float) $n * (float) $rate, 2); }
$rate = isset($rate) ? $rate : 1;
$open_for_move = array();
foreach( $bills as $b ){ if( $b->status === 'open' ){ $open_for_move[] = $b; } }
?>
<div class="page-title" style="margin-bottom:6px;">
	<h3 style="display:inline-block;">Cuentas · Mesa <?php echo htmlspecialchars($tab->table_label); ?>
		<small><?php echo htmlspecialchars($tab->area_name); ?> · <?php echo htmlspecialchars((string) $tab->waiter); ?></small></h3>
	<span class="pull-right">
		<a href="<?php echo base_url('kitchen/tab/'.(int) $tab->id_tab); ?>" class="btn btn-default"><i class="fa fa-cutlery"></i> Pedido</a>
		<a href="<?php echo base_url('floor'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Plano</a>
		<button type="button" class="btn btn-success" id="b-new"><i class="fa fa-plus"></i> Nueva cuenta</button>
	</span>
</div>
<div class="clearfix"></div>
<p class="text-muted" style="margin:0 0 10px;">
	Propina <?php echo rtrim(rtrim(number_format($rates['tip'], 2), '0'), '.'); ?>% · IVA <?php echo rtrim(rtrim(number_format($rates['iva'], 2), '0'), '.'); ?>% (se suman al subtotal)
</p>

<div class="row">
<?php foreach( $bills as $b ) : $isOpen = $b->status === 'open'; ?>
	<div class="col-md-4 col-sm-6">
		<div class="x_panel bill-card" data-id="<?php echo (int) $b->id_bill; ?>">
			<div class="x_title">
				<h2 style="font-size:16px;"><?php echo htmlspecialchars($b->label); ?>
					<?php if( $b->status === 'split' ) : ?><span class="label label-info">Dividida</span>
					<?php elseif( $b->status === 'paid' ) : ?><span class="label label-success">Pagada</span><?php endif; ?></h2>
				<div class="clearfix"></div>
			</div>

			<?php if( empty($b->items) ) : ?><p class="text-muted">Sin productos.</p><?php endif; ?>
			<ul class="list-unstyled">
			<?php foreach( $b->items as $i ) : ?>
				<li style="padding:5px 0; border-bottom:1px solid #f0f0f0;">
					<?php echo (int) $i->quantity; ?> × <?php echo htmlspecialchars($i->product_name); ?>
					<span class="pull-right"><?php echo bills_money($i->quantity * $i->unit_price); ?></span>
					<?php if( $isOpen ) : ?>
					<br><button type="button" class="btn btn-xs btn-default js-move" data-id="<?php echo (int) $i->id_order_item; ?>"
						data-qty="<?php echo (int) $i->quantity; ?>" data-name="<?php echo htmlspecialchars($i->product_name); ?>" data-bill="<?php echo (int) $b->id_bill; ?>"><i class="fa fa-exchange"></i> Mover</button>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>

			<table class="table table-condensed" style="margin-bottom:6px;">
				<tr><td>Subtotal</td><td class="text-right"><?php echo bills_money($b->totals['subtotal']); ?></td></tr>
				<?php if( $rates['tip'] > 0 ) : ?><tr><td>Propina</td><td class="text-right"><?php echo bills_money($b->totals['tip']); ?></td></tr><?php endif; ?>
				<?php if( $rates['iva'] > 0 ) : ?><tr><td>IVA</td><td class="text-right"><?php echo bills_money($b->totals['iva']); ?></td></tr><?php endif; ?>
				<tr><th>Total</th><th class="text-right"><?php echo bills_money($b->totals['total']); ?></th></tr>
			</table>

			<?php if( $isOpen ) : ?>
			<div>
				<button type="button" class="btn btn-sm btn-default js-rename" data-id="<?php echo (int) $b->id_bill; ?>" data-label="<?php echo htmlspecialchars($b->label); ?>"><i class="fa fa-pencil"></i></button>
				<button type="button" class="btn btn-sm btn-default js-split" data-id="<?php echo (int) $b->id_bill; ?>"><i class="fa fa-pie-chart"></i> Dividir</button>
				<?php if( count($open_for_move) > 1 ) : ?><button type="button" class="btn btn-sm btn-default js-merge" data-id="<?php echo (int) $b->id_bill; ?>"><i class="fa fa-compress"></i> Unir</button><?php endif; ?>
				<?php if( $b->totals['subtotal'] > 0 && $this->controller->is_admin() ) : ?><a href="<?php echo base_url('sales/register?bill='.(int) $b->id_bill); ?>" class="btn btn-sm btn-primary"><i class="fa fa-money"></i> Cobrar</a><?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if( $b->status === 'split' ) : ?>
			<hr style="margin:8px 0;">
			<?php foreach( isset($kids[(int) $b->id_bill]) ? $kids[(int) $b->id_bill] : array() as $k ) : ?>
				<div style="padding:4px 0;"><?php echo htmlspecialchars($k->label); ?> <strong class="pull-right"><?php echo bills_money($k->totals['total']); ?></strong>
					<?php if( $k->status === 'paid' ) : ?><span class="label label-success">Pagada</span>
					<?php elseif( $k->status === 'open' && $this->controller->is_admin() ) : ?><a href="<?php echo base_url('sales/register?bill='.(int) $k->id_bill); ?>" class="btn btn-xs btn-primary" style="margin-left:6px;"><i class="fa fa-money"></i> Cobrar</a><?php endif; ?></div>
			<?php endforeach; ?>
			<button type="button" class="btn btn-sm btn-warning js-undo" data-id="<?php echo (int) $b->id_bill; ?>" style="margin-top:6px;">Deshacer división</button>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; ?>
</div>

<!-- move modal -->
<div class="modal fade" id="move-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Mover <span id="mv-name"></span></h4></div>
	<div class="modal-body">
		<div class="form-group"><label for="mv-target">A la cuenta</label><select id="mv-target" class="form-control"></select></div>
		<div class="form-group"><label for="mv-qty">Cantidad (máx. <span id="mv-max"></span>)</label><input type="number" id="mv-qty" class="form-control" min="1"></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button><button type="button" class="btn btn-success" id="mv-ok">Mover</button></div>
</div></div></div>

<!-- split modal -->
<div class="modal fade" id="split-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Dividir en partes iguales</h4></div>
	<div class="modal-body"><div class="form-group"><label for="sp-parts">Número de partes</label><input type="number" id="sp-parts" class="form-control" min="2" max="20" value="2"></div>
		<p class="text-muted">Cada parte se cobra como una cuenta aparte. Las cuentas divididas quedan bloqueadas hasta deshacer la división.</p></div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button><button type="button" class="btn btn-success" id="sp-ok">Dividir</button></div>
</div></div></div>

<!-- merge modal -->
<div class="modal fade" id="merge-modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Unir cuentas</h4></div>
	<div class="modal-body"><div class="form-group"><label for="mg-into">Pasar todos los productos a:</label><select id="mg-into" class="form-control"></select></div></div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button><button type="button" class="btn btn-success" id="mg-ok">Unir</button></div>
</div></div></div>

<script>
(function(){
	var TK = <?php echo json_encode($tk); ?>;
	var ID_TAB = <?php echo (int) $tab->id_tab; ?>;
	var BASE = '<?php echo base_url('bills'); ?>/';
	var OPEN = <?php echo json_encode(array_map(function($b){ return array('id' => (int) $b->id_bill, 'label' => $b->label); }, $open_for_move)); ?>;
	var cur = {};

	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 2500 }); }

	function post(url, data){
		data.tk = TK;
		$.post(BASE + url, data, function(r){
			notify(r.type === 'success' ? 'success' : 'error', r.message);
			if( r.type === 'success' ){ setTimeout(function(){ location.reload(); }, 600); }
		}, 'json').fail(function(){ notify('error', 'Respuesta inesperada del servidor; se recargará'); setTimeout(function(){ location.reload(); }, 1500); });
	}

	function fill($sel, exceptId, withNew){
		$sel.empty();
		if( withNew ){ $sel.append('<option value="0">+ Nueva cuenta</option>'); }
		$.each(OPEN, function(_, b){ if( b.id !== exceptId ){ $sel.append($('<option></option>').val(b.id).text(b.label)); } });
	}

	$('#b-new').on('click', function(){
		bootbox.prompt({ title: 'Nombre de la cuenta (opcional)', callback: function(name){ if( name !== null ){ post('create', { id_tab: ID_TAB, label: name }); } } });
	});

	$('.js-rename').on('click', function(){
		var id = $(this).data('id');
		bootbox.prompt({ title: 'Nuevo nombre', value: $(this).data('label'), callback: function(name){ if( name ){ post('rename', { id_bill: id, label: name }); } } });
	});

	$('.js-move').on('click', function(){
		cur = { item: $(this).data('id'), bill: $(this).data('bill'), max: $(this).data('qty') };
		$('#mv-name').text($(this).data('name')); $('#mv-max').text(cur.max);
		$('#mv-qty').attr('max', cur.max).val(cur.max);
		fill($('#mv-target'), cur.bill, true);
		$('#move-modal').modal('show');
	});
	$('#mv-ok').on('click', function(){ post('move_item', { id_order_item: cur.item, id_target: $('#mv-target').val(), quantity: $('#mv-qty').val() }); });

	$('.js-split').on('click', function(){ cur = { bill: $(this).data('id') }; $('#split-modal').modal('show'); });
	$('#sp-ok').on('click', function(){ post('split_equal', { id_bill: cur.bill, parts: $('#sp-parts').val() }); });

	$('.js-merge').on('click', function(){ cur = { bill: $(this).data('id') }; fill($('#mg-into'), cur.bill, false); $('#merge-modal').modal('show'); });
	$('#mg-ok').on('click', function(){ post('merge', { id_from: cur.bill, id_into: $('#mg-into').val() }); });

	$('.js-undo').on('click', function(){
		var id = $(this).data('id');
		bootbox.confirm('¿Deshacer la división de esta cuenta?', function(ok){ if( ok ){ post('undo_split', { id_bill: id }); } });
	});
})();
</script>
