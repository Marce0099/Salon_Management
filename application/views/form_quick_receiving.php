<h4 class="section-title">Registrar compra</h4>

<style>
	.qr-card { border:1px solid #ddd; border-radius:6px; padding:10px; margin-bottom:10px; background:#fff; position:relative; }
	.qr-card .qr-name { font-weight:bold; padding-right:30px; }
	.qr-card .qr-remove { position:absolute; top:6px; right:10px; font-size:20px; color:#c9302c; cursor:pointer; }
	.qr-card .form-control { height:42px; font-size:16px; }
	.qr-card label { font-size:12px; margin-bottom:2px; color:#666; }
	#qr-results .list-group-item { padding:12px; cursor:pointer; }
	#qr-total { font-size:18px; font-weight:bold; }
	@media (min-width: 992px) { #qr-lines { column-count:2; column-gap:12px; } .qr-card { break-inside:avoid; } }
</style>

<form id="qr-form" autocomplete="off" onsubmit="return false;">
	<input type="hidden" id="qr-tk" value="<?php echo $tk; ?>">
	<div class="row">
		<div class="col-sm-4 form-group">
			<label>No. de factura / recibo</label>
			<input type="text" id="qr-invoice-no" class="form-control" maxlength="50" value="<?php echo 'REC-'.date('Ymd-His'); ?>">
		</div>
		<div class="col-sm-4 form-group">
			<label>Proveedor</label>
			<?php $this->load->view('_provider_picker', array('pp_hidden_id'=>'qr-provider','pp_default_first'=>FALSE,'providers'=>$providers)); ?>
		</div>
		<div class="col-sm-4 form-group">
			<label>Fecha</label>
			<input type="text" id="qr-date" class="form-control" value="<?php echo date('d/m/Y'); ?>">
		</div>
	</div>

	<div class="form-group">
		<input type="search" id="qr-search" class="form-control input-lg" placeholder="Buscar por nombre, o escanee el código (producto o presentación) y Enter..." style="height:48px;">
		<div id="qr-results" class="list-group" style="max-height:260px;overflow-y:auto;margin-top:4px;"></div>
	</div>

	<div id="qr-lines"></div>

	<div class="clearfix">
		<span id="qr-total">Total: $0.00</span>
		<button type="button" id="qr-save" class="btn btn-success btn-lg pull-right"><i class="fa fa-check"></i> Guardar recepción</button>
	</div>
</form>

<script>
(function(){

	var timer = null;

	function esc(t){ return $('<div>').text(t == null ? '' : t).html(); }

	function search(){

		var q = $.trim($('#qr-search').val());

		if( q.length < 2 ){ $('#qr-results').empty(); return; }

		$.ajax({
			url : '<?php echo base_url('products/searchProducts'); ?>',
			type : 'post',
			dataType : 'json',
			data : { name : q, barcode : '', type : 'products' },
			success : function(data){

				var $r = $('#qr-results').empty();
				var list = data.products || [];

				// scanner input: if nothing matched by name, try the barcode
				if( !list.length ){
					$.post('<?php echo base_url('products/searchProducts'); ?>', { name : '', barcode : q, type : 'products' }, function(d2){
						var j = typeof d2 === 'string' ? $.parseJSON(d2) : d2;
						render((j && j.products) || []);
					}).fail(function(){ notify('Error al buscar el producto', 'error'); });
					return;
				}

				render(list);
			}
		});
	}

	function render(list){

		var $r = $('#qr-results').empty();

		if( !list.length ){ $r.append('<div class="list-group-item text-muted">Sin resultados</div>'); return; }

		$.each(list, function(i, p){
			$('<a class="list-group-item">').html('<strong>' + esc(p.name) + '</strong> <small class="text-muted">' + esc(p.barcode || '') + '</small>')
				.data('p', p).appendTo($r);
		});
	}

	$('#qr-search').on('input', function(){ clearTimeout(timer); timer = setTimeout(search, 300); });

	$(document).on('click', '#qr-results .list-group-item', function(){

		var p = $(this).data('p');
		if( !p ){ return; }

		addNext(p);

		$('#qr-results').empty();
		$('#qr-search').val('');
	});

	var packCache = {};

	// packs of a product (cached): cb(packs[]) in server order
	function getPacks(id, cb){

		if( packCache[id] ){ cb(packCache[id]); return; }

		$.getJSON('<?php echo base_url('products/packs'); ?>/' + id, function(data){
			packCache[id] = (data && data.packs) ? data.packs : [];
			cb(packCache[id]);
		}).fail(function(){ notify('Error al cargar las presentaciones', 'error'); });
	}

	// Adds the product with the first presentation not yet listed (base unit, then each pack in order).
	function addNext(p){

		var id = parseInt(p.id_product, 10);

		getPacks(id, function(packs){

			var order = [''];
			$.each(packs, function(i, pk){ order.push(String(pk.id_pack)); });

			var used = {};
			$('#qr-lines .qr-card[data-id="' + id + '"]').each(function(){ used[String($(this).attr('data-pack') || '')] = true; });

			for( var i = 0; i < order.length; i++ ){
				if( !used[order[i]] ){ addLine(p, order[i]); return; }
			}

			notify('Todas las presentaciones de "' + p.name + '" ya están en la lista', 'error');
		});
	}

	function addLine(p, prePack, preQty){

		var $c = $(
			'<div class="qr-card" data-id="' + parseInt(p.id_product, 10) + '" data-pack="' + (prePack || '') + '" data-prev-cost="' + esc(p.price_cost) + '">' +
				'<span class="qr-remove"><i class="fa fa-times"></i></span>' +
				'<div class="qr-name">' + esc(p.name) + '</div>' +
				'<div class="row">' +
					'<div class="col-xs-6 qr-pack-wrap" style="display:none;"><label>Presentación</label><select class="form-control qr-pack"><option value="" data-units="1">Unidad</option></select></div>' +
					'<div class="col-xs-6"><label>Cantidad</label><input type="number" inputmode="numeric" min="1" step="1" class="form-control qr-qty" value="1"></div>' +
					'<div class="col-xs-6"><label>Costo por unidad ($)</label><input type="text" inputmode="decimal" class="form-control qr-cost" value="' + esc(p.price_cost) + '"></div>' +
					'<div class="col-xs-6"><label>Precio venta ($)</label><input type="text" inputmode="decimal" class="form-control qr-sell" value="' + esc(p.price_sell) + '"></div>' +
				'</div>' +
				'<div class="small qr-warn" style="margin-top:4px;"></div>' +
				'<div class="text-right text-muted qr-units" style="margin-top:4px;"></div>' +
			'</div>'
		);

		$('#qr-lines').prepend($c);

		getPacks(parseInt(p.id_product, 10), function(packs){

			if( packs.length ){

				var $sel = $c.find('.qr-pack');

				$.each(packs, function(i, pk){
					$('<option>').val(pk.id_pack).attr('data-units', pk.units).attr('data-cost', pk.price_cost === null ? '' : pk.price_cost)
						.text(pk.name + ' (x' + pk.units + ')').appendTo($sel);
				});

				$c.find('.qr-pack-wrap').show();

				if( prePack ){ $sel.val(String(prePack)).trigger('change'); }
			}
		});

		if( preQty ){ $c.find('.qr-qty').val(preQty); }

		$c.find('.qr-qty').focus().select();
		recalc();
	}

	// Scanner: a code + Enter adds the product (pack barcode = that pack preselected); scanning again adds +1.
	function scan(code){

		$.post('<?php echo base_url('products/scan'); ?>', { code : code }, function(raw){

			var d = typeof raw === 'string' ? $.parseJSON(raw) : raw;

			if( !d.found ){ notify('Código no encontrado: ' + code, 'error'); return; }

			var pack = d.id_pack ? String(d.id_pack) : '';
			var $line = $('#qr-lines .qr-card[data-id="' + parseInt(d.product.id_product, 10) + '"][data-pack="' + pack + '"]');

			if( $line.length ){
				var $q = $line.find('.qr-qty');
				$q.val((parseInt($q.val(), 10) || 0) + 1);
				recalc();
			}
			else{
				addLine(d.product, pack, 1);
			}

			$('#qr-results').empty();
			$('#qr-search').val('').focus();
		}).fail(function(){ notify('Error al buscar el código', 'error'); });
	}

	$('#qr-search').on('keydown', function(e){

		if( e.which !== 13 ){ return; }

		e.preventDefault();

		var code = $.trim($(this).val());

		if( code ){ clearTimeout(timer); scan(code); }
	});

	function units($c){ return parseInt($c.find('.qr-pack option:selected').attr('data-units'), 10) || 1; }

	function num(v){ var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }

	// Soft warnings (never block saving): cost jump vs. the last cost, and thin or negative margin.
	function costWarning(cost, sell, prev){

		var msgs = [];

		if( prev > 0 && cost > 0 ){

			var jump = (cost - prev) / prev * 100;

			if( Math.abs(jump) >= 20 ){
				msgs.push('<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Costo ' + (jump > 0 ? 'subió ' : 'bajó ') + Math.abs(jump).toFixed(0) + '% (antes $' + prev.toFixed(2) + ')</span>');
			}
		}

		if( sell > 0 && cost > 0 ){

			if( sell <= cost ){
				msgs.push('<span class="text-danger"><i class="fa fa-exclamation-circle"></i> Venta menor o igual al costo: pérdida</span>');
			}
			else{
				var margin = (sell - cost) / sell * 100;
				msgs.push('<span class="' + (margin < 30 ? 'text-warning' : 'text-muted') + '">Margen ' + margin.toFixed(0) + '%' + (margin < 30 ? ' (bajo)' : '') + '</span>');
			}
		}

		return msgs.join('<br>');
	}

	function recalc(){

		var total = 0;

		$('#qr-lines .qr-card').each(function(){

			var $c = $(this);
			var u = units($c) * (parseInt($c.find('.qr-qty').val(), 10) || 0);
			total += u * num($c.find('.qr-cost').val());
			$c.find('.qr-units').text(u + ' unidades');
			$c.find('.qr-warn').html( costWarning(num($c.find('.qr-cost').val()), num($c.find('.qr-sell').val()), num($c.attr('data-prev-cost'))) );
		});

		$('#qr-total').text('Total: $' + total.toFixed(2));
	}

	$(document).on('input', '.qr-qty, .qr-cost, .qr-sell', recalc);

	// Cost change keeps the same markup: the sell price moves by the same amount, unless the user typed the price by hand.
	$(document).on('focusin', '.qr-cost', function(){ $(this).attr('data-prev', num($(this).val())); });

	$(document).on('input', '.qr-sell', function(){ $(this).attr('data-manual', '1'); });

	$(document).on('change', '.qr-cost', function(){

		var $c = $(this).closest('.qr-card');
		var $sell = $c.find('.qr-sell');
		var prev = parseFloat($(this).attr('data-prev'));
		var now = num($(this).val());

		if( $sell.attr('data-manual') || isNaN(prev) || prev <= 0 || now <= 0 ){ return; }

		var next = num($sell.val()) + (now - prev);

		if( next > 0 ){ $sell.val(next.toFixed(2)); recalc(); }
	});

	$(document).on('change', '.qr-pack', function(){

		var $c = $(this).closest('.qr-card');
		var opt = $(this).find('option:selected');
		var np = String($(this).val() || '');

		var dup = $('#qr-lines .qr-card[data-id="' + parseInt($c.attr('data-id'), 10) + '"]').not($c).filter(function(){
			return String($(this).attr('data-pack') || '') === np;
		}).length;

		if( dup ){
			$(this).val(String($c.attr('data-pack') || ''));
			notify('Esa presentación ya está en la lista para este producto', 'error');
			return;
		}

		$c.attr('data-pack', np);
		var u = parseInt(opt.attr('data-units'), 10) || 1;
		var pc = parseFloat(opt.attr('data-cost'));

		if( u > 1 && !isNaN(pc) ){
			$c.find('.qr-cost').val( (pc / u).toFixed(4).replace(/0+$/, '').replace(/\.$/, '') );
		}

		recalc();
	});

	$(document).on('click', '.qr-remove', function(){ $(this).closest('.qr-card').remove(); recalc(); });

	function notify(text, type){
		new PNotify({ title : 'Notificación', text : text, type : type, styling : 'bootstrap3', delay : 3000 });
	}

	$('#qr-save').on('click', function(){

		var $cards = $('#qr-lines .qr-card');

		if( !$cards.length ){ notify('Agregue al menos un producto', 'error'); return; }

		var post = [
			{ name : 'tk', value : $('#qr-tk').val() },
			{ name : 'invoice_no', value : $.trim($('#qr-invoice-no').val()) },
			{ name : 'id_provider', value : $('#qr-provider').val() },
			{ name : 'date_time', value : $('#qr-date').val() }
		];

		var bad = false;

		$cards.each(function(){

			var $c = $(this);
			var qty = $.trim($c.find('.qr-qty').val());

			if( !/^[0-9]+$/.test(qty) || parseInt(qty, 10) < 1 ){ bad = true; $c.find('.qr-qty').focus(); return false; }

			post.push({ name : 'id_products[]', value : $c.data('id') });
			post.push({ name : 'quantities[]', value : qty });
			post.push({ name : 'prices_cost[]', value : String($c.find('.qr-cost').val()).replace(',', '.') });
			post.push({ name : 'prices_sell[]', value : String($c.find('.qr-sell').val()).replace(',', '.') });
			post.push({ name : 'id_packs[]', value : $c.find('.qr-pack').val() || '' });
		});

		post.push({ name : 'quick', value : '1' });

		if( bad ){ notify('La cantidad debe ser un entero mayor a cero', 'error'); return; }

		// summary so a wrong quantity is caught before it hits the stock (there is no edit afterwards)
		var rows = '', alerts = 0;
		$cards.each(function(){

			var $c = $(this);
			var q = (parseInt($c.find('.qr-qty').val(), 10) || 0);
			var pk = $c.find('.qr-pack option:selected');
			var label = pk.val() ? ' ' + esc(pk.text()) : '';
			if( $c.find('.qr-warn .text-warning, .qr-warn .text-danger').length ){ alerts++; }

			rows += '<tr><td>' + esc($c.find('.qr-name').text()) + '</td><td class="text-right">' + q + label + '<br><small class="text-muted">' + (q * units($c)) + ' unid.</small></td></tr>';
		});

		var provider = $('#qr-provider').val() ? esc($('#qr-provider_name').val()) : '<em>Sin proveedor</em>';
		var summary = '<p><strong>' + esc($('#qr-invoice-no').val()) + '</strong> · ' + provider + '</p>' +
			'<table class="table table-condensed"><tbody>' + rows + '</tbody></table>' +
			'<h4>' + esc($('#qr-total').text()) + '</h4>' +
			( alerts ? '<p class="text-warning"><i class="fa fa-exclamation-triangle"></i> ' + alerts + ' producto(s) con aviso de costo o margen.</p>' : '' );

		bootbox.confirm({
			title : 'Confirmar recepción',
			message : summary,
			buttons : { confirm : { label : 'Guardar', className : 'btn-success' }, cancel : { label : 'Revisar', className : 'btn-default' } },
			callback : function(ok){ if( ok ){ send(post); } }
		});
	});

	function send(post){

		var $btn = $('#qr-save').prop('disabled', true);

		$.ajax({
			url : '<?php echo base_url('invoices/save'); ?>',
			type : 'post',
			dataType : 'json',
			data : post,
			success : function(data){

				notify(data.message, data.type);

				if( data.tk ){ $('#qr-tk').val(data.tk); }

				if( data.type == 'success' ){
					$('#qr-lines').empty();
					recalc();
					var d = new Date(), z = function(n){ return ('0' + n).slice(-2); };
						$('#qr-invoice-no').val('REC-' + d.getFullYear() + z(d.getMonth() + 1) + z(d.getDate()) + '-' + z(d.getHours()) + z(d.getMinutes()) + z(d.getSeconds()));
				}

				$btn.prop('disabled', false);
			},
			error : function(){ notify('Ha ocurrido un error guardando la recepción', 'error'); $btn.prop('disabled', false); }
		});
	}

	$(function(){

		if( $.fn.daterangepicker ){

			$('#qr-date').daterangepicker({
				locale : {
					format : 'DD/MM/YYYY',
					daysOfWeek : ['Do','Lu','Ma','Mi','Ju','Vi','Sa'],
					monthNames : ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
				},
				maxDate : '<?php echo date('d/m/Y'); ?>',
				singleDatePicker : true,
				showDropdowns : true
			});
		}
	});

})();
</script>
