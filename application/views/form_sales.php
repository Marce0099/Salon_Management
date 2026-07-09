<h4 class="section-title">Formulario de ventas</h4>

<form action="<?php echo base_url('sales/register'); ?>" method="post" class="form-horizontal form-label-left" autocomplete="off" spellcheck="false" id="form" onsubmit="return false;">
	<table class="table table-hover table-bordered" id="products-sale">
		<thead>
			<tr>
				<th></th>
				<th width="30%">Producto</th>
				<th>Precio</th>
				<th>Cantidad</th>
				<th><a class="btn btn-primary btn-xs" onclick="setGeneralDiscount();"><i class="fa fa-arrow-down"></i></a> Descuento</th>
				<th>Mi Producto</th>
				<th>Valor C$</th>
				<th>Valor $</th>
			</tr>
		</thead>
		<tbody id="sales-list">	
		</tbody>
		<tfoot>
			<tr>
				<td colspan="6" class="text-right" style="border: none;">Total:</td>
				<td>
					C$<span class="blue-2" id="total-cordobas"></span>
				</td>
				<td>
					$<span class="blue-2" id="total-dollar"></span>
				</td>
			</tr>
		</tfoot>
	</table>
	<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="barcode">Código del producto: 
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="barcode" id="barcode"  class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Cedula cliente:</label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text"  id="cedula_cliente" class="form-control col-md-7 col-xs-12">
			<input type="hidden" name="id_client" id="id_client">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Nombre cliente:</label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" disabled id="client_name" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Vendedor:</label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <select name="id_employee" id="id_employee" class="form-control">
            	<option value="">---</option>
            	<?php foreach( $employees as $e ) : ?>
				<option value="<?php echo $e->id_employee; ?>">
					<?php echo $e->id_employee . ' - ' . $e->name; ?>
				</option>
            	<?php endforeach; ?>
            </select>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Fecha:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-8">
            <input type="text" name="date_time" id="date_time" class="form-control dtpicker" required="true">
        </div>
	</div>
	<div class="form-group" style="margin-bottom: 20px;">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6" style="display: flex; align-items: center; flex-wrap: wrap; gap:10px;">
			<button type="button" class="btn btn-warning" id="add_payment" style="white-space: nowrap;">Agregar mas forma de pago</button>
			<span id="payment-due-badge" class="label label-warning" style="font-size: 100%; white-space: nowrap;"></span>
			<span id="payment-overpayment-badge" class="label label-info" style="font-size: 100%; white-space: nowrap; display: none;"></span>
			<span class="label label-info" style="font-size: 100%; white-space: nowrap;">Tasa venta:C$<?php echo $exchange->sell_price; ?></span>
			<span class="label label-info" style="font-size: 100%; white-space: nowrap;">Tasa compra:C$<?php echo $exchange->buy_price; ?></span>
		</div>
	</div>
	<div id="payment-blocks">
		<div class="payment_container" data-idx="0">

			<div class="form-group">

				<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Metodo de pago:</label>
				<div class="col-md-6 col-sm-6 col-xs-12">
					<select class="form-control payment-method-select" name="payment_method[]">
						<option value="cash">Efectivo</option>
						<option value="pos">POS</option>
						<option value="transfer">Transferencia</option>
						<option value="cupon">Cupón</option>
					</select>
				</div>

			</div>

			<div class="form-group hide payment-account-container">
				<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Numero de cuenta:</label>
				<div class="col-md-6 col-sm-6 col-xs-12">
					<select class="form-control payment-account-select" name="account_number[]">
						<?php  foreach($accounts as $account): ?>
							<option value="<?php echo $account->token_account ?>" data-type="<?php echo $account->type; ?>"><?php echo $account->name_account; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="form-group payment-currency-notice hide">
				<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6">
					<div class="payment-currency-notice-text"></div>
				</div>
			</div>

			<div class="form-group">
				<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Monto:</label>
				<div class="col-md-9 col-sm-9">
					<div class="row" style="margin-top: 8px;">
						<div class="col-md-2 payment-cash-fields">
							<label for="">Córdobas:</label>
							<input type="text" name="cordobas_input[]" class="form-control numeric-input cash-input cordobas-input" data-parsley-type="number">
						</div>
						<div class="col-md-2 payment-cash-fields">
							<label for="">Dólares:</label>
							<input type="text" name="dolar_input[]" class="form-control numeric-input cash-input dolar-input" data-parsley-type="number">
						</div>
						<div class="col-md-2">
							<label for="">Total C$</label>
							<input type="text" readonly="true" class="form-control payment-total-cordobas">
						</div>
					</div>
				</div>
			</div>

			<div class="form-group payment-cupon-fields hide">
				<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Cupón:</label>
				<div class="col-md-9 col-sm-9">
					<div class="row" style="margin-top: 8px;">
						<div class="col-md-2">
							<label for="">Código:</label>
							<input type="text" name="cupon_code[]" class="form-control cupon-code-input">
						</div>
						<div class="col-md-2">
							<label for="">Monto C$:</label>
							<input type="text" name="cupon_amount[]" class="form-control numeric-input cupon-amount-input" data-parsley-type="number">
						</div>
						<div class="col-md-2">
							<label for="">Desc. banco %:</label>
							<input type="text" name="cupon_discount_rate[]" class="form-control numeric-input cupon-discount-input" data-parsley-type="number">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!--<div class="form-group">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Efectivo:</label>
		<div class="col-md-9 col-sm-9">
			<div class="row" style="margin-top: 8px;">
				<div class="col-md-2">
					<label for="">Córdobas:</label>
					<input type="text" name="cash_cordobas" id="cash_cordobas_input" class="form-control numeric-input cash-input" data-parsley-type="number">
				</div>
				<div class="col-md-2">
					<label for="">Dólares:</label>
					<input type="text" name="cash_dollar" id="cash_dollar_input" class="form-control numeric-input cash-input" data-parsley-type="number">
				</div>
				<div class="col-md-2">
					<label for="">Total C$</label>
					<input type="text" id="cash_total_cordobas" readonly="true" class="form-control">
				</div>
			</div>
		</div>
	</div> .efectivo-->
	<!--<div class="form-group">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-3 col-sm-3">
            <div class="checkbox">
	        	<label style="padding-left: 0;">
	        		<input type="checkbox" class="flat flat-green" name="pos_paid" id="pos_paid" value="1"> Pago POS
	        	</label>
	        </div>
        </div>
	</div>-->
	<div class="form-group">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-primary" id="btn-submit">Registrar venta</button>
			<img style="display: none;" src="<?php echo base_url('assets/img/loader.gif'); ?>" alt="" id="loader">
		</div>
	</div>
