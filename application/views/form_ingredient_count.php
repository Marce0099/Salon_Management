<h4 class="section-title">Conteo físico de ingredientes</h4>

<style>
	.ct-row { border:1px solid #ddd; border-radius:6px; padding:8px 10px; margin-bottom:6px; background:#fff; }
	.ct-row.changed { border-color:#f0ad4e; background:#fffaf0; }
	.ct-row .ct-name { font-weight:bold; }
	.ct-row .ct-sys { font-size:12px; color:#777; }
	.ct-row .form-control { height:42px; font-size:16px; }
	.ct-line { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:6px; }
	.ct-line .l-qty { flex:1 1 90px; min-width:80px; }
	.ct-line .l-pack { flex:2 1 130px; min-width:110px; }
	.ct-line .l-pctbox { flex:1 1 90px; min-width:90px; display:flex; flex-wrap:nowrap; white-space:nowrap; align-items:center; gap:4px; }
	.ct-line .l-pctbox .l-pct { width:100%; }
	.ct-line .l-eq { flex:2 1 120px; font-size:12px; color:#555; }
	.ct-line.bad .form-control { border-color:#d9534f; }
	.ct-actions { margin-top:6px; }
	.ct-actions .btn { margin-right:4px; margin-top:4px; }
	.ct-row .ct-diff { font-size:12px; min-height:16px; margin-top:2px; }
	#ct-bar { position:sticky; bottom:0; background:#fff; border-top:1px solid #ddd; padding:10px 0; margin-top:10px; z-index:5; }
	#ip-form .form-control, #ct-form .form-control { width:100%; display:block; }
	@media (max-width:991px){ #ip-bar, #ct-bar { bottom:calc(60px + env(safe-area-inset-bottom)); } }
</style>

<form id="ct-form" autocomplete="off" onsubmit="return false;">
	<input type="hidden" id="ct-tk" value="<?php echo $tk; ?>">
	<div class="row">
		<div class="col-xs-12 col-sm-8 form-group"><input type="search" id="ct-search" class="form-control input-lg" placeholder="Buscar ingrediente..." style="height:48px;"></div>
		<div class="col-xs-12 col-sm-4 form-group" style="padding-top:10px;"><label><input type="checkbox" id="ct-only"> Mostrar solo los contados</label></div>
	</div>
	<p class="text-muted" style="font-size:12px;">Escriba lo que hay realmente. Los que deje en blanco no se tocan. Cada ingrediente admite varias líneas, cada una en su presentación (ej. sacos) y con un % de lleno para los abiertos: 4 sacos al 100% + 1 saco al 50% + 1 saco al 75%. La app suma todo en la unidad base.</p>
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

	function lineHtml(i){
		var opts = '<option value="0" data-size="1">' + esc(i.unit) + '</option>';
		i.packs.forEach(function(p){ opts += '<option value="' + p.id + '" data-size="' + p.size + '">' + esc(p.name) + '</option>'; });
		return $('<div class="ct-line">' +
			'<input type="text" inputmode="decimal" class="form-control l-qty" placeholder="Cantidad">' +
			'<select class="form-control l-pack"' + (i.packs.length ? '' : ' disabled') + '>' + opts + '</select>' +
			'<span class="l-pctbox" style="display:none;"><input type="text" inputmode="decimal" class="form-control l-pct" value="100" title="% de lleno"><b>%</b></span>' +
			'<span class="l-eq"></span>' +
			'<button type="button" class="btn btn-default l-del" title="Quitar línea"><i class="fa fa-times"></i></button>' +
			'</div>');
	}

	function addLine(row, preset){
		var i = row.data('ing'), line = lineHtml(i);
		if( preset && i.packs.length ){ line.find('.l-pack').val(String(i.packs[0].id)); line.find('.l-pctbox').show(); line.find('.l-pct').val(preset.pct); line.find('.l-qty').val(preset.qty); }
		row.find('.ct-lines').append(line);
		return line;
	}

	ING.forEach(function(i){
		var row = $('<div class="ct-row" data-id="' + i.id + '" data-name="' + esc(i.name).replace(/"/g, '&quot;').toLowerCase() + '">' +
			'<div class="ct-name">' + esc(i.name) + (i.prepared ? ' <span class="label label-primary">Preparado</span>' : '') + '</div><div class="ct-sys">Sistema: ' + fmt(i.stock) + ' ' + esc(i.unit) + '</div>' +
			'<div class="ct-lines"></div>' +
			'<div class="ct-actions"><button type="button" class="btn btn-default btn-sm ct-add"><i class="fa fa-plus"></i> Agregar línea</button>' +
			'</div>' +
			'<div class="ct-total" style="font-size:12px; margin-top:4px;"></div><div class="ct-diff"></div></div>');
		row.data('ing', i);
		$('#ct-list').append(row);
		addLine(row);
	});

	/** Reads one line: null = blank, {bad:true} = invalid, else {base, text}. */
	function readLine(line, i){
		var qs = $.trim(line.find('.l-qty').val()), opt = line.find('.l-pack option:selected'), idp = line.find('.l-pack').val(), size = parseFloat(opt.data('size')) || 1;
		var isPack = idp !== '0', ps = $.trim(line.find('.l-pct').val());
		if( qs === '' ){ return null; }
		var q = num(qs), pct = (!isPack || ps === '') ? 100 : num(ps);
		if( isNaN(q) || q < 0 || isNaN(pct) || pct < 0 || pct > 100 ){ return { bad: true }; }
		var base = Math.round(q * size * pct / 100 * 1000) / 1000;
		return { base: base, id_pack: idp, qty: qs, pct: isPack ? String(pct) : '100',
			text: fmt(q) + '×' + opt.text() + (isPack && pct !== 100 ? ' al ' + fmt(pct) + '%' : '') };
	}

	/** Row result: null = not counted; else {total, bad, parts, text}. */
	function counted(row){
		var i = row.data('ing'), total = 0, any = false, bad = false, parts = [], texts = [], seen = {};
		row.find('.ct-line').each(function(){
			var line = $(this), r = readLine(line, i), eq = '';
			line.removeClass('bad');
			if( r === null ){ line.find('.l-eq').text(''); return; }
			any = true;
			if( r.bad ){ bad = true; line.addClass('bad'); line.find('.l-eq').html('<span class="text-danger">Valor inválido (cantidad ≥ 0, % entre 0 y 100)</span>'); return; }
			var key = r.id_pack + '|' + r.pct;
			if( seen[key] ){ bad = true; line.addClass('bad'); line.find('.l-eq').html('<span class="text-danger">Línea repetida (misma presentación y %): actualice la cantidad de la línea existente</span>'); return; }
			seen[key] = true;
			total += r.base; parts.push({ id_pack: r.id_pack, qty: r.qty, pct: r.pct }); texts.push(r.text);
			line.find('.l-eq').text('= ' + fmt(r.base) + ' ' + i.unit);
		});
		if( !any ){ return null; }
		return { total: Math.round(total * 1000) / 1000, bad: bad, parts: parts, text: texts.join(' + ') };
	}

	function refresh(){
		var n = 0, diffs = 0, q = $.trim($('#ct-search').val()).toLowerCase(), only = $('#ct-only').is(':checked'), shown = 0;
		$('#ct-list .ct-row').each(function(){
			var row = $(this), i = row.data('ing'), cr = counted(row), c = (cr && !cr.bad) ? cr.total : null, info = '', has = cr !== null;
			row.find('.ct-total').text(cr && !cr.bad && cr.parts.length > 1 ? 'Total contado: ' + fmt(cr.total) + ' ' + i.unit : '');
			if( has && cr.bad ){ n++; info = '<span class="text-danger">Corrija las líneas marcadas</span>'; row.removeClass('changed'); }
			else if( has ){
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
	$('#ct-list').on('input change', '.l-qty, .l-pack, .l-pct', function(){
		if( $(this).is('.l-pack') ){ $(this).closest('.ct-line').find('.l-pctbox').toggle($(this).val() !== '0'); }
		refresh();
	});
	$('#ct-list').on('click', '.ct-add', function(){ addLine($(this).closest('.ct-row')).find('.l-qty').focus(); refresh(); });
	$('#ct-list').on('click', '.l-del', function(){
		var row = $(this).closest('.ct-row'), lines = row.find('.ct-line');
		if( lines.length > 1 ){ $(this).closest('.ct-line').remove(); } else { lines.find('.l-qty').val(''); }
		refresh();
	});

	$('#ct-save').on('click', function(){
		var counts = [], rows = '', bad = null;
		$('#ct-list .ct-row').each(function(){
			var row = $(this), i = row.data('ing'), cr = counted(row);
			if( cr === null ){ return; }
			if( cr.bad ){ bad = bad || row; return; }
			var c = cr.total;
			counts.push({ id_ingredient: i.id, parts: cr.parts });
			var d = Math.round((c - i.stock) * 1000) / 1000;
			if( d !== 0 ){ rows += '<tr><td>' + esc(i.name) + '</td><td class="text-right">' + fmt(i.stock) + '</td><td class="text-right">' + fmt(c) + ' ' + esc(i.unit) + '<div style="font-size:11px;color:#777;">' + esc(cr.text) + '</div></td><td class="text-right ' + (d < 0 ? 'text-danger' : 'text-warning') + '">' + (d > 0 ? '+' : '') + fmt(d) + '</td></tr>'; }
		});
		if( bad ){ bad.find('.ct-line.bad .l-qty').first().focus(); notify('error', 'Hay líneas inválidas o repetidas: cantidad ≥ 0, % entre 0 y 100, y sin repetir presentación + %'); return; }
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
