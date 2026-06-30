<?php

	if( isset($invoice) ){

		$post_url = base_url("invoices/edit/{$invoice->id_invoice}");
	}
	else{

		$post_url = base_url("invoices/register");
		$invoice = NULL;
	}
?>

<h4 class="section-title">Formulario de facturas</h4>

<form id="form" action="" method="post" class="form-horizontal" autocomplete="off" spellcheck="false" data-parsley-validate="">
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="">No. de factura</label>
				<input type="text" name="invoice_no" id="invoice_no" class="form-control" required="true" value="<?php if($invoice)echo $invoice->invoice_no; ?>">
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="">Proveedor</label>
				<select name="id_provider" id="id_provider" class="form-control">
					<?php foreach($providers as $p) : ?>
					<option value="<?php echo $p->id_provider; ?>"><?php echo $p->provider; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="">Fecha</label>
				<input type="text" name="date_time" id="date_time" class="dtpicker form-control" required="true" value="<?php if($invoice)echo date('d/m/Y',strtotime($invoice->date_time)); ?>">
			</div>
		</div>
	</div>
	<table class="table table-bordered jambo_table table-hover" id="invoice-table">
		<thead>
			<tr>
				<th width="2%"></th>
				<th width="52%">Producto/Servicio</th>
				<th width="10%">Cantidad</th>
				<th width="13%">Precio costo ($)</th>
				<th width="13%">Precio venta ($)</th>
				<th width="10%">Valor costo</th>
			</tr>
		</thead>
		<tbody id="products-list">
		</tbody>
		<tfoot style="background: #eceff3;">
			<td colspan="6" class="text-right total-price">
				<strong>Total: $<span id="total"><?php if($invoice)echo number_format($total,2); ?></span></strong>
			</td>
		</tfoot>
	</table>
	<div class="form-group">
		<a href="javascript:;" class="btn btn-primary" id="add-entry"><i class="fa fa-plus"></i> Agregar producto</a>
		<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		<img style="display: none;" src="<?php echo base_url('assets/img/loader.gif'); ?>" alt="" id="loader">
	</div>
</form>

<table class="hidden">
	<tr id="row-mdl" class="row-product">
		<td class="text-center">
			<span class="delete-item text-danger text-center">
				<i class="fa fa-times"></i>
			</span>
		</td>
		<td>
			<input type="text" class="p-barcode" placeholder="Producto/Servicio" required="true">
			<input type="hidden" class="p-id-products" name="id_products[]" required="true">
		</td>
		<td>
			<input type="text" name="quantities[]" class="p-quantity text-center" placeholder="Cantidad" required="true" data-parsley-type="integer" onkeyup="updateTotal($(this).parents('tr'));">
		</td>
		<td>
			<input type="text" name="prices_cost[]" class="p-price-cost text-center" placeholder="Precio costo" required="true" data-parsley-type="number" onkeyup="updateTotal($(this).parents('tr'));" >
		</td>
		<td>
			<input type="text" name="prices_sell[]" class="p-price-sell text-center" placeholder="Precio venta" required="true" data-parsley-type="number" onkeyup="updateTotal($(this).parents('tr'));" >
		</td>
		<td class="subtotal"></td>
	</tr>
</table>

<style>
	#picker-results tr.picker-selected td { background-color: #337ab7 !important; color: #fff; }
</style>

<div class="modal fade" id="product-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">

			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Producto/Servicio</h4>
			</div>

			<div class="modal-body">
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-5">
						<input type="text" id="picker-barcode" class="form-control" placeholder="Buscar por código de barra">
					</div>
					<div class="col-sm-5">
						<input type="text" id="picker-name" class="form-control" placeholder="Buscar por nombre">
					</div>
				</div>
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-12">
						<label style="margin-right:15px;font-weight:normal;">
							<input type="radio" name="picker-type" value="all" checked> Todos
						</label>
						<label style="margin-right:15px;font-weight:normal;">
							<input type="radio" name="picker-type" value="products"> Productos
						</label>
						<label style="font-weight:normal;">
							<input type="radio" name="picker-type" value="services"> Servicios
						</label>
					</div>
				</div>
				<div style="max-height:350px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>Código</th>
								<th>Nombre</th>
								<th>Precio costo</th>
								<th>Precio venta</th>
								<th>Tipo</th>
							</tr>
						</thead>
						<tbody id="picker-results"></tbody>
					</table>
				</div>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="picker-ok" disabled>OK</button>
			</div>

		</div>
	</div>
</div>

