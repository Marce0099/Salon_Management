<h4 class="section-title">Formulario de ventas</h4>

<form action="<?php echo base_url('sales/register'); ?>" method="post" class="form-horizontal form-label-left" autocomplete="off" spellcheck="false" id="form" onsubmit="return false;">
	<input type="hidden" name="tk" value="<?php echo $tk; ?>">
	<input type="hidden" name="id_appointment" id="id_appointment" value="">
	<table class="table table-hover table-bordered" id="products-sale">
		<thead>
			<tr>
				<th>
					<input type="checkbox" id="manual-discount-checkbox" name="manual_discount" value="1"
						title="Al marcar esta casilla se desactivan los descuentos automáticos (VIP, cumpleaños, categoría) y podrá escribir el descuento manualmente en cada producto o servicio. No se podrá usar cupón mientras esté activa.">
				</th>
				<th width="30%">Producto</th>
				<th>Precio</th>
				<th>Cantidad</th>
				<th>Descuento</th>
				<th>Atendido por</th>
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
	<div class="form-group" id="loyalty-block" style="display:none;">
		<label class="control-label col-md-2 col-sm-3 col-xs-12">Puntos de fidelidad:</label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <span id="loyalty-balance-display" class="label label-info"></span>
            <div id="loyalty-claim-options" style="margin-top:5px;"></div>
        </div>
		<input type="hidden" name="claim_loyalty_tier" id="claim_loyalty_tier" value="0">
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
	<div class="form-group" style="margin-bottom: 10px;">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6">
			<button type="button" class="btn btn-warning" id="add_payment" style="white-space: nowrap;">Agregar mas forma de pago</button>
		</div>
	</div>
	<div class="form-group" style="margin-bottom: 10px;">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6" style="display: flex; align-items: center; flex-wrap: wrap; gap:10px;">
			<small class="text-muted" style="width:100%;">Estado de pago:</small>
			<span id="payment-due-badge" class="label label-warning" style="font-size: 100%; white-space: nowrap;"></span>
			<span id="payment-overpayment-badge" class="label label-info" style="font-size: 100%; white-space: nowrap; display: none;"></span>
		</div>
	</div>
	<div class="form-group" style="margin-bottom: 10px;">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6" style="display: flex; align-items: center; flex-wrap: wrap; gap:10px;">
			<small class="text-muted" style="width:100%;">Descuentos y créditos aplicados:</small>
			<span id="deposit-applied-badge" class="label label-success" style="font-size: 100%; white-space: nowrap; display: none;"></span>
			<span id="loyalty-applied-badge" class="label label-success" style="font-size: 100%; white-space: nowrap; display: none;"></span>
			<span id="birthday-ceiling-applied-badge" class="label label-success" style="font-size: 100%; white-space: nowrap; display: none;"></span>
		</div>
	</div>
	<div class="form-group" style="margin-bottom: 20px;">
		<div class="col-md-offset-2 col-sm-offset-3 col-md-6 col-sm-6" style="display: flex; align-items: center; flex-wrap: wrap; gap:10px;">
			<small class="text-muted" style="width:100%;">Tasas de cambio:</small>
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
							<input type="text" name="cupon_discount_rate[]" class="form-control numeric-input cupon-discount-input" data-parsley-type="number" data-parsley-min="0" data-parsley-max="100">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="form-group hide" id="change-given-section">
		<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Cambio a entregar:</label>
		<div class="col-md-9 col-sm-9">
			<div class="row" style="margin-top: 8px;">
				<div class="col-md-2">
					<label for="">Córdobas:</label>
					<input type="text" name="change_cordoba_given" id="change_cordoba_given" class="form-control numeric-input" data-parsley-type="number">
				</div>
				<div class="col-md-2">
					<label for="">Dólares:</label>
					<input type="text" name="change_dollar_given" id="change_dollar_given" class="form-control numeric-input" data-parsley-type="number">
				</div>
				<div class="col-md-3">
					<label for="">Vuelto:</label>
					<input type="text" readonly="true" class="form-control" id="change-owed-display">
				</div>
				<div class="col-md-3">
					<label for="">Restante:</label>
					<input type="text" readonly="true" class="form-control" id="change-remaining-display">
				</div>
			</div>
			<p class="help-block">El vuelto puede entregarse en cualquier combinación de córdobas y dólares. Solo se permite una diferencia de centavos entre el vuelto y lo entregado.</p>
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
				<input type="text" name="discounts[]" class="form-control has-feedback-left p-discount readonly" data-parsley-type="integer" data-parsley-min="0" data-parsley-max="100" readonly="true" onkeyup="if(_manualDiscountMode) updateTotal($(this).parents('tr'));">
	    		<span class="fa fa-percent form-control-feedback left"></span>
	    	</div>
	    </td>
		<td>
			<div class="tr-input p-atendido">
				<input type="text" class="form-control atendido-name" placeholder="Atendido por" readonly="true" style="cursor:pointer;background-color:#fff;">
				<input type="hidden" class="atendido-id" name="attended_employees[]" value="">
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
						<input type="text" name="cupon_discount_rate[]" class="form-control numeric-input cupon-discount-input" data-parsley-type="number" data-parsley-min="0" data-parsley-max="100">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style>
	#prod-picker-results tr.picker-selected td,
	#client-picker-results tr.picker-selected td,
	#atendido-picker-results tr.picker-selected td { background-color: #337ab7 !important; color: #fff; }
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

