<h4 class="section-title">Conteo físico de ingredientes</h4>

<style>
	.ct-row { border:1px solid #ddd; border-radius:6px; padding:8px 10px; margin-bottom:6px; background:#fff; }
	.ct-row.changed { border-color:#f0ad4e; background:#fffaf0; }
	.ct-row .ct-name { font-weight:bold; }
	.ct-row .ct-sys { font-size:12px; color:#777; }
	.ct-row .form-control { height:42px; font-size:16px; }
	.ct-row .ct-diff { font-size:12px; min-height:16px; margin-top:2px; }
	#ct-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #ddd; padding:10px 0; margin-top:10px; z-index:5; }
	#ip-form .form-control, #ct-form .form-control { width:100%; display:block; }
	@media (max-width:991px){ #ip-bar, #ct-bar { bottom:62px; } }
</style>

<form id="ct-form" autocomplete="off" onsubmit="return false;">
	<input type="hidden" id="ct-tk" value="<?php echo $tk; ?>">
	<div class="row">
		<div class="col-xs-12 col-sm-8 form-group"><input type="search" id="ct-search" class="form-control input-lg" placeholder="Buscar ingrediente..." style="height:48px;"></div>
		<div class="col-xs-12 col-sm-4 form-group" style="padding-top:10px;"><label><input type="checkbox" id="ct-only"> Mostrar solo los contados</label></div>
	</div>
	<p class="text-muted" style="font-size:12px;">Escriba lo que hay realmente. Los que deje en blanco no se tocan. Se puede contar en una presentación (ej. sacos) si el ingrediente la tiene.</p>
	<div id="ct-list"></div>
	<p id="ct-none" class="text-muted text-center" style="display:none;">Sin resultados.</p>
	<div id="ct-bar" class="clearfix">
		<span id="ct-summary" style="font-weight:bold;">0 contados</span>
		<button type="button" id="ct-save" class="btn btn-warning btn-lg pull-right"><i class="fa fa-check"></i> Guardar conteo</button>
	</div>
</form>

<script>
(function(){
	var ING = <?php echo json_encode($ingredients); ?>;
	function esc(t){ return $('<div>').text(t == null ? '' : t).html(); }
	function num(v){ var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? NaN : n; }
	function fmt(n){ return (Math.round(n * 1000) / 1000).toLocaleString('en-US', { maximumFractionDigits: 3 }); }
	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 4000 }); }

	ING.forEach(function(i){
		var opts = '<option value="0" data-size="1">' + esc(i.unit) + '</option>';
		i.packs.forEach(function(p){ opts += '<option value="' + p.id + '" data-size="' + p.size + '">' + esc(p.name) + '</option>'; });
		var row = $('<div class="ct-row" data-id="' + i.id + '" data-name="' + esc(i.name).replace(/"/g, '&quot;').toLowerCase() + '"><div class="row">' +
			'<div class="col-xs-12 col-sm-5"><div class="ct-name">' + esc(i.name) + (i.prepared ? ' <span class="label label-primary">Preparado</span>' : '') + '</div><div class="ct-sys">Sistema: ' + fmt(i.stock) + ' ' + esc(i.unit) + '</div></div>' +
			'<div class="col-xs-6 col-sm-3" style="margin-top:4px;"><input type="text" inputmode="decimal" class="form-control ct-qty" placeholder="Contado"></div>' +
			'<div class="col-xs-6 col-sm-4" style="margin-top:4px;"><select class="form-control ct-pack"' + (i.packs.length ? '' : ' disabled') + '>' + opts + '</select></div>' +
			'</div><div class="ct-diff"></div></div>');
		row.data('ing', i);
		$('#ct-list').append(row);
	});

	function counted(row){
		var q = num(row.find('.ct-qty').val());
		if( isNaN(q) || $.trim(row.find('.ct-qty').val()) === '' ){ return null; }
		return q * (parseFloat(row.find('.ct-pack option:selected').data('size')) || 1);
	}

	function refresh(){
		var n = 0, diffs = 0, q = $.trim($('#ct-search').val()).toLowerCase(), only = $('#ct-only').is(':checked'), shown = 0;
		$('#ct-list .ct-row').each(function(){
			var row = $(this), i = row.data('ing'), c = counted(row), info = '', has = c !== null;
			if( has ){
				n++;
				var d = Math.round((c - i.stock) * 1000) / 1000;
				if( d === 0 ){ info = '<span class="text-success"><i class="fa fa-check"></i> Coincide</span>'; }
				else{ diffs++; info = '<span class="' + (d < 0 ? 'text-danger' : 'text-warning') + '">Diferencia: ' + (d > 0 ? '+' : '') + fmt(d) + ' ' + esc(i.unit) + ' (quedará en ' + fmt(c) + ')</span>'; }
				row.toggleClass('changed', d !== 0);
			} else { row.removeClass('changed'); }
			row.find('.ct-diff').html(info);
			var vis = (q === '' || String(row.data('name')).indexOf(q) !== -1) && (!only || has);
			row.toggle(vis); if( vis ){ shown++; }
		});
		$('#ct-summary').text(n + ' contado' + (n === 1 ? '' : 's') + (n ? ' · ' + diffs + ' con diferencia' : ''));
		$('#ct-none').toggle(shown === 0);
	}
	$('#ct-search, #ct-only').on('input change', refresh);
	$('#ct-list').on('input change', '.ct-qty, .ct-pack', refresh);

	$('#ct-save').on('click', function(){
		var counts = [], rows = '', bad = null;
		$('#ct-list .ct-row').each(function(){
			var row = $(this), i = row.data('ing'), c = counted(row);
			if( $.trim(row.find('.ct-qty').val()) === '' ){ return; }
			if( c === null || c < 0 ){ bad = bad || row; return; }
			counts.push({ id_ingredient: i.id, id_pack: row.find('.ct-pack').val(), qty: row.find('.ct-qty').val() });
			var d = Math.round((c - i.stock) * 1000) / 1000;
			if( d !== 0 ){ rows += '<tr><td>' + esc(i.name) + '</td><td class="text-right">' + fmt(i.stock) + '</td><td class="text-right">' + fmt(c) + ' ' + esc(i.unit) + '</td><td class="text-right ' + (d < 0 ? 'text-danger' : 'text-warning') + '">' + (d > 0 ? '+' : '') + fmt(d) + '</td></tr>'; }
		});
		if( bad ){ bad.find('.ct-qty').focus(); notify('error', 'Hay una cantidad inválida: use números mayores o iguales a 0'); return; }
		if( !counts.length ){ notify('error', 'Escriba al menos una cantidad contada'); return; }

		bootbox.confirm({
			title: 'Confirmar conteo', size: 'large',
			message: (rows ? '<p>Estos ingredientes cambiarán de existencia:</p><table class="table table-condensed"><thead><tr><th>Ingrediente</th><th class="text-right">Sistema</th><th class="text-right">Contado</th><th class="text-right">Diferencia</th></tr></thead><tbody>' + rows + '</tbody></table>' : '<p>Todo lo contado coincide con el sistema.</p>'),
			buttons: { confirm: { label: 'Guardar conteo', className: 'btn-warning' }, cancel: { label: 'Revisar', className: 'btn-default' } },
			callback: function(ok){
				if( !ok ){ return; }
				var btn = $('#ct-save').prop('disabled', true);
				$.ajax({ url: '<?php echo base_url('ingredients/count_save'); ?>', type: 'post', dataType: 'json', data: { tk: $('#ct-tk').val(), counts: JSON.stringify(counts) },
					success: function(r){
						if( r.tk ){ $('#ct-tk').val(r.tk); }
						notify(r.type, r.message);
						if( r.type === 'success' ){ setTimeout(function(){ window.location = '<?php echo base_url('ingredients'); ?>'; }, 900); } else { btn.prop('disabled', false); }
					},
					error: function(){ notify('error', 'Ha ocurrido un error guardando el conteo'); btn.prop('disabled', false); }
				});
			}
		});
	});
	refresh();
})();
</script>
