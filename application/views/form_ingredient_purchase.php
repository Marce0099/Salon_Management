<h4 class="section-title">Compra de ingredientes <a href="<?php echo base_url('ingredients/purchases'); ?>" class="btn btn-default btn-sm pull-right"><i class="fa fa-history"></i> Compras</a></h4>

<style>
	.ip-card { border:1px solid #ddd; border-radius:6px; padding:10px; margin-bottom:10px; background:#fff; position:relative; }
	.ip-card .ip-name { font-weight:bold; padding-right:30px; }
	.ip-card .ip-stock { font-size:12px; color:#888; }
	.ip-card .ip-remove { position:absolute; top:6px; right:10px; font-size:22px; color:#c9302c; cursor:pointer; line-height:1; }
	.ip-card .form-control { height:42px; font-size:16px; }
	.ip-card label { font-size:12px; margin-bottom:2px; color:#666; }
	.ip-card .ip-info { font-size:12px; color:#555; margin-top:4px; min-height:16px; }
	.ip-card.flash { box-shadow:0 0 0 2px #26b99a; }
	#ip-results .list-group-item { padding:12px; cursor:pointer; }
	#ip-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #ddd; padding:10px 0; margin-top:10px; z-index:5; }
	#ip-total { font-size:18px; font-weight:bold; }
	#ip-form .form-control, #ct-form .form-control { width:100%; display:block; }
	@media (max-width:991px){ #ip-bar, #ct-bar { bottom:calc(60px + env(safe-area-inset-bottom)); } }
</style>

<form id="ip-form" autocomplete="off" onsubmit="return false;">
	<input type="hidden" id="ip-tk" value="<?php echo $tk; ?>">
	<div class="row">
		<div class="col-xs-12 col-sm-4 form-group"><label>Proveedor</label><?php $this->load->view('_provider_picker', array('pp_hidden_id' => 'ip-store', 'pp_default_first' => FALSE, 'pp_create_url' => base_url('ingredients/quick_provider'), 'pp_tk_selector' => '#ip-tk', 'providers' => $providers)); ?></div>
		<div class="col-xs-12 col-sm-3 form-group"><label>Fecha</label><input type="date" id="ip-date" class="form-control" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"></div>
		<div class="col-xs-12 col-sm-5 form-group"><label>Nota (opcional)</label><input type="text" id="ip-note" class="form-control" maxlength="255"></div>
	</div>

	<div class="form-group">
		<input type="search" id="ip-search" class="form-control input-lg" placeholder="Buscar ingrediente para agregar..." style="height:48px;">
		<div id="ip-results" class="list-group" style="max-height:280px;overflow-y:auto;margin-top:4px;"></div>
	</div>

	<div id="ip-lines"></div>
	<p id="ip-empty" class="text-muted text-center" style="padding:20px 0;">Busque y agregue cada ingrediente de la compra. Al terminar, guarde todo junto.</p>

	<div id="ip-bar" class="clearfix">
		<span id="ip-total">0 ingredientes · $0.00</span>
		<button type="button" id="ip-save" class="btn btn-success btn-lg pull-right"><i class="fa fa-check"></i> Guardar compra</button>
	</div>
</form>

<!-- New ingredient -->
<div class="modal fade" id="modal-new" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Nuevo ingrediente</h4></div>
	<div class="modal-body">
		<div class="form-group"><label>Nombre</label><input type="text" id="nw-name" class="form-control" maxlength="100"></div>
		<div class="form-group"><label>Unidad base</label>
			<select id="nw-unit" class="form-control"><?php foreach( $units as $k => $label ) : ?><option value="<?php echo $k; ?>"><?php echo $label; ?></option><?php endforeach; ?></select>
		</div>
		<div class="row">
			<div class="col-xs-7 form-group"><label>Presentación (opcional)</label><input type="text" id="nw-pack" class="form-control" maxlength="60" placeholder="ej. Saco 25 lb"></div>
			<div class="col-xs-5 form-group"><label>Contiene (unid. base)</label><input type="text" id="nw-size" inputmode="decimal" class="form-control"></div>
		</div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" id="nw-save">Crear y agregar</button></div>
</div></div></div>

<script>
(function(){
	var ING = <?php echo json_encode($ingredients); ?>;
	var UNITS = <?php echo json_encode($units); ?>;
	var RATE = <?php echo json_encode((float) $rate); ?>; // C$ per US$ (dollar_exchange.sell_price)
	function cor(n){ return 'C$ ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function usd(n){ return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
	function esc(t){ return $('<div>').text(t == null ? '' : t).html(); }
	function attr(t){ return esc(t).replace(/"/g, '&quot;'); }
	function num(v){ var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }
	function fmt(n){ return (Math.round(n * 1000) / 1000).toLocaleString('en-US', { maximumFractionDigits: 3 }); }
	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 3500 }); }
	function byId(id){ for( var i = 0; i < ING.length; i++ ){ if( ING[i].id == id ){ return ING[i]; } } return null; }
	function lineOf(id){ return $('#ip-lines .ip-card[data-id="' + id + '"]'); }
	function optionIds(i){ var r = ['0']; i.packs.forEach(function(p){ r.push(String(p.id)); }); return r; }
	function usedPacks(id, except){ var r = []; lineOf(id).each(function(){ if( !except || this !== except[0] ){ r.push(String($(this).find('.ip-pack').val())); } }); return r; }

	function search(){
		var q = $.trim($('#ip-search').val()).toLowerCase(), box = $('#ip-results').empty();
		if( q.length < 1 ){ return; }
		var exact = false, n = 0;
		ING.forEach(function(i){
			var nm = i.name.toLowerCase();
			if( nm === q ){ exact = true; }
			if( nm.indexOf(q) === -1 || n >= 12 ){ return; }
			n++;
			box.append('<a href="#" class="list-group-item ip-pick" data-id="' + i.id + '"><strong>' + esc(i.name) + '</strong> <small class="text-muted">· ' + fmt(i.stock) + ' ' + esc(i.unit) + ' en existencia' + (lineOf(i.id).length ? ' · ya agregado ×' + lineOf(i.id).length + (usedPacks(i.id).length >= optionIds(i).length ? ' (sin presentaciones libres)' : '') : '') + '</small></a>');
		});
		if( !exact ){ box.append('<a href="#" class="list-group-item ip-create" style="color:#26b99a;"><i class="fa fa-plus"></i> Crear ingrediente nuevo «' + esc($('#ip-search').val().trim()) + '»</a>'); }
	}

	function addLine(i){
		var used = usedPacks(i.id), free = null;
		optionIds(i).forEach(function(o){ if( free === null && used.indexOf(o) === -1 ){ free = o; } });
		if( free === null ){
			var ex = lineOf(i.id).last(); ex.addClass('flash'); setTimeout(function(){ ex.removeClass('flash'); }, 900);
			notify('error', 'Ya agregó todas las presentaciones de «' + i.name + '»');
			return;
		}
		var opts = '<option value="0">' + esc(UNITS[i.unit] || i.unit) + ' (unidad base)</option>';
		i.packs.forEach(function(p){ opts += '<option value="' + p.id + '" data-size="' + p.size + '">' + esc(p.name) + ' (' + fmt(p.size) + ' ' + esc(i.unit) + ')</option>'; });
		var card = $('<div class="ip-card" data-id="' + i.id + '"><span class="ip-remove" title="Quitar">&times;</span>' +
			'<div class="ip-name">' + esc(i.name) + '</div><div class="ip-stock">Existencia: ' + fmt(i.stock) + ' ' + esc(i.unit) + (i.cost !== null ? ' · costo actual C$ ' + (i.cost * RATE).toFixed(4) + '/' + esc(i.unit) : '') + '</div>' +
			'<div class="row" style="margin-top:6px;"><div class="col-xs-12 form-group" style="margin-bottom:6px;"><label>Presentación</label><select class="form-control ip-pack">' + opts + '</select></div>' +
			'<div class="col-xs-5 form-group" style="margin-bottom:0;"><label>Cantidad</label><input type="text" inputmode="decimal" class="form-control ip-qty" placeholder="0"></div>' +
			'<div class="col-xs-7 form-group" style="margin-bottom:0;"><label>Total pagado C$ (opcional)</label><input type="text" inputmode="decimal" class="form-control ip-cost" placeholder="0.00"></div></div>' +
			'<div class="ip-info"></div></div>');
		card.data('ing', i);
			card.find('.ip-pack').val(free).attr('data-prev', free);
		$('#ip-lines').prepend(card);
		$('#ip-empty').hide();
		recalc();
		card.find('.ip-qty').focus();
	}

	function baseQty(card){
		var size = parseFloat(card.find('.ip-pack option:selected').data('size')) || 1;
		return num(card.find('.ip-qty').val()) * size;
	}

	function recalc(){
		var total = 0, n = 0, seen = {};
		$('#ip-lines .ip-card').each(function(){
			var card = $(this), i = card.data('ing'), base = baseQty(card), costCor = num(card.find('.ip-cost').val()), cost = costCor / RATE, info = '';
			if( !seen[i.id] ){ seen[i.id] = 1; n++; } total += costCor;
			if( base > 0 ){
				info = '= ' + fmt(base) + ' ' + esc(i.unit);
				if( cost > 0 ){
					var u = cost / base;
					info += ' · C$ ' + (u * RATE).toFixed(4) + '/' + esc(i.unit) + ' ($' + u.toFixed(4) + ')';
					if( i.cost && Math.abs(u - i.cost) / i.cost > 0.3 ){ info += ' <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' + (u > i.cost ? 'sube' : 'baja') + ' ' + Math.round(Math.abs(u - i.cost) / i.cost * 100) + '% vs. costo actual</span>'; }
				}
			}
			card.find('.ip-info').html(info);
		});
		$('#ip-total').text(n + ' ingrediente' + (n === 1 ? '' : 's') + ' · ' + cor(total) + ' · ' + usd(total / RATE));
		$('#ip-empty').toggle(n === 0);
	}

	$('#ip-search').on('input', search);
	$('#ip-results').on('click', '.ip-pick', function(e){ e.preventDefault(); addLine(byId($(this).data('id'))); $('#ip-search').val('').focus(); $('#ip-results').empty(); });
	$('#ip-results').on('click', '.ip-create', function(e){
		e.preventDefault();
		$('#nw-name').val($.trim($('#ip-search').val())); $('#nw-pack, #nw-size').val(''); $('#nw-save').prop('disabled', false);
		$('#modal-new').modal('show');
	});
	$('#modal-new').on('shown.bs.modal', function(){ $('#nw-pack').focus(); });
	$('#nw-save').on('click', function(){
		var btn = $(this); btn.prop('disabled', true);
		$.ajax({ url: '<?php echo base_url('ingredients/quick_create'); ?>', type: 'post', dataType: 'json',
			data: { tk: $('#ip-tk').val(), name: $('#nw-name').val(), unit: $('#nw-unit').val(), pack_name: $('#nw-pack').val(), pack_size: $('#nw-size').val() },
			success: function(r){
				if( r.tk ){ $('#ip-tk').val(r.tk); }
				if( r.type !== 'success' ){ notify(r.type, r.message); btn.prop('disabled', false); return; }
				ING.push(r.ingredient); $('#modal-new').modal('hide');
				$('#ip-search').val(''); $('#ip-results').empty(); addLine(r.ingredient);
			},
			error: function(){ notify('error', 'Ha ocurrido un error creando el ingrediente'); btn.prop('disabled', false); }
		});
	});

	$('#ip-lines').on('change', '.ip-pack', function(){
		var sel = $(this), card = sel.closest('.ip-card'), i = card.data('ing');
		if( usedPacks(i.id, card).indexOf(String(sel.val())) !== -1 ){
			sel.val(sel.attr('data-prev'));
			notify('error', 'Esa presentación de «' + i.name + '» ya está en la compra');
		} else { sel.attr('data-prev', sel.val()); }
	});
	$('#ip-lines').on('input change', '.ip-qty, .ip-cost, .ip-pack', recalc).on('click', '.ip-remove', function(){ $(this).closest('.ip-card').remove(); recalc(); });

	$('#ip-save').on('click', function(){
		var lines = [], rows = '', bad = null, dup = null, seenKeys = {}, total = 0;
		$('#ip-lines .ip-card').each(function(){
			var card = $(this), i = card.data('ing'), q = num(card.find('.ip-qty').val());
			if( q <= 0 ){ bad = bad || card; return; }
			var key = i.id + '|' + card.find('.ip-pack').val();
			if( seenKeys[key] ){ dup = dup || card; return; }
			seenKeys[key] = 1;
			var cost = card.find('.ip-cost').val();
			lines.push({ id_ingredient: i.id, id_pack: card.find('.ip-pack').val(), qty: card.find('.ip-qty').val(), cost: cost });
			total += num(cost);
			rows += '<tr><td>' + esc(i.name) + '</td><td class="text-right">' + esc(card.find('.ip-qty').val()) + ' ' + esc(card.find('.ip-pack option:selected').text().replace(/ \(.*$/, '')) + '</td><td class="text-right">' + (num(cost) > 0 ? cor(num(cost)) : '—') + '</td></tr>';
		});
		if( bad ){ bad.addClass('flash').find('.ip-qty').focus(); notify('error', 'Indique la cantidad de cada ingrediente (o quite la línea)'); return; }
		if( dup ){ dup.addClass('flash'); notify('error', 'Hay una presentación repetida para el mismo ingrediente'); return; }
		if( !lines.length ){ notify('error', 'Agregue al menos un ingrediente a la compra'); return; }

		bootbox.confirm({
			title: 'Confirmar compra', size: 'large',
			message: '<table class="table table-condensed"><thead><tr><th>Ingrediente</th><th class="text-right">Cantidad</th><th class="text-right">Pagado</th></tr></thead><tbody>' + rows + '</tbody><tfoot><tr><th colspan="2" class="text-right">Total</th><th class="text-right">' + cor(total) + '<br><small>' + usd(total / RATE) + ' (TC ' + RATE.toFixed(2) + ')</small></th></tr></tfoot></table>',
			buttons: { confirm: { label: 'Guardar compra', className: 'btn-success' }, cancel: { label: 'Revisar', className: 'btn-default' } },
			callback: function(ok){
				if( !ok ){ return; }
				var btn = $('#ip-save').prop('disabled', true);
				$.ajax({ url: '<?php echo base_url('ingredients/purchase_save'); ?>', type: 'post', dataType: 'json',
					data: { tk: $('#ip-tk').val(), lines: JSON.stringify(lines), id_provider: $('#ip-store').val(), note: $('#ip-note').val(), date: $('#ip-date').val() },
					success: function(r){
						if( r.tk ){ $('#ip-tk').val(r.tk); }
						notify(r.type, r.message);
						if( r.type === 'success' ){ setTimeout(function(){ window.location = '<?php echo base_url('ingredients/purchase_detail'); ?>/' + r.id_purchase; }, 800); }
						else { btn.prop('disabled', false); }
					},
					error: function(){ notify('error', 'Ha ocurrido un error guardando la compra'); btn.prop('disabled', false); }
				});
			}
		});
	});
	recalc();
})();
</script>