<script>

	$('#add-entry').on('click',function(){

		var row = $('#row-mdl').clone().removeAttr('id');
		$('#products-list').append(row);
		row.find('.p-barcode').focus();

		updateTotal(row);
		window.scrollTo(0,document.body.scrollHeight);
	});

	// ----- Product picker modal -----

	var _pickerRow         = null;
	var _pickerSelected    = null;
	var _pickerJustClosed  = false;
	var _pickerSuccess     = false;
	var _pickerTimer       = null;

	// Open modal on focus of any barcode/product field
	$(document).on('focusin', '.p-barcode', function(){

		if( _pickerJustClosed ){
			_pickerJustClosed = false;
			return;
		}

		_pickerRow     = $(this).closest('tr');
		_pickerSelected = null;
		_pickerSuccess  = false;

		$('#picker-ok').prop('disabled', true);
		$('#picker-barcode').val('');
		$('#picker-name').val('');
		$('input[name="picker-type"][value="all"]').prop('checked', true);

		$('#product-picker-modal').modal('show');

		$('#product-picker-modal').one('shown.bs.modal', function(){
			$('#picker-barcode').focus();
			pickerLoad();
		});
	});

	// After modal hides: OK → move to quantity; Cancel → return focus with guard
	$('#product-picker-modal').on('hidden.bs.modal', function(){

		if( _pickerSuccess ){
			_pickerSuccess = false;
			if( _pickerRow ) _pickerRow.find('.p-quantity').focus();
		}
		else{
			_pickerJustClosed = true;
			if( _pickerRow ) _pickerRow.find('.p-barcode').focus();
		}
	});

	function pickerLoad(){

		var barcode = $('#picker-barcode').val();
		var name    = $('#picker-name').val();
		var type    = $('input[name="picker-type"]:checked').val() || 'all';

		$.ajax({
			url      : '<?php echo base_url('products/searchProducts'); ?>',
			type     : 'post',
			dataType : 'json',
			data     : { barcode: barcode, name: name, type: type },
			success  : function(data){

				var $tbody = $('#picker-results').empty();
				_pickerSelected = null;
				$('#picker-ok').prop('disabled', true);

				if( data.products && data.products.length ){

					$.each(data.products, function(i, p){
						$('<tr>').data('product', p)
							.append($('<td>').text(p.barcode || ''))
							.append($('<td>').text(p.name))
							.append($('<td>').text(p.price_cost))
							.append($('<td>').text(p.price_sell))
							.append($('<td>').text(p.is_service == 1 ? 'Servicio' : 'Producto'))
							.appendTo($tbody);
					});
				}
				else{
					$('<tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>').appendTo($tbody);
				}
			},
			error : function(){
				$('<tr><td colspan="5" class="text-center text-danger">Error al buscar productos</td></tr>')
					.appendTo($('#picker-results').empty());
			}
		});
	}

	// Debounced search on text input
	$(document).on('input', '#picker-barcode, #picker-name', function(){
		clearTimeout(_pickerTimer);
		_pickerTimer = setTimeout(pickerLoad, 300);
	});

	// Immediate reload on radio change
	$(document).on('change', 'input[name="picker-type"]', pickerLoad);

	// Row highlight on click
	$(document).on('click', '#picker-results tr', function(){
		var p = $(this).data('product');
		if( !p ) return;
		$('#picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_pickerSelected = p;
		$('#picker-ok').prop('disabled', false);
	});

	// OK: fetch full product via existing endpoint, fill row same as old Tab handler
	$('#picker-ok').on('click', function(){

		if( !_pickerSelected || !_pickerRow ) return;

		// Products with no barcode (edge case): populate directly from picker data
		if( !_pickerSelected.barcode ){
			_pickerRow.find('.p-barcode').val(_pickerSelected.name);
			_pickerRow.find('.p-price-sell').val(_pickerSelected.price_sell);
			_pickerRow.find('.p-price-cost').val(_pickerSelected.price_cost);
			_pickerRow.find('.p-id-products').val(_pickerSelected.id_product);
			updateTotal(_pickerRow);
			_pickerSuccess = true;
			$('#product-picker-modal').modal('hide');
			return;
		}

		$.ajax({
			url      : '<?php echo base_url('products/getByBarCode'); ?>',
			type     : 'post',
			dataType : 'json',
			data     : { barcode: _pickerSelected.barcode },
			success  : function(data){

				if( data.product ){
					_pickerRow.find('.p-barcode').val(data.product.barcode + ': ' + data.product.name);
					_pickerRow.find('.p-price-sell').val(data.product.price_sell);
					_pickerRow.find('.p-price-cost').val(data.product.price_cost);
					_pickerRow.find('.p-id-products').val(data.product.id_product);
					updateTotal(_pickerRow);
					_pickerSuccess = true;
					$('#product-picker-modal').modal('hide');
				}
				else{
					new PNotify({
						title   : 'Notificación',
						text    : 'No se ha encontrado el producto',
						type    : 'warning',
						styling : 'bootstrap3',
						delay   : 2000
					});
				}
			},
			error : function(){
				new PNotify({
					title   : 'Notificación',
					text    : 'Ha ocurrido un error obteniendo los datos',
					type    : 'error',
					styling : 'bootstrap3',
					delay   : 3500
				});
			}
		});
	});

	$(document).on( 'click', '.delete-item',function(e){

		$(this).parents('tr').remove();
	});

	$(function () {
	  $('#form').parsley()
	  .on('form:submit', function() {
	  		
	  		if( $('#form .row-product').length == 0 ){

				new PNotify({
		            title: 'Notificación',
		            text: 'No ha ingresado ningun producto de la factura',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 2000
		        });

		        return false;
			}

			var ok = true;

			$('#form .p-id-products').each(function(index,element){

				if( $(element).val() == '' ){

					new PNotify({
			            title: 'Notificación',
			            text: 'Uno o varios productos posee un codigo de barra invalido',
			            type : 'error',
			            styling : 'bootstrap3',
			            delay : 2700
			        });

			        ok = false;

			        return false;
				}
			});

			if( !ok )
				return false;

			(new PNotify({
			    title: 'Confirmación',
			    text: 'Confirma guardar la factura?',
			    icon: 'glyphicon glyphicon-question-sign',
			    hide: false,
			    confirm: {
			        confirm: true,
			        buttons : [
			        {text: "OK", addClass : 'btn-default'},
			        {text: 'Cancelar', addClass: 'btn-default'}
			        ]
			    },
			    buttons: {
			        closer: true,
			        sticker: false,
			        closer_hover : false,
			    },
			    history: {
			        history: false
			    },
			    animate: {
			        animate: true,
			        in_class: 'bounceInDown',
			        out_class: 'bounceOutUp'
			    },
			    addclass: 'stack-modal',
			    stack: {
			        'dir1': 'down',
			        'dir2': 'right',
			        'modal': true
			    }
			})).get().on('pnotify.confirm', function() {
			    
				var post = $('#form').serialize();
				var btn = $('#btn-submit');
				var loader = $('#loader');

				btn.attr('disabled','true');
	  			loader.show();

				$.ajax({

					url : '<?php echo $post_url; ?>',
					type : 'post',
					dataType : 'json',
					data : post,
					success : function(data){

						new PNotify({
				            title: 'Notificación',
				            text: data.message,
				            type : data.type,
				            styling : 'bootstrap3',
				            delay : 2000
				        });

						<?php if( !$invoice ) : ?>
						if( data.type == 'success' ){

							$('#invoice_no').val('');
							$('#id_provider option').eq(0).attr('selected',true);
							$('#date_time').val('<?php echo date('d/m/Y'); ?>');

							$('#form .row-product').remove();
							$('#total').html('');
						}
						<?php endif; ?>

						btn.removeAttr('disabled');
						loader.hide();
					},
					error : function(){

						new PNotify({
				            title: 'Notificación',
				            text: 'Ha ocurrido un error obteniendo los datos',
				            type : 'error',
				            styling : 'bootstrap3',
				            delay : 3500
				        });

				        btn.removeAttr('disabled');
				        loader.hide();
					}

				});
			});

			return false;
	  });
	});

	function updateTotal(row){

		var p = numeral( row.find('.p-price-cost').val() ).value();
		var q = numeral( row.find('.p-quantity').val() ).value();
		row.find('.subtotal').html( numeral(p*q).format('0,0.00') );
		var subtotales = 0;

		$('.subtotal').each(function(index,elem){

			subtotales += numeral( $(elem).html() ).value();
		});

		$('#total').html( numeral( subtotales ).format('0,0.00') );
	}

	$(document).on('ready',function(){

		$('.dtpicker').daterangepicker({
			locale : {
				 'format': 'DD/MM/YYYY',
				 "daysOfWeek": [
		            "Do",
		            "Lu",
		            "Ma",
		            "Mi",
		            "Ju",
		            "Vi",
		            "Sa"
		        ],
		        "monthNames": [
		            "Enero",
		            "Febrero",
		            "Marzo",
		            "Abril",
		            "Mayo",
		            "Junio",
		            "Julio",
		            "Agosto",
		            "Septiembre",
		            "Octubre",
		            "Noviembre",
		            "Diciembre"
		        ]
			},
			maxDate : '<?php echo date('d/m/Y'); ?>',
	        singleDatePicker: true,
	        showDropdowns: true
	    });
	});

	$('.delete-checkbox').on('ifChanged', function(event){
	  	
	  	var row = $(this).parents('tr');
	  	row.find('td').removeClass('gray');

	  	if( $(this).prop('checked') ){

	  		row.find('td, input').addClass('gray');
	  	}
	  	else{

	  		row.find('td, input').removeClass('gray');
	  	}
	});

	$(document).on('focusin','#products-list .p-price-cost',function(){

		var curr_value = numeral( $(this).val() ).value();

		if( curr_value )
			$(this).attr('prev-value',curr_value);
	});

	$(document).on('change','#products-list .p-price-cost',function(){

		var new_cost = numeral($(this).val()).value();

		if( new_cost ){

			var prev_value = numeral( $(this).attr('prev-value') );
			var price_sell = $(this).parents('tr').find('.p-price-sell');
			var diff = numeral( price_sell.val() ).value() - prev_value.value();
			price_sell.val( new_cost + diff );
		}
		
	});

</script>