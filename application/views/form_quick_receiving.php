<h4 class="section-title">Recepción rápida de mercadería</h4>

<style>
	.qr-card { border:1px solid #ddd; border-radius:6px; padding:10px; margin-bottom:10px; background:#fff; position:relative; }
	.qr-card .qr-name { font-weight:bold; padding-right:30px; }
	.qr-card .qr-remove { position:absolute; top:6px; right:10px; font-size:20px; color:#c9302c; cursor:pointer; }
	.qr-card .form-control { height:42px; font-size:16px; }
	.qr-card label { font-size:12px; margin-bottom:2px; color:#666; }
	#qr-results .list-group-item { padding:12px; cursor:pointer; }
	#qr-total { font-size:18px; font-weight:bold; }
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
			<select id="qr-provider" class="form-control">
				<option value="">---</option>
				<?php foreach( $providers as $p ) : ?>
				<option value="<?php echo (int)$p->id_provider; ?>"><?php echo htmlspecialchars($p->provider); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-sm-4 form-group">
			<label>Fecha</label>
			<input type="text" id="qr-date" class="form-control" readonly value="<?php echo date('d/m/Y'); ?>">
		</div>
	</div>

	<div class="form-group">
		<input type="search" id="qr-search" class="form-control input-lg" placeholder="Buscar producto por nombre o código..." style="height:48px;">
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
						render($.parseJSON(d2).products || []);
					});
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

		var $line = $('#qr-lines .qr-card[data-id="' + p.id_product + '"]');

		if( $line.length ){
			$line.find('.qr-qty').focus().select();
		}
		else{
			addLine(p);
		}

		$('#qr-results').empty();
		$('#qr-search').val('');
	});

	function addLine(p){

		var $c = $(
			'<div class="qr-card" data-id="' + parseInt(p.id_product, 10) + '">' +
				'<span class="qr-remove"><i class="fa fa-times"></i></span>' +
				'<div class="qr-name">' + esc(p.name) + '</div>' +
				'<div class="row">' +
					'<div class="col-xs-6 qr-pack-wrap" style="display:none;"><label>Presentación</label><select class="form-control qr-pack"><option value="" data-units="1">Unidad</option></select></div>' +
					'<div class="col-xs-6"><label>Cantidad</label><input type="number" inputmode="numeric" min="1" step="1" class="form-control qr-qty" value="1"></div>' +
					'<div class="col-xs-6"><label>Costo por unidad ($)</label><input type="text" inputmode="decimal" class="form-control qr-cost" value="' + esc(p.price_cost) + '"></div>' +
					'<div class="col-xs-6"><label>Precio venta ($)</label><input type="text" inputmode="decimal" class="form-control qr-sell" value="' + esc(p.price_sell) + '"></div>' +
				'</div>' +
				'<div class="text-right text-muted qr-units" style="margin-top:4px;"></div>' +
			'</div>'
		);

		$('#qr-lines').prepend($c);

		$.getJSON('<?php echo base_url('products/packs'); ?>/' + parseInt(p.id_product, 10), function(data){

			if( data.packs && data.packs.length ){

				var $sel = $c.find('.qr-pack');

				$.each(data.packs, function(i, pk){
					$('<option>').val(pk.id_pack).attr('data-units', pk.units).attr('data-cost', pk.price_cost === null ? '' : pk.price_cost)
						.text(pk.name + ' (x' + pk.units + ')').appendTo($sel);
				});

				$c.find('.qr-pack-wrap').show();
			}
		});

		$c.find('.qr-qty').focus().select();
		recalc();
	}

	function units($c){ return parseInt($c.find('.qr-pack option:selected').attr('data-units'), 10) || 1; }

	function num(v){ var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }

	function recalc(){

		var total = 0;

		$('#qr-lines .qr-card').each(function(){

			var $c = $(this);
			var u = units($c) * (parseInt($c.find('.qr-qty').val(), 10) || 0);
			total += u * num($c.find('.qr-cost').val());
			$c.find('.qr-units').text(u + ' unidades');
		});

		$('#qr-total').text('Total: $' + total.toFixed(2));
	}

	$(document).on('input', '.qr-qty, .qr-cost, .qr-sell', recalc);

	$(document).on('change', '.qr-pack', function(){

		var $c = $(this).closest('.qr-card');
		var opt = $(this).find('option:selected');
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

		if( bad ){ notify('La cantidad debe ser un entero mayor a cero', 'error'); return; }

		var $btn = $(this).prop('disabled', true);

		$.ajax({
			url : '<?php echo base_url('invoices/register'); ?>',
			type : 'post',
			dataType : 'json',
			data : post,
			success : function(data){

				notify(data.message, data.type);

				if( data.tk ){ $('#qr-tk').val(data.tk); }

				if( data.type == 'success' ){
					$('#qr-lines').empty();
					recalc();
					$('#qr-invoice-no').val('REC-' + new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14));
				}

				$btn.prop('disabled', false);
			},
			error : function(){ notify('Ha ocurrido un error guardando la recepción', 'error'); $btn.prop('disabled', false); }
		});
	});

})();
</script>