</form>

<table class="hide" id="product-mdl">
	<tr class="product">
		<td><button class="btn btn-danger btn-xs btn-remove" onclick="updateTotalSubsctract($(this).parents('tr'),event)"><i class="fa fa-times"></i> Eliminar</button></td>
		<td class="p-name"></td>
		<td><input type="text" class="form-control p-price readonly" readonly="true" name="prices[]"></td>
		<td>
			<div class="tr-input">
				<input name="quantities[]" type="text" required="required" class="form-control p-quantity numeric-input" value="1" onkeyup="updateTotal($(this).parents('tr'))" data-parsley-type="integer">
	    	</div>
		</td>
		<td>
			<div class="tr-input">
				<input type="text" name="discounts[]" class="form-control has-feedback-left p-discount"  onkeyup="updateTotal($(this).parents('tr'))" data-parsley-type="integer">
	    		<span class="fa fa-percent form-control-feedback left"></span>
	    	</div>
	    </td>
		<td>
			<div>
				<select name="my_product[]" class="p-my_product form-control">
					<option value="0">No</option>
					<option value="1">Si</option>
				</select>
			</div>
		</td>
		<td>
			<span class="subtotal-cordobas"></span>
		</td>
		<td>
			<span class="subtotal-dollar"></span>
		</td>
		<input class="form-ids" type="hidden" name="id_products[]" value="">
		<input class="p-commission_rate" type="hidden" name="commission_rate[]" value="">
	</tr>
</table>