<!-- Atendido por (servicing employee) picker modal -->
<div class="modal fade" id="atendido-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">

			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Seleccionar Empleado</h4>
			</div>

			<div class="modal-body">
				<div class="row" style="margin-bottom:10px;">
					<div class="col-sm-12">
						<input type="text" id="atendido-picker-search" class="form-control" placeholder="Buscar por nombre">
					</div>
				</div>
				<div style="max-height:350px;overflow-y:auto;">
					<table class="table table-bordered table-hover table-condensed">
						<thead>
							<tr>
								<th>Empleado</th>
							</tr>
						</thead>
						<tbody id="atendido-picker-results"></tbody>
					</table>
				</div>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="atendido-picker-ok" disabled>OK</button>
			</div>

		</div>
	</div>
</div>

<script>

	var system = {
		total_invoice : 0,
		total_services : 0,
		// true when a coupon payment may be used: either no line discount is in play, or a coupon has
		// already out-valued it and neutralized it (see updateTotal()'s golden-rule resolution)
		coupon_allowed : true,
		discount_neutralized_by_coupon : false,
		_couponNeutralizeNotified : false,
		deposit_credit_cordobas : 0,
		loyalty_discount_cordobas : 0,
		// flat birthday-ceiling write-off currently in effect (0 if not applicable or neutralized by a
		// coupon) plus the rate it was computed at, purely for the "Descuento cumpleaños aplicado" badge —
		// see resolve_birthday_ceiling_writeoff() in discount_helper.php for the server-side source of truth
		birthday_ceiling_writeoff_cordobas : 0,
		birthday_ceiling_rate : 0,
		payments : {
			'0' : { method : 'cash', subtotal : 0, total : 0 }
		}
	};

	var _paymentCounter = 1; // idx 0 is the permanent block already in the markup

	// true while the manual-discount checkbox is checked: automated VIP/birthday/category discounts
	// and the coupon-vs-discount golden rule are both bypassed — every row's discount is whatever the
	// employee typed, and coupon payments are blocked outright (see updateTotal()'s manual branch below)
	var _manualDiscountMode = false;

	$(document).on('change', '#manual-discount-checkbox', function(){

		_manualDiscountMode = $(this).is(':checked');

		$('#sales-list .p-discount').each(function(){
			$(this).prop('readonly', !_manualDiscountMode)
				.toggleClass('readonly', !_manualDiscountMode)
				.val(_manualDiscountMode ? '0' : '');
		});

		$('.payment-method-select option[value="cupon"]').prop('disabled', _manualDiscountMode);

		updateTotal();
	});

	var dollarBuyRate = <?php echo $exchange->buy_price; ?>;
	var dollarSellRate = <?php echo $exchange->sell_price; ?>;

	// Payment-sufficiency tolerance (córdobas) — must mirror paymentSufficiencyToleranceCordobas()
	// in application/helpers/cash_helper.php exactly. See that function's comment for the full
	// rationale: a dollar-denominated payment amount typed back from the rounded "Pendiente"
	// display can miss the exact córdoba total by up to half a dollar-cent per dollar row.
	var PAYMENT_TOLERANCE_BASE_CORDOBA = 0.01;
	var PAYMENT_TOLERANCE_PER_DOLLAR_ROW = 0.005;
	var PAYMENT_TOLERANCE_MAX_CORDOBA = 0.99;

	function paymentSufficiencyTolerance(numDollarRows, sellRate){
		var raw = numDollarRows * PAYMENT_TOLERANCE_PER_DOLLAR_ROW * sellRate;
		return Math.max(PAYMENT_TOLERANCE_BASE_CORDOBA, Math.min(raw, PAYMENT_TOLERANCE_MAX_CORDOBA));
	}

	var appointmentPrefill = <?php echo json_encode($prefill); ?>;

	// VIP/birthday client discounts (application/settings, admin-configurable via /settings) — auto-fill
	// service line discounts alongside the existing category-default auto-fill (whichever is higher wins)
	var VIP_DISCOUNT_RATE = <?php echo (float) $vip_discount_rate; ?>;
	var BIRTHDAY_DISCOUNT_RATE = <?php echo (float) $birthday_discount_rate; ?>;
	// Ceiling gating the birthday discount only (VIP/category are unaffected): the running total of
	// undiscounted service lines, taken in the order they were added, must clear this minimum before any
	// line becomes birthday-eligible, and only lines whose running total stays within the maximum qualify
	var BIRTHDAY_CEILING_MIN = <?php echo (float) $birthday_discount_ceiling_min; ?>;
	var BIRTHDAY_CEILING_MAX = <?php echo (float) $birthday_discount_ceiling_max; ?>;
	var _selectedClientVip = false;
	var _selectedClientBirthday = false;

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

	// Builds and appends a #sales-list row from a product-shaped object (picker result or
	// appointment-continuation prefill); returns false if the product is already in the sale.
	function addProductRow(p){

		var alreadyAdded = $('#sales-list .form-ids').filter(function(){
			return $(this).val() == p.id_product;
		}).length > 0;

		if( alreadyAdded ){
			return false;
		}

		var row = $('#product-mdl tr').eq(0).clone();

		if( p.is_service == '1' || p.is_service == 1 ){
			row.addClass('p-service');
		}
		else{
			row.find('.p-atendido').addClass('hide');
		}

		row.find('.p-name').html( p.barcode ? (p.barcode+' : '+p.name) : p.name );
		row.find('.p-price').val(p.price_sell);
		row.find('.form-ids').val(p.id_product);
		row.find('.p-commission_rate').val(p.commisssion_rate);

		if( p.is_service == '1' || p.is_service == 1 ){

			var categoryRate = (p.category_discount_rate !== null && p.category_discount_rate !== undefined && p.category_discount_rate !== '') ? numeral(p.category_discount_rate).value() : 0;
			row.data('category-discount-rate', categoryRate);

			// defaults "Atendido por" to the currently-selected Vendedor; cashier only needs to
			// open the picker to override it for a line a different employee actually served
			var $vendedor = $('#id_employee option:selected');
			if( $vendedor.val() ){
				row.find('.atendido-name').val($vendedor.text().trim());
				row.find('.atendido-id').val($vendedor.val());
			}
		}

		if( _manualDiscountMode ){
			row.find('.p-discount').prop('readonly', false).removeClass('readonly').val('0');
		}

		$('#sales-list').append(row);

		// discount assignment (category/VIP/ceiling-gated birthday) and totals are resolved for every
		// row in one full pass, since birthday-ceiling eligibility depends on the whole set of service rows
		updateTotal(row);

		return row;
	}

	// Sums each service row's UNDISCOUNTED córdoba subtotal (price * qty * sell rate) in DOM order, then
	// decides which rows qualify for the birthday discount: none, if the client isn't a birthday client or
	// the total doesn't reach BIRTHDAY_CEILING_MIN; otherwise rows are walked in that same order and marked
	// eligible while the running total stays within BIRTHDAY_CEILING_MAX (both bounds inclusive) — the first
	// row that would push the running total past the max, and every row after it, is left ineligible.
	// Eligibility is stashed per-row via .data('birthday-eligible', bool) for updateTotal() to consume.
	function recalcBirthdayEligibility(){

		var rows = $('#sales-list tr.p-service');
		var undiscountedTotals = [];
		var servicesTotalUndiscounted = 0;

		rows.each(function(){
			var row = $(this);
			var price = numeral( row.find('.p-price').val() ).value();
			var qt = numeral( row.find('.p-quantity').val() ).value();
			var undiscounted = price * qt * dollarSellRate;
			undiscountedTotals.push(undiscounted);
			servicesTotalUndiscounted += undiscounted;
		});

		var applyCeiling = _selectedClientBirthday && servicesTotalUndiscounted >= BIRTHDAY_CEILING_MIN;
		var running = 0;
		var ceilingExceeded = false;

		rows.each(function(index){
			var row = $(this);
			var undiscounted = undiscountedTotals[index];
			var eligible = false;

			if( applyCeiling && !ceilingExceeded && ( running + undiscounted ) <= BIRTHDAY_CEILING_MAX ){
				eligible = true;
				running += undiscounted;
			}
			else if( applyCeiling ){
				ceilingExceeded = true;
			}

			row.data('birthday-eligible', eligible);
		});
	}

	// OK: populate a new #sales-list row directly from the picker's data
	$('#prod-picker-ok').on('click', function(){

		if( !_prodPickerSelected ) return;

		var added = addProductRow(_prodPickerSelected);

		if( added === false ){

			new PNotify({
				title: 'Notificación',
				text: 'El producto ya se encuentra agregado en la venta',
				type : 'warning',
				styling : 'bootstrap3',
				delay : 2500
			});

			return;
		}

		$('#barcode').val('');

		$('#product-picker-modal').modal('hide');
	});

	// Fetches the selected client's loyalty balance and, if they qualify for a reward, shows a single
	// "claim" checkbox for the maximum tier they're eligible for (claiming always applies the highest
	// qualifying tier — there is no picking a lower one). Called from both places a client gets set:
	// the picker's OK handler and the appointment-continuation prefill.
	function fetchLoyaltyBalance( id_client ){

		$('#claim_loyalty_tier').val(0);
		system.loyalty_discount_cordobas = 0;
		$('#loyalty-block').hide();
		$('#loyalty-claim-options').empty();
		$('#loyalty-balance-display').text('');

		if( !id_client ) return;

		$.post( '<?php echo base_url('loyalty_points/getClientBalance'); ?>', { id_client : id_client }, function(data){

			if( !data || !data.enabled ) return;

			$('#loyalty-block').show();
			$('#loyalty-balance-display').text('Saldo: ' + numeral(data.balance).format('0,0.[00]') + ' / ' + data.ceiling + ' pts');

			if( data.max_eligible_tier > 0 ){

				var tier = null;
				$.each(data.tiers, function(i, t){
					if( t.tier == data.max_eligible_tier ) tier = t;
				});

				if( tier ){

					var label = 'Aplicar recompensa de fidelidad — Etapa ' + tier.tier + ' (' + numeral(tier.rate).format('0,0.[00]') + '% descuento en servicios, quema ' + numeral(tier.threshold).format('0,0.[00]') + ' pts)';

					var $chk = $('<label class="checkbox-inline">')
						.append($('<input type="checkbox" id="loyalty-claim-checkbox">').data('tier', tier))
						.append(' ' + label);

					$('#loyalty-claim-options').append($chk);

					$('#loyalty-claim-checkbox').on('change', function(){

						if( $(this).is(':checked') ){
							$('#claim_loyalty_tier').val(tier.tier);
							system.loyalty_discount_cordobas = system.total_services * tier.rate / 100;
						}else{
							$('#claim_loyalty_tier').val(0);
							system.loyalty_discount_cordobas = 0;
						}

						var $row = $('#sales-list tr').eq(0);
						if( $row.length ) updateTotal($row); else updateDueAmount();
					});
				}
			}

		}, 'json');
	}

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
		fetchLoyaltyBalance(c.id_client);

		_selectedClientVip = (c.is_vip_client == 1 || c.is_vip_client == '1');
		_selectedClientBirthday = (c.is_birthday_today == 1 || c.is_birthday_today == '1');
		updateTotal();

		$('#client-picker-modal').modal('hide');
	});

	// ----- Atendido por (servicing employee) picker modal -----
	// One modal instance shared by every service row's readonly "Atendido por" input; the row that
	// was clicked is remembered in _atendidoPickerTargetRow and only written to on OK.

	var _atendidoPickerTargetRow = null;
	var _atendidoPickerSelected  = null;
	var _atendidoPickerListBuilt = false;

	// Employees are already fully rendered server-side into the "Vendedor" select (#id_employee) —
	// reuse that instead of a new AJAX endpoint or duplicating the list.
	function buildAtendidoPickerList(){

		if( _atendidoPickerListBuilt ) return;
		_atendidoPickerListBuilt = true;

		var $tbody = $('#atendido-picker-results').empty();

		$('#id_employee option').each(function(){

			var id = $(this).val();
			var name = $(this).text().trim();

			if( !id ) return; // skip the blank "---" placeholder option

			$('<tr>').data('employee', { id : id, name : name })
				.append($('<td>').text(name))
				.appendTo($tbody);
		});
	}

	$(document).on('click', '.atendido-name', function(){

		_atendidoPickerTargetRow = $(this).closest('tr');
		_atendidoPickerSelected = null;

		buildAtendidoPickerList();

		$('#atendido-picker-search').val('');
		$('#atendido-picker-results tr').show().removeClass('picker-selected');
		$('#atendido-picker-ok').prop('disabled', true);

		// pre-highlight the row's current selection, if any
		var currentId = _atendidoPickerTargetRow.find('.atendido-id').val();
		if( currentId ){
			$('#atendido-picker-results tr').each(function(){
				var e = $(this).data('employee');
				if( e && e.id == currentId ){
					$(this).addClass('picker-selected');
					_atendidoPickerSelected = e;
					$('#atendido-picker-ok').prop('disabled', false);
				}
			});
		}

		$('#atendido-picker-modal').modal('show');

		$('#atendido-picker-modal').one('shown.bs.modal', function(){
			$('#atendido-picker-search').focus();
		});
	});

	// Plain client-side substring filter — the employee list is small and already fully loaded,
	// unlike the client/product pickers which query potentially large tables via AJAX.
	$(document).on('input', '#atendido-picker-search', function(){

		var term = $.trim( $(this).val() ).toLowerCase();

		$('#atendido-picker-results tr').each(function(){
			var e = $(this).data('employee');
			var match = !term || ( e && e.name.toLowerCase().indexOf(term) !== -1 );
			$(this).toggle(!!match);
		});
	});

	// Row highlight on click
	$(document).on('click', '#atendido-picker-results tr', function(){
		var e = $(this).data('employee');
		if( !e ) return;
		$('#atendido-picker-results tr').removeClass('picker-selected');
		$(this).addClass('picker-selected');
		_atendidoPickerSelected = e;
		$('#atendido-picker-ok').prop('disabled', false);
	});

	// OK: write the selected employee into the row that opened the modal
	$('#atendido-picker-ok').on('click', function(){

		if( !_atendidoPickerSelected || !_atendidoPickerTargetRow ) return;

		_atendidoPickerTargetRow.find('.atendido-name').val(_atendidoPickerSelected.name);
		_atendidoPickerTargetRow.find('.atendido-id').val(_atendidoPickerSelected.id);

		$('#atendido-picker-modal').modal('hide');
	});

	// Full recompute over every row in the sale, not just the one that triggered the change: birthday-
	// ceiling eligibility depends on the whole set of service rows and their order, so a row being added,
	// removed, or having its quantity edited can shift which OTHER rows qualify for the birthday discount.
	// The `row` argument is accepted (existing call sites pass the changed row) but unused.
	//
	// Golden rule, two stages, both UX mirrors of Sales.php — the server never trusts either. Stage 1
	// (below): category/VIP/birthday compete as three whole-sale strategies, only the single winning
	// source's candidate discount is assigned per row (mirrors resolve_single_discount_source()). Stage 2
	// (Pass 2 below): those candidates are then weighed as a whole against any coupon payment currently
	// entered — whichever is worth more in córdobas wins, the other is neutralized (mirrors
	// resolve_coupon_vs_line_discounts()).
	function updateTotal( row ){

		// Manual-discount mode: skip the whole golden-rule computation below (category/VIP/birthday
		// competition, coupon-vs-discount neutralization) entirely. Each row's discount is whatever the
		// employee typed into its (now-editable) .p-discount input, clamped to 0-100; coupon payments
		// are force-disallowed via system.coupon_allowed, reusing refreshCouponAvailability()'s existing
		// revert-any-selected-coupon-row behavior.
		if( _manualDiscountMode ){

			system.coupon_allowed = false;
			system.discount_neutralized_by_coupon = false;
			system.birthday_ceiling_writeoff_cordobas = 0;
			system.birthday_ceiling_rate = 0;

			var total_dollar = 0, total_cordobas = 0, total_services = 0;

			$('#sales-list tr').each(function(){

				var r = $(this);
				var price = numeral( r.find('.p-price').val() ).value();
				var qt = numeral( r.find('.p-quantity').val() ).value();
				var discount = Math.min( 100, Math.max( 0, numeral( r.find('.p-discount').val() ).value() || 0 ) );
				var subtotal_dollar = price * qt;

				if( discount ) subtotal_dollar -= subtotal_dollar * discount / 100;

				var subtotal_cordobas = subtotal_dollar * dollarSellRate;

				r.find('.subtotal-dollar').html( numeral(subtotal_dollar).format('0,0.00') );
				r.find('.subtotal-cordobas').html( numeral(subtotal_cordobas).format('0,0.00') );

				total_dollar += subtotal_dollar;
				total_cordobas += subtotal_cordobas;

				if( r.hasClass('p-service') ) total_services += subtotal_cordobas;
			});

			$('#total-dollar').html( numeral(total_dollar).format('0,0.00') );
			$('#total-cordobas').html( numeral(total_cordobas).format('0,0.00') );

			system.total_services = total_services;
			refreshCouponAvailability();

			var $loyaltyChk = $('#loyalty-claim-checkbox');
			if( $loyaltyChk.length && $loyaltyChk.is(':checked') ){
				var claimedTier = $loyaltyChk.data('tier');
				system.loyalty_discount_cordobas = system.total_services * claimedTier.rate / 100;
			}

			system.total_invoice = Math.max( total_cordobas - system.deposit_credit_cordobas - system.loyalty_discount_cordobas, 0 );

			updateDueAmount();
			return;
		}

		recalcBirthdayEligibility();

		// Golden rule stage 1 (UX mirror of resolve_single_discount_source() in Sales.php — the server
		// remains authoritative and never trusts this): category, VIP, and birthday compete as three
		// whole-sale strategies, not per row independently. First collect each service row's inputs
		// without deciding a discount yet, along with the three "representative" rates for the sale.
		var rows = [];
		var categoryRepresentative = 0;
		var servicesTotalUndiscounted = 0;

		$('#sales-list tr').each(function(){

			var r = $(this);
			var price = numeral( r.find('.p-price').val() ).value();
			var qt = numeral( r.find('.p-quantity').val() ).value();
			var subtotal_dollar = price * qt;
			var isService = r.hasClass('p-service');
			var categoryRate = 0, birthdayEligible = false, undiscountedCordobas = 0;

			if( isService ){

				categoryRate = r.data('category-discount-rate') || 0;
				birthdayEligible = _selectedClientBirthday && !!r.data('birthday-eligible');
				undiscountedCordobas = subtotal_dollar * dollarSellRate;

				if( categoryRate > categoryRepresentative ) categoryRepresentative = categoryRate;
				servicesTotalUndiscounted += undiscountedCordobas;
			}

			rows.push({ el : r, subtotal_dollar : subtotal_dollar, isService : isService,
				categoryRate : categoryRate, birthdayEligible : birthdayEligible, undiscountedCordobas : undiscountedCordobas });
		});

		var vipRepresentative = _selectedClientVip ? VIP_DISCOUNT_RATE : 0;

		// Birthday enters the comparison whenever it qualifies for the sale at all (client's birthday
		// today AND undiscounted services total >= ceiling min) — NOT tied to any row surviving the
		// per-row ceiling walk in recalcBirthdayEligibility(). Once the total goes past the ceiling max,
		// every row can end up excluded by that walk (e.g. a single row whose own amount alone already
		// exceeds the max) while birthday still owes a flat write-off below; it must still win the sale
		// for that write-off to happen, not fall back to discounting nothing (mirrors
		// resolve_single_discount_source()'s $birthday_qualifies server-side).
		var birthdayApplies = _selectedClientBirthday && servicesTotalUndiscounted >= BIRTHDAY_CEILING_MIN;
		var birthdayRepresentative = birthdayApplies ? BIRTHDAY_DISCOUNT_RATE : 0;

		// ascending precedence (category, then VIP, then birthday) checked with >=, so a later source
		// overtakes an equal representative — gives birthday > VIP > category on exact ties, matching
		// resolve_single_discount_source() server-side
		var winningSource = 'none', winningRate = 0;

		if( categoryRepresentative > winningRate ){
			winningSource = 'category';
			winningRate = categoryRepresentative;
		}
		if( vipRepresentative > 0 && vipRepresentative >= winningRate ){
			winningSource = 'vip';
			winningRate = vipRepresentative;
		}
		if( birthdayRepresentative > 0 && birthdayRepresentative >= winningRate ){
			winningSource = 'birthday';
			winningRate = birthdayRepresentative;
		}

		// Birthday-ceiling cap (UX mirror of resolve_birthday_ceiling_writeoff() in discount_helper.php):
		// once birthday wins the sale AND the undiscounted services total goes beyond the configured
		// maximum, the discount stops being granted per row — it's capped at birthdayRepresentative% of
		// the MAXIMUM (not the actual total) and applied as a single flat write-off below, with every
		// service row's candidate forced to 0. No-op otherwise: a different source won, or birthday won
		// within the ceiling (today's per-row behavior stays unchanged).
		var birthdayCeilingWriteoffCordobas = 0;

		if( winningSource === 'birthday' && servicesTotalUndiscounted > BIRTHDAY_CEILING_MAX ){
			birthdayCeilingWriteoffCordobas = birthdayRepresentative / 100 * BIRTHDAY_CEILING_MAX;
		}

		// Pass 1b: assign each row's candidate discount from the winning source, nothing written to the
		// DOM yet — mirrors resolve_single_discount_source()'s per-line output
		var totalLineDiscountCordobas = 0;

		$.each(rows, function(i, row){

			var candidateDiscount = 0;

			if( row.isService ){

				if( birthdayCeilingWriteoffCordobas > 0 ){
					candidateDiscount = 0;
				}
				else if( winningSource === 'category' ) candidateDiscount = row.categoryRate;
				else if( winningSource === 'vip' ) candidateDiscount = vipRepresentative;
				else if( winningSource === 'birthday' ) candidateDiscount = row.birthdayEligible ? birthdayRepresentative : 0;

				totalLineDiscountCordobas += row.undiscountedCordobas * candidateDiscount / 100;
			}

			row.candidateDiscount = candidateDiscount;
		});

		totalLineDiscountCordobas += birthdayCeilingWriteoffCordobas;

		var couponTenderTotal = 0;
		$.each(system.payments, function(idx, p){
			if( p.method === 'cupon' ) couponTenderTotal += p.subtotal;
		});

		var couponAllowed = true;
		var neutralizedByCoupon = false;

		if( totalLineDiscountCordobas > 0 ){
			if( couponTenderTotal > totalLineDiscountCordobas + 0.01 ){
				neutralizedByCoupon = true;
			}else{
				couponAllowed = false;
			}
		}

		system.coupon_allowed = couponAllowed;
		system.discount_neutralized_by_coupon = neutralizedByCoupon;

		var birthdayWriteoffApplied = neutralizedByCoupon ? 0 : birthdayCeilingWriteoffCordobas;
		system.birthday_ceiling_writeoff_cordobas = birthdayWriteoffApplied;
		system.birthday_ceiling_rate = birthdayWriteoffApplied > 0 ? birthdayRepresentative : 0;

		// Pass 2: write the final (possibly neutralized) discount and compute displayed/aggregate totals
		var total_dollar = 0, total_cordobas = 0, total_services = 0;

		$.each(rows, function(i, row){

			var r = row.el;
			var discount = neutralizedByCoupon ? 0 : row.candidateDiscount;
			var subtotal_dollar = row.subtotal_dollar;

			if( r.hasClass('p-service') ) r.find('.p-discount').val( discount );

			if( discount ){
				subtotal_dollar = subtotal_dollar - ( subtotal_dollar * discount / 100 );
			}

			var subtotal_cordobas = subtotal_dollar * dollarSellRate;

			r.find('.subtotal-dollar').html( numeral(subtotal_dollar).format('0,0.00') );
			r.find('.subtotal-cordobas').html( numeral(subtotal_cordobas).format('0,0.00') );

			total_dollar += subtotal_dollar;
			total_cordobas += subtotal_cordobas;

			if( r.hasClass('p-service') ) total_services += subtotal_cordobas;
		});

		$('#total-dollar').html( numeral(total_dollar).format('0,0.00') );
		$('#total-cordobas').html( numeral(total_cordobas).format('0,0.00') );

		//services-only subtotal, used to cap coupon redemption
		system.total_services = total_services;
		refreshCouponAvailability();

		//keep an already-claimed loyalty reward in sync with the current services subtotal
		var $loyaltyChk = $('#loyalty-claim-checkbox');
		if( $loyaltyChk.length && $loyaltyChk.is(':checked') ){
			var claimedTier = $loyaltyChk.data('tier');
			system.loyalty_discount_cordobas = system.total_services * claimedTier.rate / 100;
		}

		system.total_invoice = Math.max( total_cordobas - system.deposit_credit_cordobas - system.loyalty_discount_cordobas - birthdayWriteoffApplied, 0 );

		updateDueAmount();
	}

	function updateTotalSubsctract( row, event ){

		//weird
		if( event.screenX == 0 )
			return false;

		row.remove();

		// removing a row can change birthday-ceiling eligibility for the remaining rows, so this delegates
		// to the same full recompute as updateTotal() rather than subtracting this row's totals in place
		updateTotal();
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

	// Continuation of an appointment's booking deposit: prefill the client + its current-priced
	// services (reusing the same row-building path as the product picker), then credit the frozen
	// deposit against the invoice total before anything else runs.
	if( appointmentPrefill ){

		$('#id_appointment').val(appointmentPrefill.id_appointment);

		if( appointmentPrefill.client ){
			var c = appointmentPrefill.client;
			$('#client_name').val(c.name_client+' '+c.surname_client);
			$('#id_client').val(c.id_client);
			$('#cedula_cliente').val(c.id_card_client || '')
				.prop('disabled', true)
				.attr('title', 'Cliente de la reserva — no se puede modificar');
			fetchLoyaltyBalance(c.id_client);

			_selectedClientVip = (c.is_vip_client == 1 || c.is_vip_client == '1');
			_selectedClientBirthday = (c.is_birthday_today == 1 || c.is_birthday_today == '1');
		}

		$.each(appointmentPrefill.products || [], function(i, p){
			addProductRow(p);
		});

		system.deposit_credit_cordobas = appointmentPrefill.deposit_credit_cordobas || 0;

		var currentTotalCordobas = numeral($('#total-cordobas').html()).value();
		system.total_invoice = Math.max(currentTotalCordobas - system.deposit_credit_cordobas - system.loyalty_discount_cordobas - system.birthday_ceiling_writeoff_cordobas, 0);

		if( system.total_invoice <= 0 ){

			// the deposit alone already covers (or exceeds) the invoice total; no payment row required by default
			$('#payment-blocks').empty();
			system.payments = {};
		}

		updateDueAmount();
	}

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

		var couponError = null;
		var seenCoupons = {};
		var cuponSeenOnce = false;
		var nonCashTotal = 0;
		var dollarRowCount = 0;
		var couponRemaining = system.total_services;

		$('#payment-blocks').children('.payment_container').each(function(){

			var $block  = $(this);
			var method  = $block.find('.payment-method-select').val();
			var amount  = system.payments[$block.data('idx')].subtotal;

			if( method !== 'cupon' && ( numeral( $block.find('.dolar-input').val() || 0 ).value() > 0 ) )
				dollarRowCount++;

			if( method === 'cupon' ){

				if( !system.coupon_allowed ){
					couponError = _manualDiscountMode
						? 'No se puede usar cupón cuando el descuento es manual'
						: 'El descuento de los servicios es mayor o igual al monto del cupón; no se puede usar el cupón';
					return false;
				}

				if( cuponSeenOnce ){
					couponError = 'No se permite más de un método de pago con cupón por venta';
					return false;
				}
				cuponSeenOnce = true;

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

				if( couponRemaining <= 0.01 ){
					couponError = 'Los cupones solo se pueden aplicar sobre servicios, y esta factura no tiene servicios';
					return false;
				}

				if( amount - couponRemaining > 0.01 ){
					couponError = 'El cupón "' + code + '" excede el total disponible de servicios en la factura (disponible: C$' + numeral(couponRemaining).format('0,0.00') + ')';
					return false;
				}
				couponRemaining -= amount;
			}

			if( method !== 'cash' )
				nonCashTotal += amount;
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

		var paymentTolerance = paymentSufficiencyTolerance(dollarRowCount, dollarSellRate);

		if( total < system.total_invoice - paymentTolerance ){
			new PNotify({
		            title: 'Notificación',
		            text: 'El efectivo entregado es menor que el total de la factura',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });

		    return false;
		}

		if( nonCashTotal - system.total_invoice > paymentTolerance ){
			new PNotify({
		            title: 'Notificación',
		            text: 'El total de pagos con POS, transferencia y/o cupón no puede exceder el total de la factura. El efectivo debe cubrir cualquier excedente.',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });

		    return false;
		}

		if( changeOwedCordobas > 0 && Math.abs( computeChangeLeftover() ) >= 1 ){
			new PNotify({
		            title: 'Notificación',
		            text: 'El cambio entregado no cuadra con el vuelto de C$' + numeral(changeOwedCordobas).format('0,0.00') + '. Solo se permite una diferencia de centavos.',
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

	function submitForm(){

		var btn = $('#btn-submit');
		var loader = $('#loader');

		$('#payment-blocks').children('.payment_container').each(function(){

			var $block = $(this);
			var method = $block.find('.payment-method-select').val();

			// intentionally not blanking the account select for non-transfer methods: it has no
			// blank option, so .val('') deselects it and jQuery drops it from the serialized form
			// entirely, shifting account_number[] out of alignment with payment_method[] on submit.
			// The server only reads this field when method === 'transfer', so a stale value here is harmless.

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

		        if( data.tk ){
		        	$('input[name="tk"]').val(data.tk);
		        }

		        if( data.type == 'success' ){

		        	$('#barcode').val('');
		        	$('#cedula_cliente').val('');
		        	$('#id_client').val('');
		        	$('#client_name').val('');
		        	$('#id_employee option:eq(0)').prop('selected',true);
		        	$('#form .product').remove();
		        	$('#total-cordobas').html('');
		        	$('#total-dollar').html('');

		        	$('#id_appointment').val('');
		        	system.deposit_credit_cordobas = 0;
		        	$('#deposit-applied-badge').hide().text('');

		        	system.loyalty_discount_cordobas = 0;
		        	$('#claim_loyalty_tier').val(0);
		        	$('#loyalty-applied-badge').hide().text('');
		        	$('#loyalty-block').hide();
		        	$('#loyalty-claim-options').empty();
		        	$('#loyalty-balance-display').text('');

		        	system.birthday_ceiling_writeoff_cordobas = 0;
		        	system.birthday_ceiling_rate = 0;
		        	$('#birthday-ceiling-applied-badge').hide().text('');

		        	// a completed continuation may have emptied #payment-blocks entirely (zero remaining
		        	// balance owed); restore the default block so the form is usable for a fresh sale
		        	if( $('#payment-blocks').children('.payment_container').length === 0 ){
		        		var $defaultBlock = $('#payment-mdl .payment_container').eq(0).clone();
		        		$defaultBlock.attr('data-idx', 0).removeClass('hide');
		        		$('#payment-blocks').append($defaultBlock);
		        	}

		        	$('#payment-blocks').children('.payment_container').not(':eq(0)').remove();

		        	system.payments = { '0' : { method : 'cash', subtotal : 0, total : 0 } };
		        	_paymentCounter = 1;

		        	$('#payment-blocks .payment_container').eq(0).find('.payment-method-select').val('cash').trigger('change');
		        	$('#payment-blocks .payment_container').eq(0).find('.cash-input, .cupon-code-input, .cupon-amount-input, .cupon-discount-input').val('');

		        	updateDueAmount();
		        	updateChangeGivenSection(0);
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
			total = cordobas.value();
		}

		system.payments[idx] = { method : method, subtotal : subtotal, total : total };

		$block.find('.payment-total-cordobas').val( numeral(total).format('0,0.00') );

		// full recompute, not just updateDueAmount(): a coupon amount/method change can flip the golden-rule
		// comparison against the line discounts (see updateTotal()), which in turn changes total_invoice
		updateTotal();
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

	function hasCouponPayment(excludeIdx){
		var found = false;
		$.each(system.payments, function(idx, p){
			if( idx != excludeIdx && p.method === 'cupon' ) found = true;
		});
		return found;
	}

	// Golden rule enforcement for the UI: system.coupon_allowed / system.discount_neutralized_by_coupon are
	// computed by updateTotal() just before this runs, comparing the line discounts' córdoba value against
	// whatever coupon amount is currently typed. Unlike the old behavior, the mere presence of a line
	// discount no longer blocks selecting 'cupon' — a coupon row is only reverted once an insufficient
	// amount has actually been entered for it (couponTenderTotal reflects real typed amounts, not zero).
	function refreshCouponAvailability(){

		if( !system.coupon_allowed ){

			// Must run BEFORE the option below gets disabled: once the currently-selected <option> is
			// disabled, the <select>'s .val() stops reporting it (returns null) even though it's still
			// shown as selected, so this check would never match if it ran after.
			$('#payment-blocks .payment_container').each(function(){
				var $block = $(this);
				var amt = numeral( $block.find('.cupon-amount-input').val() || 0 ).value();

				if( $block.find('.payment-method-select').val() === 'cupon' && amt > 0 ){
					$block.find('.payment-method-select').val('pos').trigger('change');
					new PNotify({
						title: 'Notificación',
						text: 'El descuento de los servicios es mayor o igual al monto del cupón; no se puede usar el cupón',
						type : 'error',
						styling : 'bootstrap3',
						delay : 4000
					});
				}
			});
			system._couponNeutralizeNotified = false;

		}else if( system.discount_neutralized_by_coupon ){

			if( !system._couponNeutralizeNotified ){
				system._couponNeutralizeNotified = true;
				new PNotify({
					title: 'Notificación',
					text: 'El cupón supera el valor del descuento de los servicios: se aplicará el cupón en lugar del descuento',
					type : 'info',
					styling : 'bootstrap3',
					delay : 4000
				});
			}
		}else{
			system._couponNeutralizeNotified = false;
		}
	}

	function updateDueAmount(){
		var paid = 0;
		$.each(system.payments, function(idx, p){ paid += p.subtotal; });

		var due = numeral(system.total_invoice - paid);
		var $badge = $('#payment-due-badge');
		var $overpaymentBadge = $('#payment-overpayment-badge');
		var $depositBadge = $('#deposit-applied-badge');
		var $loyaltyBadge = $('#loyalty-applied-badge');
		var $birthdayCeilingBadge = $('#birthday-ceiling-applied-badge');

		if( system.deposit_credit_cordobas > 0 ){
			$depositBadge.text('Depósito aplicado: C$' + numeral(system.deposit_credit_cordobas).format('0,0.00')).show();
		}else{
			$depositBadge.hide().text('');
		}

		if( system.loyalty_discount_cordobas > 0 ){
			$loyaltyBadge.text('Descuento por puntos aplicado: C$' + numeral(system.loyalty_discount_cordobas).format('0,0.00')).show();
		}else{
			$loyaltyBadge.hide().text('');
		}

		if( system.birthday_ceiling_writeoff_cordobas > 0 ){
			$birthdayCeilingBadge.text('Descuento cumpleaños aplicado (' + numeral(system.birthday_ceiling_rate).format('0,0.[00]') + '% sobre el tope): C$' + numeral(system.birthday_ceiling_writeoff_cordobas).format('0,0.00')).show();
		}else{
			$birthdayCeilingBadge.hide().text('');
		}

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
			updateChangeGivenSection(overpaidCordobas);
		}else{
			$overpaymentBadge.hide().text('');
			updateChangeGivenSection(0);
		}
	}

	var changeOwedCordobas = 0; // tracked outside the DOM so re-reading it never depends on parsing a formatted "C$..." display string

	function computeChangeLeftover(){
		var givenCordoba = numeral( $('#change_cordoba_given').val() || 0 ).value();
		var givenDollar = numeral( $('#change_dollar_given').val() || 0 ).value();
		var givenTotal = givenCordoba + ( givenDollar * dollarSellRate );

		return changeOwedCordobas - givenTotal;
	}

	function refreshChangeRemaining(){
		var remaining = computeChangeLeftover();
		var $remaining = $('#change-remaining-display');
		$remaining.val( 'C$' + numeral(remaining).format('0,0.00') );
		$remaining.toggleClass('change-remaining-invalid', Math.abs(remaining) >= 1);
	}

	function updateChangeGivenSection(overpaidCordobas){
		var hasCash = false;
		$.each(system.payments, function(idx, p){ if( p.method === 'cash' ) hasCash = true; });

		var $section = $('#change-given-section');
		changeOwedCordobas = ( hasCash && overpaidCordobas > 0.009 ) ? overpaidCordobas : 0;

		if( !changeOwedCordobas ){
			$section.addClass('hide');
			$('#change_cordoba_given, #change_dollar_given').val('');
			$('#change-owed-display, #change-remaining-display').val('');
			$('#change-remaining-display').removeClass('change-remaining-invalid');
			return;
		}

		$section.removeClass('hide');
		$('#change-owed-display').val( 'C$' + numeral(changeOwedCordobas).format('0,0.00') );
		refreshChangeRemaining();
	}

	$(document).on('keyup', '#change_cordoba_given, #change_dollar_given', refreshChangeRemaining);

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
		// full recompute: removing a coupon row that was winning the golden-rule comparison must restore
		// the line discount it had neutralized
		updateTotal();
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

		// No preemptive block here for an existing line discount: the golden rule now lets the cashier
		// select 'cupon' and type an amount, then resolve_coupon_vs_line_discounts()-equivalent logic in
		// updateTotal() (triggered via recalcAmountPayment below) decides whether the coupon out-values the
		// discount as they type; refreshCouponAvailability()/submit validation reject only an insufficient
		// coupon amount, not the mere presence of a discount.

		if( method === 'cupon' && hasCouponPayment( $block.data('idx') ) ){
			new PNotify({
		            title: 'Notificación',
		            text: 'No se permite más de un método de pago con cupón por venta',
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

		// don't leave stale values in hidden coupon fields — e.g. after a discounted
		// service forces this row back to 'pos', switching it to 'cupon' again later
		// should start blank instead of silently resurrecting the old code/amount
		if( method !== 'cupon' ){
			$block.find('.cupon-code-input, .cupon-amount-input, .cupon-discount-input').val('');
		}

		recalcAmountPayment($block);
		checkCurrencyMismatch($block);
	});

</script>