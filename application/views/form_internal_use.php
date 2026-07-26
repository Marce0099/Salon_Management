<h4 class="section-title">Formulario de uso interno</h4>

<form action="<?php echo base_url('products/internalUse'); ?>" method="post" class="form-horizontal" autocomplete="off" id="form">
	<div class="row">
		<div class="col-md-12">
			<div class="form-group">
				<label for="reason">Motivo <span class="text-danger">*</span></label>
				<textarea name="reason" id="reason" class="form-control" rows="2" required="true" placeholder="Describa el motivo del uso interno (ej. exhibición, producto dañado, uso del negocio)..."></textarea>
			</div>
		</div>
	</div>
	<table class="table table-bordered" id="products-table" style="table-layout: fixed;">
		<thead>
			<tr>
				<th width="4%"></th>
				<th width="18%">Código de barra</th>
				<th width="48%">Descripción</th>
				<th width="15%">Stock disponible</th>
				<th width="15%">Cantidad a usar</th>
			</tr>
		</thead>
		<tbody id="products-list">
		</tbody>
	</table>
	<div class="form-group">
		<a href="javascript:;" class="btn btn-primary" id="add-entry"><i class="fa fa-plus"></i> Agregar producto</a>
		<button class="btn btn-success" id="btn-submit" type="submit">Registrar uso interno</button>
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
			<input type="text" name="quantities[]" class="p-q-use text-center" required="true" data-parsley-type="integer" data-parsley-min="1" data-parsley-max="">
		</td>
	</tr>
</table>

<style>
	#prod-picker-results tr.picker-selected td { background-color: #337ab7 !important; color: #fff; }
</style>

<!-- Product picker modal -->
<div class="modal fade" id="product-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Producto</h4>
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

	// ── Product picker ────────────────────────────────────────────────────────

	var _prodPickerRow      = null;
	var _prodPickerSelected = null;
	var _prodPickerSuccess  = false;
	var _prodPickerClosed   = false;
	var _prodPickerTimer    = null;

	$('#add-entry').on('click', function(){

		var row = $('#row-mdl').clone().removeAttr('id');
		$('#products-list').append(row);
		row.find('.p-barcode').attr('id', 'index'+ri).focus();
		ri++;

		window.scrollTo(0, document.body.scrollHeight);
	});

	$(document).on('focusin', '.p-barcode', function(){

		if( _prodPickerClosed ){ _prodPickerClosed = false; return; }

		_prodPickerRow      = $(this).closest('tr');
		_prodPickerSelected = null;
		_prodPickerSuccess  = false;
		$('#prod-picker-ok').prop('disabled', true);
		$('#prod-picker-search').val('');
		$('#product-picker-modal').modal('show');

		$('#product-picker-modal').one('shown.bs.modal', function(){
			$('#prod-picker-search').focus();
			prodPickerLoad();
		});
	});

	$('#product-picker-modal').on('hidden.bs.modal', function(){

		if( _prodPickerSuccess ){
			_prodPickerSuccess = false;
			if( _prodPickerRow ) _prodPickerRow.find('.p-q-use').focus();
		}
		else{
			_prodPickerClosed = true;
			if( _prodPickerRow ) _prodPickerRow.find('.p-barcode').focus();
		}
	});

	function prodPickerLoad(){

		$.ajax({
			url: '<?php echo base_url('products/searchForInternalUse'); ?>',
			type: 'post', dataType: 'json',
			data: { search: $('#prod-picker-search').val() },
			success: function(data){
				prodPickerRender(data.products || []);
			},
			error: function(){
				$('<tr><td colspan="3" class="text-center text-danger">Error al cargar productos</td></tr>')
					.appendTo($('#prod-picker-results').empty());
			}
		});
	}

	function prodPickerRender( list ){

		var $tbody = $('#prod-picker-results').empty();
		_prodPickerSelected = null;
		$('#prod-picker-ok').prop('disabled', true);

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
		_prodPickerTimer = setTimeout(prodPickerLoad, 300);
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
		_prodPickerRow.find('.p-q-use').attr('data-parsley-max', p.stock);
		_prodPickerSuccess = true;
		$('#product-picker-modal').modal('hide');
	});

	// ── Delete row ────────────────────────────────────────────────────────────

	$(document).on('click', '.delete-item', function(){
		$(this).parents('tr').remove();
	});

	// ── Submit validation + PNotify confirm ───────────────────────────────────

	var submit = false;

	$(function(){

		$('#form').parsley().on('form:submit', function(){

			if( submit ) return true;

			if( $('#form .row-product').length == 0 ){
				new PNotify({
					title: 'Notificación',
					text: 'No ha ingresado ningún producto',
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
				text: '¿Confirma registrar este uso interno?',
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
