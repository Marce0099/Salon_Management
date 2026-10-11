<h4 class="section-title">Formulario de devoluciones</h4>

<form action="<?php echo base_url('returns/register'); ?>" method="post" class="form-horizontal" autocomplete="off" id="form">
	<input type="hidden" name="tk" value="<?php echo isset($tk) ? $tk : ''; ?>">
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="id_provider">Proveedor <span class="text-danger">*</span></label>
				<select name="id_provider" id="id_provider" class="form-control" required="true">
					<option value="">--- Seleccione proveedor ---</option>
					<?php foreach( $providers as $p ) : ?>
					<option value="<?php echo $p->id_provider; ?>"><?php echo $p->provider; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				<label for="invoice_no_display">No. de Factura <span class="text-danger">*</span></label>
				<input type="text" id="invoice_no_display" class="form-control" readonly
				       placeholder="Seleccione una factura..." style="background:#fff;cursor:pointer;">
				<input type="hidden" name="id_invoice" id="id_invoice">
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				<label for="date_time">Fecha</label>
				<input type="text" name="date_time" id="date_time" class="dtpicker form-control" required="true">
			</div>
		</div>
	</div>
	<table class="table table-bordered" id="invoice-table" style="table-layout: fixed;">
		<thead>
			<tr>
				<th width="4%"></th>
				<th width="18%">Código de barra</th>
				<th width="48%">Descripción</th>
				<th width="15%">Cantidad en stock</th>
				<th width="15%">Cantidad a devolver</th>
			</tr>
		</thead>
		<tbody id="products-list">
		</tbody>
	</table>
	<div class="form-group">
		<a href="javascript:;" class="btn btn-primary" id="add-entry"><i class="fa fa-plus"></i> Agregar producto</a>
		<button class="btn btn-success" id="btn-submit" type="submit">Registrar devolución</button>
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
			<input type="text" class="p-barcode" readonly style="cursor:pointer;" data-parsley-excluded="true">
			<input type="hidden" class="p-id-products" name="id_products[]" required="true">
		</td>
		<td>
			<span class="p-name"></span>
		</td>
		<td class="text-center">
			<span class="p-stock"></span>
		</td>
		<td>
			<input type="text" name="quantities_returns[]" class="p-q-return text-center" required="true" data-parsley-type="integer" data-parsley-min="1" data-parsley-max="">
		</td>
	</tr>
</table>

<style>
	#inv-picker-results tr.picker-selected td,
	#prod-picker-results tr.picker-selected td { background-color: #337ab7 !important; color: #fff; }
</style>

<!-- Invoice picker modal -->
<div class="modal fade" id="invoice-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Factura</h4>
			</div>
			<div class="modal-body">
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-8 col-sm-offset-2">
						<input type="text" id="inv-picker-search" class="form-control"
						       placeholder="Buscar por No. de factura">
					</div>
				</div>
				<div style="max-height:300px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr><th>No. Factura</th><th>Fecha</th></tr>
						</thead>
						<tbody id="inv-picker-results"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="inv-picker-ok" disabled>OK</button>
			</div>
		</div>
	</div>
</div>

<!-- Product picker modal -->
<div class="modal fade" id="product-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Producto/Servicio</h4>
			</div>
			<div class="modal-body">
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-8 col-sm-offset-2">
						<input type="text" id="prod-picker-search" class="form-control"
						       placeholder="Buscar por código o nombre">
					</div>
				</div>
				<div style="max-height:350px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr><th>Código</th><th>Nombre</th><th>Stock</th></tr>
						</thead>
						<tbody id="prod-picker-results"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="prod-picker-ok" disabled>OK</button>
			</div>
		</div>
	</div>
</div>

