<style>
	.sc-card{background:#fff;border:1px solid #ddd;border-radius:6px;margin-bottom:10px;padding:10px 12px;}
	.sc-card h5{margin:0 0 6px;font-weight:bold;font-size:15px;}
	.sc-card .sc-bc{color:#888;font-weight:normal;font-size:12px;}
	.sc-row{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;}
	.sc-row .sc-qty{width:84px;} .sc-row .sc-pack{flex:1 1 150px;min-width:130px;} .sc-row .sc-note{flex:2 1 150px;min-width:120px;}
	.sc-row .sc-units{min-width:80px;text-align:right;color:#555;font-size:13px;}
	#sc-results .list-group-item{cursor:pointer;}
	#sc-bar{position:sticky;bottom:calc(60px + env(safe-area-inset-bottom));background:#fff;border:1px solid #ccc;border-radius:6px;padding:10px;margin-top:12px;box-shadow:0 -2px 8px rgba(0,0,0,.08);z-index:20;}
	@media (min-width:992px){ #sc-bar{bottom:0;} }
</style>

<h3 class="section-title"><?php echo $is_edit ? 'Corregir conteo' : 'Contar'; ?>: <?php echo htmlspecialchars($ss->storage_name); ?>
	<a href="<?php echo base_url('shift_inventory/view/'.(int) $ss->id_shift); ?>" class="btn btn-default btn-sm pull-right">Volver</a></h3>
<p class="text-muted" style="font-size:13px;"><?php echo htmlspecialchars($ss->shift_label); ?> · Registre lo que hay físicamente en este almacén, por presentación (unidades, cajas, six-pack...). No cambia el stock del sistema.
<?php if( !$is_edit ) : ?><strong>Una vez guardado, solo el super puede editarlo.</strong><?php endif; ?></p>

<div class="form-group" style="position:relative;">
	<label>Buscar producto (nombre o código de barras)</label>
	<input type="text" id="sc-search" class="form-control" placeholder="Escriba al menos 2 letras o escanee y presione Enter" autocomplete="off">
	<div id="sc-results" class="list-group" style="position:absolute;z-index:30;left:0;right:0;max-height:260px;overflow:auto;display:none;"></div>
</div>

<div id="sc-empty-hint" class="alert alert-info" style="display:none;">Aún no hay productos. Busque y agregue, o marque el almacén como vacío.</div>
<div id="sc-cards"></div>

<div class="checkbox"><label><input type="checkbox" id="sc-is-empty"> Este almacén está vacío (no hay productos)</label></div>
<div class="form-group"><label>Observaciones (opcional)</label><input type="text" id="sc-notes" maxlength="255" class="form-control" placeholder="Ej.: faltó revisar el estante alto" value="<?php echo htmlspecialchars((string) $ss->notes); ?>"></div>

<div id="sc-bar">
	<span id="sc-summary" class="text-muted" style="margin-right:10px;"></span>
	<button type="button" class="btn btn-primary" id="sc-save"><i class="fa fa-check"></i> <?php echo $is_edit ? 'Guardar corrección' : 'Guardar inventario'; ?></button>
</div>
<input type="hidden" id="sc-tk" value="<?php echo $tk; ?>">

<script>
(function(){
	var INITIAL = <?php echo json_encode($lines); ?>;
	var cards = [];   // [{id, name, barcode, packs:[{id_pack,name,units}], rows:[{id_pack,qty,note}]}]
	var searchTimer = null;

	function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }
	function find(id){ for( var i = 0; i < cards.length; i++ ){ if( cards[i].id === id ){ return cards[i]; } } return null; }
	function packUnits(c, id_pack){ if( !id_pack ){ return 1; } for( var i = 0; i < c.packs.length; i++ ){ if( c.packs[i].id_pack == id_pack ){ return parseInt(c.packs[i].units, 10); } } return 1; }

	function render(){
		var html = '', total = 0, nrows = 0;
		cards.forEach(function(c, ci){
			html += '<div class="sc-card" data-ci="' + ci + '"><h5>' + esc(c.name) + ' <span class="sc-bc">' + esc(c.barcode || '') + '</span> <button type="button" class="btn btn-danger btn-xs pull-right sc-del-card">&times;</button></h5>';
			c.rows.forEach(function(r, ri){
				var q = parseInt(r.qty, 10) || 0, u = q * packUnits(c, r.id_pack); total += u; nrows++;
				var opts = '<option value="">Unidad</option>';
				c.packs.forEach(function(p){ opts += '<option value="' + p.id_pack + '"' + (r.id_pack == p.id_pack ? ' selected' : '') + '>' + esc(p.name) + ' (x' + p.units + ')</option>'; });
				html += '<div class="sc-row" data-ri="' + ri + '"><input type="text" inputmode="numeric" class="form-control sc-qty" placeholder="Cant." value="' + esc(r.qty) + '">'
					+ '<select class="form-control sc-pack">' + opts + '</select>'
					+ '<input type="text" class="form-control sc-note" maxlength="150" placeholder="Nota (opcional)" value="' + esc(r.note) + '">'
					+ '<span class="sc-units">= <strong>' + u + '</strong> u</span>'
					+ '<button type="button" class="btn btn-default btn-xs sc-del-row">&times;</button></div>';
			});
			if( c.packs.length ){ html += '<button type="button" class="btn btn-link btn-xs sc-add-row"><i class="fa fa-plus"></i> otra presentación</button>'; }
			html += '</div>';
		});
		$('#sc-cards').html(html);
		$('#sc-empty-hint').toggle(cards.length === 0 && !$('#sc-is-empty').is(':checked'));
		$('#sc-summary').text(cards.length + ' producto(s) · ' + total + ' unidades');
	}
	function refreshTotals(){
		var total = 0;
		$('#sc-cards .sc-card').each(function(){
			var c = cards[$(this).data('ci')];
			$(this).find('.sc-row').each(function(){
				var r = c.rows[$(this).data('ri')], u = (parseInt(r.qty, 10) || 0) * packUnits(c, r.id_pack);
				total += u; $(this).find('.sc-units strong').text(u);
			});
		});
		$('#sc-summary').text(cards.length + ' producto(s) · ' + total + ' unidades');
	}

	function addProduct(p, id_pack){
		var id = parseInt(p.id_product, 10), c = find(id);
		if( c ){
			c.rows.push({ id_pack: id_pack || '', qty: 1, note: '' }); render(); return;
		}
		$.post('<?php echo base_url('products/packs'); ?>/' + id, {}, function(r){
			var packs = (r && r.packs) ? r.packs : [];
			cards.push({ id: id, name: p.name, barcode: p.barcode, packs: packs, rows: [{ id_pack: id_pack || '', qty: 1, note: '' }] });
			$('#sc-is-empty').prop('checked', false);
			render();
		}, 'json');
	}

	function hideResults(){ $('#sc-results').hide().empty(); }

	$('#sc-search').on('input', function(){
		var q = $.trim($(this).val());
		clearTimeout(searchTimer);
		if( q.length < 2 ){ hideResults(); return; }
		searchTimer = setTimeout(function(){
			$.post('<?php echo base_url('products/searchProducts'); ?>', { name: q, type: 'products' }, function(r){
				var list = (r && r.products) ? r.products : [];
				if( !list.length ){ $('#sc-results').html('<div class="list-group-item text-muted">Sin resultados</div>').show(); return; }
				var h = '';
				list.forEach(function(p, i){ h += '<a class="list-group-item" data-i="' + i + '">' + esc(p.name) + ' <small class="text-muted">' + esc(p.barcode || '') + '</small></a>'; });
				$('#sc-results').data('list', list).html(h).show();
			}, 'json');
		}, 300);
	}).on('keydown', function(e){
		if( e.which !== 13 ){ return; }
		e.preventDefault();
		var code = $.trim($(this).val());
		if( !code ){ return; }
		$.post('<?php echo base_url('products/scan'); ?>', { code: code }, function(r){
			if( r && r.found ){ addProduct(r.product, r.id_pack || ''); $('#sc-search').val(''); hideResults(); return; }
			var list = $('#sc-results').data('list');
			if( list && list.length === 1 ){ addProduct(list[0], ''); $('#sc-search').val(''); hideResults(); }
			else{ new PNotify({ title: 'Notificación', text: 'No se encontró un producto único: elija de la lista', type: 'info', styling: 'bootstrap3', delay: 2500 }); }
		}, 'json');
	});
	$('#sc-results').on('click', '.list-group-item[data-i]', function(){
		var list = $('#sc-results').data('list');
		addProduct(list[$(this).data('i')], '');
		$('#sc-search').val('').focus(); hideResults();
	});
	$(document).on('click', function(e){ if( !$(e.target).closest('#sc-results, #sc-search').length ){ hideResults(); } });

	$('#sc-cards').on('input', '.sc-qty', function(){
		var $r = $(this).closest('.sc-row'), c = cards[$r.closest('.sc-card').data('ci')];
		c.rows[$r.data('ri')].qty = $(this).val().replace(/[^0-9]/g, ''); refreshTotals();
	}).on('input', '.sc-note', function(){
		var $r = $(this).closest('.sc-row'); cards[$r.closest('.sc-card').data('ci')].rows[$r.data('ri')].note = $(this).val();
	}).on('change', '.sc-pack', function(){
		var $r = $(this).closest('.sc-row'); cards[$r.closest('.sc-card').data('ci')].rows[$r.data('ri')].id_pack = $(this).val(); refreshTotals();
	}).on('click', '.sc-del-row', function(){
		var $r = $(this).closest('.sc-row'), c = cards[$r.closest('.sc-card').data('ci')];
		c.rows.splice($r.data('ri'), 1); if( !c.rows.length ){ cards.splice(cards.indexOf(c), 1); } render();
	}).on('click', '.sc-del-card', function(){
		cards.splice($(this).closest('.sc-card').data('ci'), 1); render();
	}).on('click', '.sc-add-row', function(){
		var c = cards[$(this).closest('.sc-card').data('ci')]; c.rows.push({ id_pack: '', qty: 1, note: '' }); render();
	});

	$('#sc-is-empty').on('change', function(){
		if( this.checked && cards.length ){
			var self = this;
			bootbox.confirm('Marcar como vacío descarta los productos agregados. ¿Continuar?', function(ok){ if( ok ){ cards = []; render(); } else { $(self).prop('checked', false); } });
		}
		else{ render(); }
	});

	$('#sc-save').on('click', function(){
		var empty = $('#sc-is-empty').is(':checked'), lines = [], bad = false;
		cards.forEach(function(c){ c.rows.forEach(function(r){
			if( !/^[0-9]+$/.test(String(r.qty)) || parseInt(r.qty, 10) < 1 ){ bad = true; }
			lines.push({ id_product: c.id, id_pack: r.id_pack || null, qty: r.qty, note: r.note });
		}); });
		if( bad ){ new PNotify({ title: 'Notificación', text: 'Todas las cantidades deben ser enteros mayores que 0', type: 'error', styling: 'bootstrap3' }); return; }
		if( !empty && !lines.length ){ new PNotify({ title: 'Notificación', text: 'Agregue productos o marque el almacén como vacío', type: 'error', styling: 'bootstrap3' }); return; }

		bootbox.confirm('<?php echo $is_edit ? '¿Guardar la corrección de este conteo?' : '¿Guardar el inventario de este almacén? Después solo el super podrá editarlo.'; ?>', function(ok){
			if( !ok ){ return; }
			var $b = $('#sc-save').prop('disabled', true);
			$.ajax({ url: '<?php echo base_url('shift_inventory/count_save/'.(int) $ss->id_shift_storage); ?>', type: 'post', dataType: 'json',
				data: { tk: $('#sc-tk').val(), lines: JSON.stringify(lines), is_empty: empty ? 1 : 0, notes: $('#sc-notes').val() },
				success: function(r){
					if( r.tk ){ $('#sc-tk').val(r.tk); }
					new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 4000 });
					if( r.redirect && (r.type === 'success' || r.code === 'taken' || r.code === 'closed') ){ setTimeout(function(){ location.href = r.redirect; }, r.type === 'success' ? 600 : 2200); }
					else{ $b.prop('disabled', false); }
				},
				error: function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); $b.prop('disabled', false); }
			});
		});
	});

	// Load existing lines (edit): group by product, fetch each product's packs once.
	var order = [], byId = {};
	INITIAL.forEach(function(l){
		if( !byId[l.id_product] ){ byId[l.id_product] = { id: l.id_product, name: l.name, barcode: l.barcode, packs: [], rows: [] }; order.push(l.id_product); }
		byId[l.id_product].rows.push({ id_pack: l.id_pack || '', qty: l.qty, note: l.note });
	});
	<?php if( $is_edit && empty($lines) ) : ?>$('#sc-is-empty').prop('checked', <?php echo (int) $ss->is_empty === 1 ? 'true' : 'false'; ?>);<?php endif; ?>
	var pending = order.length;
	if( !pending ){ render(); }
	order.forEach(function(id){
		$.post('<?php echo base_url('products/packs'); ?>/' + id, {}, function(r){
			byId[id].packs = (r && r.packs) ? r.packs : [];
			if( --pending === 0 ){ order.forEach(function(i){ cards.push(byId[i]); }); render(); }
		}, 'json');
	});
	render();
})();
</script>