<div class="hide" id="payment-mdl">
	<div class="payment_container dynamic">
		<p title="Cerrar" class="dynamic_hide">X</p>

		<div class="form-group">

			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Metodo de pago:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control payment-method-select" name="payment_method[]">
					<option value="cash">Efectivo</option>
					<option value="pos">POS</option>
					<option value="transfer">Transferencia</option>
					<option value="cupon">Cupón</option>
				</select>
			</div>

		</div>

		<div class="form-group hide payment-account-container">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Numero de cuenta:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control payment-account-select" name="account_number[]">
					<?php  foreach($accounts as $account): ?>
						<option value="<?php echo $account->token_account ?>" data-type="<?php echo $account->type; ?>"><?php echo $account->name_account; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-group payment-currency-notice hide">
			<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6">
				<div class="payment-currency-notice-text"></div>
			</div>
		</div>

		<div class="form-group">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Monto:</label>
			<div class="col-md-9 col-sm-9">
				<div class="row" style="margin-top: 8px;">
					<div class="col-md-2 payment-cash-fields">
						<label for="">Córdobas:</label>
						<input type="text" name="cordobas_input[]" class="form-control numeric-input cash-input cordobas-input" data-parsley-type="number">
					</div>
					<div class="col-md-2 payment-cash-fields">
						<label for="">Dólares:</label>
						<input type="text" name="dolar_input[]" class="form-control numeric-input cash-input dolar-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Total C$</label>
						<input type="text" readonly="true" class="form-control payment-total-cordobas">
					</div>
				</div>
			</div>
		</div>

		<div class="form-group payment-cupon-fields hide">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Cupón:</label>
			<div class="col-md-9 col-sm-9">
				<div class="row" style="margin-top: 8px;">
					<div class="col-md-2">
						<label for="">Código:</label>
						<input type="text" name="cupon_code[]" class="form-control cupon-code-input">
					</div>
					<div class="col-md-2">
						<label for="">Monto C$:</label>
						<input type="text" name="cupon_amount[]" class="form-control numeric-input cupon-amount-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Desc. banco %:</label>
						<input type="text" name="cupon_discount_rate[]" class="form-control numeric-input cupon-discount-input" data-parsley-type="number">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
	#prod-picker-results tr.picker-selected td,
	#client-picker-results tr.picker-selected td { background-color: #337ab7 !important; color: #fff; }
</style>