<script>

	var ri = 0;

	// ── Invoice picker ────────────────────────────────────────────────────────

	var _allInvoices       = [];
	var _invSelected       = null;
	var _invPickerSuccess  = false;
	var _invPickerClosed   = false;
	var _invPickerTimer    = null;

	$('#id_provider').on('change', function(){

		if( $(this).val() ){
			$(this).find('option[value=""]').prop('disabled', true);
		}

		$('#invoice_no_display').val('');
		$('#id_invoice').val('');
		$('#products-list').empty();
		_allInvoices = [];
		_invSelected = null;
	});

	$('#invoice_no_display').on('focusin', function(){

		if( _invPickerClosed ){ _invPickerClosed = false; return; }

		var id_provider = $('#id_provider').val();

		if( !id_provider ){
			new PNotify({
				title: 'Notificación',
				text: 'Debe seleccionar un proveedor primero',
				type: 'warning', styling: 'bootstrap3', delay: 3000
			});
			$(this).blur();
			return;
		}

		_invSelected      = null;
		_invPickerSuccess = false;
		$('#inv-picker-ok').prop('disabled', true);
		$('#inv-picker-search').val('');
		$('#inv-picker-results').empty();
		$('#invoice-picker-modal').modal('show');

		$('#invoice-picker-modal').one('shown.bs.modal', function(){
			$('#inv-picker-search').focus();
			invPickerLoad(id_provider);
		});
	});

	$('#invoice-picker-modal').on('hidden.bs.modal', function(){

		if( _invPickerSuccess ){
			_invPickerSuccess = false;
		}
		else{
			_invPickerClosed = true;
			$('#invoice_no_display').focus();
		}
	});

	function invPickerLoad( id_provider ){

		$('#inv-picker-results').html('<tr><td colspan="2" class="text-center text-muted">Cargando...</td></tr>');

		$.ajax({
			url: '<?php echo base_url('returns/getInvoicesByProvider'); ?>',
			type: 'post', dataType: 'json',
			data: { id_provider: id_provider },
			success: function(data){
				_allInvoices = data.invoices || [];
				invPickerRender($('#inv-picker-search').val());
			},
			error: function(){
				$('#inv-picker-results').html('<tr><td colspan="2" class="text-center text-danger">Error al cargar facturas</td></tr>');
			}
		});
	}

	function invPickerRender( search ){

		var $tbody = $('#inv-picker-results').empty();
		_invSelected = null;
		$('#inv-picker-ok').prop('disabled', true);

		var list = _allInvoices;
		if( search ){
			var s = search.toLowerCase();
			list = $.grep(list, function(inv){ return inv.invoice_no.toLowerCase().indexOf(s) >= 0; });
		}

		if( list.length ){
			$.each(list, function(_, inv){
				$('<tr>').data('invoice', inv)
					.append($('<td>').text(inv.invoice_no))
					.append($('<td>').text(inv.date_time.split(' ')[0]))
					.appendTo($tbody);
			});
		}
		else{
			$('<tr><td colspan="2" class="text-center text-muted">Sin resultados</td></tr>').appendTo($tbody);
		}
	}

	$(document).on('input', '#inv-picker-search', function(){
		clearTimeout(_invPickerTimer);
		_invPickerTimer = setTimeout(function(){ invPickerRender($('#inv-picker-search').val()); }, 300);
	});

	$(document).on('click', '#inv-picker-results tr', function(){
		var inv = $(this).data('invoice');
		if( !inv ) return;
		$('#inv-picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_invSelected = inv;
		$('#inv-picker-ok').prop('disabled', false);
	});

	$('#inv-picker-ok').on('click', function(){

		if( !_invSelected ) return;

		var prevId = $('#id_invoice').val();
		if( prevId && prevId != _invSelected.id_invoice ){
			$('#products-list').empty();
		}

		$('#invoice_no_display').val(_invSelected.invoice_no);
		$('#id_invoice').val(_invSelected.id_invoice);
		_invPickerSuccess = true;
		$('#invoice-picker-modal').modal('hide');
	});

	// ── Product picker ────────────────────────────────────────────────────────

	var _prodPickerRow      = null;
	var _prodPickerSelected = null;
	var _prodPickerSuccess  = false;
	var _prodPickerClosed   = false;
	var _prodPickerTimer    = null;
	var _prodPickerCache    = [];

	$('#add-entry').on('click', function(){

		var row = $('#row-mdl').clone().removeAttr('id');
		$('#products-list').append(row);
		row.find('.p-barcode').attr('id', 'index'+ri).focus();
		ri++;

		window.scrollTo(0, document.body.scrollHeight);
	});

	$(document).on('focusin', '.p-barcode', function(){

		if( _prodPickerClosed ){ _prodPickerClosed = false; return; }

		var id_invoice = $('#id_invoice').val();

		if( !id_invoice ){
			new PNotify({
				title: 'Notificación',
				text: 'Debe seleccionar un proveedor y una factura antes de agregar productos',
				type: 'warning', styling: 'bootstrap3', delay: 3500
			});
			$(this).blur();
			return;
		}

		_prodPickerRow      = $(this).closest('tr');
		_prodPickerSelected = null;
		_prodPickerSuccess  = false;
		$('#prod-picker-ok').prop('disabled', true);
		$('#prod-picker-search').val('');
		$('#product-picker-modal').modal('show');

		$('#product-picker-modal').one('shown.bs.modal', function(){
			$('#prod-picker-search').focus();
			prodPickerLoad(id_invoice);
		});
	});

	$('#product-picker-modal').on('hidden.bs.modal', function(){

		if( _prodPickerSuccess ){
			_prodPickerSuccess = false;
			if( _prodPickerRow ) _prodPickerRow.find('.p-q-return').focus();
		}
		else{
			_prodPickerClosed = true;
			if( _prodPickerRow ) _prodPickerRow.find('.p-barcode').focus();
		}
	});

	function prodPickerLoad( id_invoice ){

		$.ajax({
			url: '<?php echo base_url('returns/getProductsByInvoice'); ?>',
			type: 'post', dataType: 'json',
			data: { id_invoice: id_invoice },
			success: function(data){
				_prodPickerCache = data.products || [];
				prodPickerRender($('#prod-picker-search').val());
			},
			error: function(){
				$('<tr><td colspan="3" class="text-center text-danger">Error al cargar productos</td></tr>')
					.appendTo($('#prod-picker-results').empty());
			}
		});
	}

	function prodPickerRender( search ){

		var $tbody = $('#prod-picker-results').empty();
		_prodPickerSelected = null;
		$('#prod-picker-ok').prop('disabled', true);

		var list = _prodPickerCache;
		if( search ){
			var s = search.toLowerCase();
			list = $.grep(list, function(p){
				return (p.barcode && p.barcode.toLowerCase().indexOf(s) >= 0)
				    || p.name.toLowerCase().indexOf(s) >= 0;
			});
		}

		if( list.length ){
			$.each(list, function(_, p){
				$('<tr>').data('product', p)
					.append($('<td>').text(p.barcode || ''))
					.append($('<td>').text(p.name))
					.append($('<td>').text(p.stock))
					.appendTo($tbody);
			});
		}
		else{
			$('<tr><td colspan="3" class="text-center text-muted">Sin resultados</td></tr>').appendTo($tbody);
		}
	}

	$(document).on('input', '#prod-picker-search', function(){
		clearTimeout(_prodPickerTimer);
		_prodPickerTimer = setTimeout(function(){ prodPickerRender($('#prod-picker-search').val()); }, 200);
	});

	$(document).on('click', '#prod-picker-results tr', function(){
		var p = $(this).data('product');
		if( !p ) return;
		$('#prod-picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_prodPickerSelected = p;
		$('#prod-picker-ok').prop('disabled', false);
	});

	$('#prod-picker-ok').on('click', function(){

		if( !_prodPickerSelected || !_prodPickerRow ) return;

		var p = _prodPickerSelected;

		var alreadyIn = false;
		$('#products-list .p-id-products').each(function(){
			if( $(this).val() == p.id_product && $(this).closest('tr')[0] !== _prodPickerRow[0] ){
				alreadyIn = true;
				return false;
			}
		});

		if( alreadyIn ){
			new PNotify({
				title: 'Notificación',
				text: 'El producto ya está incluido en el listado',
				type: 'warning', styling: 'bootstrap3', delay: 2500
			});
			return;
		}

		_prodPickerRow.find('.p-barcode').val((p.barcode ?? '' ));
		_prodPickerRow.find('.p-id-products').val(p.id_product);
		_prodPickerRow.find('.p-name').html(p.name);
		_prodPickerRow.find('.p-stock').html(p.stock);
		_prodPickerRow.find('.p-q-return').attr('data-parsley-max', p.stock);
		_prodPickerSuccess = true;
		$('#product-picker-modal').modal('hide');
	});

	// ── Delete row ────────────────────────────────────────────────────────────

	$(document).on('click', '.delete-item', function(){
		$(this).parents('tr').remove();
	});

	// ── Date picker ───────────────────────────────────────────────────────────

	$(document).on('ready', function(){

		$('.dtpicker').daterangepicker({
			locale: {
				format: 'DD/MM/YYYY',
				daysOfWeek: ["Do","Lu","Ma","Mi","Ju","Vi","Sa"],
				monthNames: ["Enero","Febrero","Marzo","Abril","Mayo","Junio",
				             "Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"]
			},
			maxDate: '<?php echo date('d/m/Y'); ?>',
			singleDatePicker: true,
			showDropdowns: true
		});
	});

	// ── Submit validation + PNotify confirm ───────────────────────────────────

	var submit = false;

	$(function(){

		$('#form').parsley().on('form:submit', function(){

			if( submit ) return true;

			if( !$('#id_invoice').val() ){
				new PNotify({
					title: 'Notificación',
					text: 'Debe seleccionar una factura',
					type: 'error', styling: 'bootstrap3', delay: 2500
				});
				return false;
			}

			if( $('#form .row-product').length == 0 ){
				new PNotify({
					title: 'Notificación',
					text: 'No ha ingresado ningún producto de devolución',
					type: 'error', styling: 'bootstrap3', delay: 2000
				});
				return false;
			}

			var ok = true;
			$('#form .p-id-products').each(function(index, element){
				if( $(element).val() == '' ){
					new PNotify({
						title: 'Notificación',
						text: 'Uno o varios productos no ha sido seleccionado correctamente',
						type: 'error', styling: 'bootstrap3', delay: 2700
					});
					ok = false;
					return false;
				}
			});

			if( !ok ) return false;

			(new PNotify({
				title: 'Confirmación',
				text: '¿Confirma realizar la devolución?',
				icon: 'glyphicon glyphicon-question-sign',
				hide: false,
				confirm: {
					confirm: true,
					buttons: [
						{text: 'OK',       addClass: 'btn-default'},
						{text: 'Cancelar', addClass: 'btn-default'}
					]
				},
				buttons: { closer: true, sticker: false, closer_hover: false },
				history: { history: false },
				animate: { animate: true, in_class: 'bounceInDown', out_class: 'bounceOutUp' },
				addclass: 'stack-modal',
				stack: { dir1: 'down', dir2: 'right', modal: true }
			})).get().on('pnotify.confirm', function(){
				submit = true;
				$('#form').submit();
			});

			return false;
		});
	});

</script>