<!-- Product picker modal (#barcode) -->
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
						<input type="text" id="prod-picker-barcode" class="form-control" placeholder="Buscar por código de barra">
					</div>
					<div class="col-sm-5">
						<input type="text" id="prod-picker-name" class="form-control" placeholder="Buscar por nombre">
					</div>
				</div>
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-12">
						<label style="margin-right:15px;font-weight:normal;">
							<input type="radio" name="prod-picker-type" value="all" checked> Todos
						</label>
						<label style="margin-right:15px;font-weight:normal;">
							<input type="radio" name="prod-picker-type" value="products"> Productos
						</label>
						<label style="font-weight:normal;">
							<input type="radio" name="prod-picker-type" value="services"> Servicios
						</label>
					</div>
				</div>
				<div style="max-height:350px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>Código</th>
								<th>Nombre</th>
								<th>Precio venta</th>
								<th>Tipo</th>
							</tr>
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

<!-- Client picker modal (#cedula_cliente) -->
<div class="modal fade" id="client-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">

			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Cliente</h4>
			</div>

			<div class="modal-body">
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-4">
						<input type="text" id="client-picker-id-card" class="form-control" placeholder="Buscar por cédula">
					</div>
					<div class="col-sm-4">
						<input type="text" id="client-picker-name" class="form-control" placeholder="Buscar por nombre">
					</div>
					<div class="col-sm-4">
						<input type="text" id="client-picker-surname" class="form-control" placeholder="Buscar por apellido">
					</div>
				</div>
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-6">
						<input type="text" id="client-picker-phone" class="form-control" placeholder="Buscar por teléfono">
					</div>
					<div class="col-sm-6">
						<input type="text" id="client-picker-email" class="form-control" placeholder="Buscar por correo">
					</div>
				</div>
				<div style="max-height:350px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>Cédula</th>
								<th>Nombre</th>
								<th>Apellido</th>
								<th>Teléfono</th>
								<th>Correo</th>
							</tr>
						</thead>
						<tbody id="client-picker-results"></tbody>
					</table>
				</div>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="client-picker-ok" disabled>OK</button>
			</div>

		</div>
	</div>
</div>

<script>

	var system = {
		total_invoice : 0,
		payments : {
			'0' : { method : 'cash', subtotal : 0, total : 0 }
		}
	};

	var _paymentCounter = 1; // idx 0 is the permanent block already in the markup

	var dollarBuyRate = <?php echo $exchange->buy_price; ?>;
	var dollarSellRate = <?php echo $exchange->sell_price; ?>;

	// ----- Product picker modal (#barcode) -----

	var _prodPickerSelected   = null;
	var _prodPickerJustClosed = false;
	var _prodPickerOpen       = false;
	var _prodPickerTimer      = null;

	function openProdPicker(){

		if( _prodPickerOpen ) return;
		_prodPickerOpen = true;

		_prodPickerSelected = null;

		$('#prod-picker-ok').prop('disabled', true);
		$('#prod-picker-barcode').val('');
		$('#prod-picker-name').val('');
		$('input[name="prod-picker-type"][value="all"]').prop('checked', true);

		$('#product-picker-modal').modal('show');

		$('#product-picker-modal').one('shown.bs.modal', function(){
			$('#prod-picker-barcode').focus();
			prodPickerLoad();
		});
	}

	// Focus opens the modal; a click while already focused (no new focus event) opens it too
	$(document).on('focusin click', '#barcode', function(){

		if( _prodPickerJustClosed ){
			_prodPickerJustClosed = false;
			return;
		}

		openProdPicker();
	});

	$('#product-picker-modal').on('hidden.bs.modal', function(){

		_prodPickerOpen = false;
		_prodPickerJustClosed = true;
		$('#barcode')[0].focus({ preventScroll: true });
	});

	function prodPickerLoad(){

		var barcode = $('#prod-picker-barcode').val();
		var name    = $('#prod-picker-name').val();
		var type    = $('input[name="prod-picker-type"]:checked').val() || 'all';

		$.ajax({
			url      : '<?php echo base_url('products/searchAvailableProducts'); ?>',
			type     : 'post',
			dataType : 'json',
			data     : { barcode: barcode, name: name, type: type },
			success  : function(data){

				var $tbody = $('#prod-picker-results').empty();
				_prodPickerSelected = null;
				$('#prod-picker-ok').prop('disabled', true);

				if( data.products && data.products.length ){

					$.each(data.products, function(i, p){
						$('<tr>').data('product', p)
							.append($('<td>').text(p.barcode || ''))
							.append($('<td>').text(p.name))
							.append($('<td>').text(p.price_sell))
							.append($('<td>').text(p.is_service == 1 ? 'Servicio' : 'Producto'))
							.appendTo($tbody);
					});
				}
				else{
					$('<tr><td colspan="4" class="text-center text-muted">Sin resultados</td></tr>').appendTo($tbody);
				}
			},
			error : function(){
				$('<tr><td colspan="4" class="text-center text-danger">Error al buscar productos</td></tr>')
					.appendTo($('#prod-picker-results').empty());
			}
		});
	}

	// Debounced search on text input
	$(document).on('input', '#prod-picker-barcode, #prod-picker-name', function(){
		clearTimeout(_prodPickerTimer);
		_prodPickerTimer = setTimeout(prodPickerLoad, 300);
	});

	// Immediate reload on radio change
	$(document).on('change', 'input[name="prod-picker-type"]', prodPickerLoad);

	// Row highlight on click
	$(document).on('click', '#prod-picker-results tr', function(){
		var p = $(this).data('product');
		if( !p ) return;
		$('#prod-picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_prodPickerSelected = p;
		$('#prod-picker-ok').prop('disabled', false);
	});

	// OK: populate a new #sales-list row directly from the picker's data
	$('#prod-picker-ok').on('click', function(){

		if( !_prodPickerSelected ) return;

		var p = _prodPickerSelected;

		var alreadyAdded = $('#sales-list .form-ids').filter(function(){
			return $(this).val() == p.id_product;
		}).length > 0;

		if( alreadyAdded ){

			new PNotify({
				title: 'Notificación',
				text: 'El producto ya se encuentra agregado en la venta',
				type : 'warning',
				styling : 'bootstrap3',
				delay : 2500
			});

			return;
		}

		var row = $('#product-mdl tr').eq(0).clone();

		if( p.is_service == '1' ){
			row.addClass('p-service');
		}
		else{
			row.find('.p-my_product').addClass('hide');
		}

		row.find('.p-name').html( p.barcode ? (p.barcode+' : '+p.name) : p.name );
		row.find('.p-price').val(p.price_sell);
		row.find('.form-ids').val(p.id_product);
		row.find('.p-commission_rate').val(p.commisssion_rate);

		$('#sales-list').append(row);
		$('#barcode').val('');

		updateTotal(row);

		$('#product-picker-modal').modal('hide');
	});

	// ----- Client picker modal (#cedula_cliente) -----

	var _clientPickerSelected   = null;
	var _clientPickerJustClosed = false;
	var _clientPickerTimer      = null;

	$(document).on('focusin', '#cedula_cliente', function(){

		if( _clientPickerJustClosed ){
			_clientPickerJustClosed = false;
			return;
		}

		_clientPickerSelected = null;

		$('#client-picker-ok').prop('disabled', true);
		$('#client-picker-id-card').val('');
		$('#client-picker-name').val('');
		$('#client-picker-surname').val('');
		$('#client-picker-phone').val('');
		$('#client-picker-email').val('');

		$('#client-picker-modal').modal('show');

		$('#client-picker-modal').one('shown.bs.modal', function(){
			$('#client-picker-id-card').focus();
			clientPickerLoad();
		});
	});

	$('#client-picker-modal').on('hidden.bs.modal', function(){

		_clientPickerJustClosed = true;
		$('#cedula_cliente').focus();
	});

	function clientPickerLoad(){

		var post = {
			id_card_client : $('#client-picker-id-card').val(),
			name_client    : $('#client-picker-name').val(),
			surname_client : $('#client-picker-surname').val(),
			phone_client   : $('#client-picker-phone').val(),
			email_client   : $('#client-picker-email').val()
		};

		$.ajax({
			url      : '<?php echo base_url('clients/searchClients'); ?>',
			type     : 'post',
			dataType : 'json',
			data     : post,
			success  : function(data){

				var $tbody = $('#client-picker-results').empty();
				_clientPickerSelected = null;
				$('#client-picker-ok').prop('disabled', true);

				if( data.clients && data.clients.length ){

					$.each(data.clients, function(i, c){
						$('<tr>').data('client', c)
							.append($('<td>').text(c.id_card_client || ''))
							.append($('<td>').text(c.name_client))
							.append($('<td>').text(c.surname_client))
							.append($('<td>').text(c.phone_client || ''))
							.append($('<td>').text(c.email_client || ''))
							.appendTo($tbody);
					});
				}
				else{
					$('<tr><td colspan="5" class="text-center text-muted">Sin resultados</td></tr>').appendTo($tbody);
				}
			},
			error : function(){
				$('<tr><td colspan="5" class="text-center text-danger">Error al buscar clientes</td></tr>')
					.appendTo($('#client-picker-results').empty());
			}
		});
	}

	// Debounced search on any filter input
	$(document).on('input', '#client-picker-id-card, #client-picker-name, #client-picker-surname, #client-picker-phone, #client-picker-email', function(){
		clearTimeout(_clientPickerTimer);
		_clientPickerTimer = setTimeout(clientPickerLoad, 300);
	});

	// Row highlight on click
	$(document).on('click', '#client-picker-results tr', function(){
		var c = $(this).data('client');
		if( !c ) return;
		$('#client-picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_clientPickerSelected = c;
		$('#client-picker-ok').prop('disabled', false);
	});

	// OK: populate client fields directly from the picker's data
	$('#client-picker-ok').on('click', function(){

		if( !_clientPickerSelected ) return;

		var c = _clientPickerSelected;

		$('#client_name').val(c.name_client+' '+c.surname_client);
		$('#id_client').val(c.id_client);
		$('#cedula_cliente').val(c.id_card_client || '');

		$('#client-picker-modal').modal('hide');
	});

	function updateTotal( row ){


		var price = numeral( row.find('.p-price').val() );
		var qt = numeral( row.find('.p-quantity').val() );
		//initial subtotal dollar
		var subtotal_dollar = numeral( price.value() * qt.value() );
		var subtotal_cordobas = 0;
		var discount = numeral( row.find('.p-discount').val() );
		var total_dollar = 0, total_cordobas = 0;

		//descuento
		if( discount.value() ){

			subtotal_dollar = numeral( subtotal_dollar.value() - ( subtotal_dollar.value() * discount.value() / 100 ) );
		}

		//subtotal dollar
		row.find('.subtotal-dollar').html( subtotal_dollar.format('0,0.00') );

		//dollar total
		$('#products-sale .subtotal-dollar').each(function(index,element){

			total_dollar += numeral( $(element).html() ).value();

		});

		total_dollar = numeral(total_dollar);
		$('#total-dollar').html(total_dollar.format('0,0.00'));

		//subtotal cordobas
		subtotal_cordobas = numeral( subtotal_dollar.value() * <?php echo $exchange->sell_price; ?> );

		row.find('.subtotal-cordobas').html( subtotal_cordobas.format('0,0.00') );

		//calc cordobas total
		$('#products-sale .subtotal-cordobas').each(function(index,element){

			total_cordobas += numeral( $(element).html() ).value();

		});

		total_cordobas = numeral(total_cordobas);
		system.total_invoice = total_cordobas.value();
		$('#total-cordobas').html(total_cordobas.format('0,0.00'));
		updateDueAmount();
	}

	function updateTotalSubsctract( row, event ){

		//weird
		if( event.screenX == 0 )
			return false;

		var subtotal_dollar = numeral( row.find('.subtotal-dollar').html() );
		var subtotal_cordobas = numeral( row.find('.subtotal-cordobas').html() );
		var total_dollar = numeral( $('#total-dollar').html() );
		var total_cordobas = numeral( $('#total-cordobas').html() );

		$('#total-dollar').html( numeral( total_dollar.value() - subtotal_dollar.value() ).format('0,0.00') );

		var tot_cord = numeral( total_cordobas.value() - subtotal_cordobas.value());
		system.total_invoice = tot_cord.value();
		$('#total-cordobas').html( tot_cord.format('0,0.00') );
		updateDueAmount();

		row.remove();
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
	        showDropdowns: true,
	        opens: "center",
    		drops: "up"
	    });
	});

	$('#form').parsley();

	$('#btn-submit').on('click',function(e){

		e.preventDefault();

		if( !$('#form').parsley().validate() )
			return false;

		if( !$('#id_client').val() ){

			new PNotify({
		            title: 'Notificación',
		            text: 'Debe seleccionar un cliente',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 2800
		        });

		        return false;
		}

		if( !$('#id_employee').val() ){

			new PNotify({
		            title: 'Notificación',
		            text: 'Debe seleccionar un vendedor',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 2800
		        });

		        return false;
		}

		if( $('#form .product').length == 0 ){

			new PNotify({
	            title: 'Notificación',
	            text: 'No ha ingresado ningun producto',
	            type : 'error',
	            styling : 'bootstrap3',
	            delay : 2800
	        });

	        return false;
		}

		var total = 0;
		$.each(system.payments, function(idx, p){ total += p.subtotal; });

		if( total < system.total_invoice ){
			new PNotify({
		            title: 'Notificación',
		            text: 'El efectivo entregado es menor que el total de la factura',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });

		    return false;
		}

		var couponError = null;
		var seenCoupons = {};
		var runningTotal = 0;

		$('#payment-blocks').children('.payment_container').each(function(){

			var $block  = $(this);
			var method  = $block.find('.payment-method-select').val();
			var amount  = system.payments[$block.data('idx')].subtotal;

			if( method === 'cupon' ){

				var code = $.trim( $block.find('.cupon-code-input').val() );

				if( !code || !amount ){
					couponError = 'Debe ingresar el código y el monto del cupón';
					return false;
				}

				if( seenCoupons[code] ){
					couponError = 'El cupón "' + code + '" ya fue usado en otro metodo de pago de esta venta';
					return false;
				}
				seenCoupons[code] = true;

				if( runningTotal + amount > system.total_invoice ){
					couponError = 'El cupón "' + code + '" excede el total pendiente de la factura';
					return false;
				}
			}

			runningTotal += amount;
		});

		if( couponError ){
			new PNotify({
		            title: 'Notificación',
		            text: couponError,
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });

		    return false;
		}

		/*if( !$('#pos_paid').is(':checked') ){

			var cash = numeral( $('#cash_total_cordobas').val() );
			var total = numeral( $('#total-cordobas').html() );

			if( total.value() > cash.value() ){

				new PNotify({
		            title: 'Notificación',
		            text: 'El efectivo entregado es menor que el total de la factura',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		        });

		        return false;
			}
		}*/

		var quantities = [];
		var id_products = [];

		$('#form .p-quantity').each( function(index,element){

			quantities.push( $(element).val() );
		});

		$('#form .form-ids').each( function(index,element){

			id_products.push( $(element).val() );
		});

		$.ajax({

			url : '<?php echo base_url('products/validateQuantities'); ?>',
			data : { id_products : id_products, quantities : quantities },
			type : 'post',
			dataType : 'json',
			success : function(data){

				if( data.message ){

					new PNotify({
			            title: 'Notificación',
			            text: data.message,
			            type : data.type,
			            styling : 'bootstrap3',
			            delay : 4000
			        });
				}
				
		        if( data.type == 'success' ){
		        	
		        	submitForm();
		        }
			},
			error : function(){

				new PNotify({
		            title: 'Notificación',
		            text: 'Ocurrio un error validando los datos',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		        });
			}
		});
	});

	function setGeneralDiscount(){

		var discount = $('#form .p-discount').eq(0);

		if( discount ){

			$('#form .p-discount').each(function(index,element){

				$(element).val(discount.val());
				updateTotal($(element).parents('tr'));
			});
		}
	}

	function submitForm(){

		var btn = $('#btn-submit');
		var loader = $('#loader');

		$('#payment-blocks').children('.payment_container').each(function(){

			var $block = $(this);
			var method = $block.find('.payment-method-select').val();

			if( method !== 'transfer' )
				$block.find('.payment-account-select').val('');

			if( method === 'cupon' ){
				$block.find('.cordobas-input, .dolar-input').val('');
			}else{
				$block.find('.cupon-code-input, .cupon-amount-input, .cupon-discount-input').val('');
			}
		});

	  	var post = $('#form').serializeArray();
	  	btn.attr('disabled','true');
	  	loader.show();

	  	$.ajax({

	  		url : '<?php echo base_url('sales/register'); ?>',
	  		data : post,
	  		type : 'post',
	  		dataType : 'json',
	  		success : function(data){

	  			new PNotify({
		            title: 'Notificación',
		            text: data.message,
		            type : data.type,
		            styling : 'bootstrap3',
		            delay : data.type == 'success' ? 10000 : 3500
		        });

		        if( data.type == 'success' ){

		        	$('#barcode').val('');
		        	$('#cedula_cliente').val('');
		        	$('#id_client').val('');
		        	$('#client_name').val('');
		        	$('#id_employee option:eq(0)').prop('selected',true);
		        	$('#form .product').remove();
		        	$('#total-cordobas').html('');
		        	$('#total-dollar').html('');

		        	$('#payment-blocks').children('.payment_container').not(':eq(0)').remove();
		        	$('#payment-blocks .payment_container').eq(0).find('.payment-method-select').val('cash').trigger('change');
		        	$('#payment-blocks .payment_container').eq(0).find('.cash-input, .cupon-code-input, .cupon-amount-input, .cupon-discount-input').val('');

		        	system.payments = { '0' : { method : 'cash', subtotal : 0, total : 0 } };
		        	_paymentCounter = 1;
		        	updateDueAmount();
		        }

		        btn.removeAttr('disabled');
		        loader.hide();
	  		},
	  		error : function(){

	  			new PNotify({
		            title: 'Notificación',
		            text: 'Ha ocurrido un error guardando los datos',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 3500
		        });

		        btn.removeAttr('disabled');
		        loader.hide();
	  		}
	  	});
	}

	function recalcAmountPayment($block){
		var idx = $block.data('idx');
		var method = $block.find('.payment-method-select').val();
		var subtotal, total;

		if( method === 'cupon' ){

			var amt = numeral( $block.find('.cupon-amount-input').val() || 0 );
			subtotal = amt.value();
			total = subtotal;
		}else{

			var cordobas = numeral( $block.find('.cordobas-input').val() || 0 );
			var dollar = numeral( ( $block.find('.dolar-input').val() * <?php echo $exchange->sell_price; ?> ) || 0 );

			if( dollar )
				cordobas.add( dollar.value() );

			subtotal = cordobas.value();

			if( method === "pos" )
				cordobas.add( cordobas.value() * (6.75/100)  );

			total = cordobas.value();
		}

		system.payments[idx] = { method : method, subtotal : subtotal, total : total };

		$block.find('.payment-total-cordobas').val( numeral(total).format('0,0.00') );
		updateDueAmount();
	}

	function checkCurrencyMismatch($block){
		var $select = $block.find('.payment-account-select');
		var accountType = $select.find('option:selected').data('type');
		var $cordoba = $block.find('.cordobas-input');
		var $dolar = $block.find('.dolar-input');
		var $notice = $block.find('.payment-currency-notice');

		$cordoba.removeClass('payment-currency-mismatch');
		$dolar.removeClass('payment-currency-mismatch');

		if( $block.find('.payment-method-select').val() !== 'transfer' || !accountType ){
			$notice.addClass('hide');
			return;
		}

		var cordobaVal = parseFloat( ($cordoba.val() || '0').replace(',', '') ) || 0;
		var dolarVal = parseFloat( ($dolar.val() || '0').replace(',', '') ) || 0;

		var message = '';

		if( accountType === 'dolares' && cordobaVal > 0 ){
			$cordoba.addClass('payment-currency-mismatch');
			message = 'Escribiendo monto en córdobas en una cuenta de dólares.';
		}else if( accountType === 'cordobas' && dolarVal > 0 ){
			$dolar.addClass('payment-currency-mismatch');
			message = 'Escribiendo monto en dólares en una cuenta de córdobas.';
		}

		if( message ){
			$notice.removeClass('hide').find('.payment-currency-notice-text').text(message);
		}else{
			$notice.addClass('hide');
		}
	}

	function hasCashPayment(excludeIdx){
		var found = false;
		$.each(system.payments, function(idx, p){
			if( idx != excludeIdx && p.method === 'cash' ) found = true;
		});
		return found;
	}

	function updateDueAmount(){
		var paid = 0;
		$.each(system.payments, function(idx, p){ paid += p.subtotal; });

		var due = numeral(system.total_invoice - paid);
		var $badge = $('#payment-due-badge');
		var $overpaymentBadge = $('#payment-overpayment-badge');

		if( system.total_invoice > 0 && due.value() > 0 ){
			var dueDollars = numeral(due.value() / dollarSellRate);
			$badge.removeClass('label-success').addClass('label-warning')
				.text('Pendiente: $' + dueDollars.format('0,0.00') + ' (C$' + due.format('0,0.00') + ')');
		}else if( system.total_invoice > 0 ){
			$badge.removeClass('label-warning').addClass('label-success')
				.text('Pagado');
		}else{
			$badge.text('');
		}

		if( system.total_invoice > 0 && due.value() < 0 ){
			var overpaidCordobas = due.value() * -1;
			var overpaidDollars = numeral(overpaidCordobas / dollarBuyRate);
			$overpaymentBadge.text('Vuelto: $' + overpaidDollars.format('0,0.00') + ' (C$' + numeral(overpaidCordobas).format('0,0.00') + ')').show();
		}else{
			$overpaymentBadge.hide().text('');
		}
	}

	$(document).on('keyup', '#payment-blocks .cash-input, #payment-blocks .cupon-amount-input', function(){
		recalcAmountPayment( $(this).closest('.payment_container') );
		checkCurrencyMismatch( $(this).closest('.payment_container') );
	});

	$(document).on('change', '.payment-account-select', function(){
		checkCurrencyMismatch( $(this).closest('.payment_container') );
	});

	/*$('.cash-input').on('keyup',function(){

		var cordobas = numeral( $('#cash_cordobas_input').val() || 0 );

		var dollar = numeral( Math.round( $('#cash_dollar_input').val() * <?php //echo $exchange->buy_price; ?> ) || 0 );

		if( dollar )
			cordobas.add( dollar.value() );
		
		$('#cash_total_cordobas').val( cordobas.format('0,0.00') );

		//$('#pos_paid').iCheck('uncheck');
	});*/

	/*$('#pos_paid').on('ifToggled',function(event){

		if( $(this).prop('checked') ){

			$('.cash-input').val('');
			$('#cash_total_cordobas').val('');
		}
	});*/

	$('#add_payment').on('click', function(event){

		var total = 0;
		$.each(system.payments, function(idx, p){ total += p.subtotal; });

		if( total >= system.total_invoice && system.total_invoice > 0 ){
			new PNotify({
		            title: 'Notificación',
		            text: 'El monto ingresado ya cubre el total de la factura, no es necesario agregar otro metodo de pago',
		            type : 'warning',
		            styling : 'bootstrap3',
		            delay : 4000
		    });
		    return;
		}

		var idx = _paymentCounter++;
		var $clone = $('#payment-mdl .payment_container').eq(0).clone();
		$clone.attr('data-idx', idx).removeClass('hide');

		if( hasCashPayment() ){
			$clone.find('.payment-method-select').val('pos');
			new PNotify({
		            title: 'Notificación',
		            text: 'Ya existe un método de pago en efectivo en esta venta; se seleccionó POS para el nuevo método',
		            type : 'info',
		            styling : 'bootstrap3',
		            delay : 4000
		    });
		}

		$('#payment-blocks').append($clone);

		system.payments[idx] = { method : $clone.find('.payment-method-select').val(), subtotal : 0, total : 0 };

		recalcAmountPayment($clone);
	});

	$(document).on('click', '.dynamic_hide', function(event){
		var $block = $(this).closest('.payment_container');

		delete system.payments[ $block.data('idx') ];
		$block.remove();
		updateDueAmount();
	});

	$(document).on('change', '.payment-method-select', function(event){
		var $block = $(this).closest('.payment_container');
		var method = $(this).val();

		if( method === 'cash' && hasCashPayment( $block.data('idx') ) ){
			new PNotify({
		            title: 'Notificación',
		            text: 'No se permite más de un método de pago en efectivo por venta',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });
			method = 'pos';
			$(this).val('pos');
		}

		$block.find('.payment-account-container').toggleClass('hide', method !== 'transfer');
		$block.find('.payment-cash-fields').toggleClass('hide', method === 'cupon');
		$block.find('.payment-cupon-fields').toggleClass('hide', method !== 'cupon');

		recalcAmountPayment($block);
		checkCurrencyMismatch($block);
	});

</script>