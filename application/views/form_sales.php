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
		<div class="col-md-offset-2 col-sm-offset-3 col-md-3 col-sm-3">
			<button type="button" class="btn btn-warning" id="add_payment">Agregar mas forma de pago</button>
		</div>
	</div>
	<div class="payment_container">

		<div class="form-group">
			
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Metodo de pago:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control" id="meth1_payment_method" name="meth1_payment_method">
					<option value="cash">Efectivo</option>
					<option value="pos">POS</option>
					<option value="transfer">Transferencia</option>
				</select>
			</div>

		</div>

		<div class="form-group hide" id="meth1_payment_accountContainer">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Numero de cuenta:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control" name="meth1_account_number" id="meth1_account_number">
					<?php  foreach($accounts as $account): ?>
						<option value="<?php echo $account->token_account ?>"><?php echo $account->name_account; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Monto:</label>
			<div class="col-md-9 col-sm-9">
				<div class="row" style="margin-top: 8px;">
					<div class="col-md-2">
						<label for="">Córdobas:</label>
						<input type="text" name="meth1_cordobas_input" id="meth1_cordobas_input" class="form-control numeric-input cash-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Dólares:</label>
						<input type="text" name="meth1_dolar_input" id="meth1_dolar_input" class="form-control numeric-input cash-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Total C$</label>
						<input type="text" id="meth1_total_cordobas" readonly="true" class="form-control">
					</div>
				</div>
			</div>
		</div>
	</div>
	
	<div class="payment_container dynamic hide">
		<p title="Cerrar" id="dynamic_hide">X</p>
		<div class="form-group">
			
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Metodo de pago:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control" id="meth2_payment_method" name="meth2_payment_method">
					<option value="cash">Efectivo</option>
					<option value="pos">POS</option>
					<option value="transfer">Transferencia</option>
				</select>
			</div>

		</div>

		<div class="form-group hide" id="meth2_payment_accountContainer">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Numero de cuenta:</label>
			<div class="col-md-6 col-sm-6 col-xs-12">
				<select class="form-control" name="meth2_account_number" id="meth2_account_number">
					<?php  foreach($accounts as $account): ?>
						<option value="<?php echo $account->token_account ?>"><?php echo $account->name_account; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>

		<div class="form-group">
			<label class="control-label col-md-2 col-sm-3 col-xs-12" for="">Monto:</label>
			<div class="col-md-9 col-sm-9">
				<div class="row" style="margin-top: 8px;">
					<div class="col-md-2">
						<label for="">Córdobas:</label>
						<input type="text" name="meth2_cordobas_input" id="meth2_cordobas_input" class="form-control numeric-input cash-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Dólares:</label>
						<input type="text" name="meth2_dolar_input" id="meth2_dolar_input" class="form-control numeric-input cash-input" data-parsley-type="number">
					</div>
					<div class="col-md-2">
						<label for="">Total C$</label>
						<input type="text" id="meth2_total_cordobas" readonly="true" class="form-control">
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

<script>

	var system = {
		mth_visible : false,
		total_invoice : 0,
		meth1 : {
			subtotal : 0,
			total : 0
		},
		meth2 : {
			subtotal : 0,
			total : 0
		}
	};

	$('#barcode').focus().on('keypress',function(e){

		var barcode = $(this).val();

		if( e.which == 13 && barcode.length > 0 ){

			e.preventDefault();

		  	var post = { 'barcode' : barcode };
			
			$.ajax({

				url : '<?php echo base_url('products/getByBarCode'); ?>',
				type : 'post',
				dataType : 'json',
				data : post,
				success : function(data){

					if( data.product ){

						var row = $('#product-mdl tr').eq(0).clone();

						if( data.product.is_service == '1' ){
							row.addClass('p-service');

						}else{
							row.find('.p-my_product').addClass('hide');
						}

						row.find('.p-name').html(data.product.barcode+' : '+data.product.name);
						row.find('.p-price').val(data.product.price_sell);
						row.find('.form-ids').val(data.product.id_product);
						row.find('.p-commission_rate').val(data.product.commisssion_rate);

						$('#sales-list').append(row);
						$('#barcode').val('');

						updateTotal(row);
						window.scrollTo(0,document.body.scrollHeight);
					}
					else{

						new PNotify({
				            title: 'Notificación',
				            text: 'No se ha encontrado el producto',
				            type : 'warning',
				            styling : 'bootstrap3',
				            delay : 2000
				        });
					}

				},
				error : function(){

					new PNotify({
			            title: 'Notificación',
			            text: 'Ha ocurrido un error guardando los datos',
			            type : 'error',
			            styling : 'bootstrap3',
			            delay : 3500
			        });
				}

			});
		}
	});

	$('#cedula_cliente').focus().on('keypress',function(e){

		var identification = $(this).val();

		if( e.which == 13 && identification.length > 0 ){

			e.preventDefault();

			var post = { 'id_client' : identification };
			
			$.ajax({

				url : '<?php echo base_url('clients/getByIdCard'); ?>',
				type : 'post',
				dataType : 'json',
				data : post,
				success : function(data){
					if( !data.error ){
						$('#client_name').val(`${data.name_client} ${data.surname_client}`);
						$('#id_client').val(data.id_client);
					}
					else{

						new PNotify({
							title: 'Notificación',
							text: data.error,
							type : 'warning',
							styling : 'bootstrap3',
							delay : 2000
						});
					}

				},
				error : function(){

					new PNotify({
						title: 'Notificación',
						text: 'Ha ocurrido un error extrayendo los datos',
						type : 'error',
						styling : 'bootstrap3',
						delay : 3500
					});
				}

			});
		}
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
		subtotal_cordobas = numeral( Math.ceil( subtotal_dollar.value() * <?php echo $exchange->buy_price; ?>) );

		row.find('.subtotal-cordobas').html( subtotal_cordobas.format('0,0.00') );

		//calc cordobas total
		$('#products-sale .subtotal-cordobas').each(function(index,element){

			total_cordobas += numeral( $(element).html() ).value();

		});

		total_cordobas = numeral(total_cordobas);
		system.total_invoice = total_cordobas.value();
		$('#total-cordobas').html(total_cordobas.format('0,0.00'));
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

		var total = system.mth_visible 
		? system.meth1.subtotal + system.meth2.subtotal 
		: system.meth1.subtotal;

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

		if( system.mth_visible ){
			$('#meth2_payment_method').prop('disabled', false);
			$('#meth2_account_number').prop('disabled', false);
			$('#meth2_cordobas_input').prop('disabled', false);
			$('#meth2_dolar_input').prop('disabled', false);
		}else{
			$('#meth2_payment_method').prop('disabled', true);
			$('#meth2_account_number').prop('disabled', true);
			$('#meth2_cordobas_input').prop('disabled', true);
			$('#meth2_dolar_input').prop('disabled', true);
		}

		if( $('#meth1_payment_method').val() !== "transfer" ){
			$('#meth1_account_number').prop('disabled', true);
		}else{
			$('#meth1_account_number').prop('disabled', false);
		}

		if( $('#meth2_payment_method').val() !== "transfer" ){
			$('#meth2_account_number').prop('disabled', true);
		}else{
			$('#meth2_account_number').prop('disabled', false);
		}

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
		        	$('.cash-input').val('');
		        	$('#pos_paid').iCheck('uncheck');
		        	$('#form .product').remove();
		        	$('#total-cordobas').html('');
		        	$('#total-dollar').html('');
		        	$('#cash_total_cordobas').val('');
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

	function recalcAmountPayment(mth){
		var method = $(`#${mth}_payment_method`).val();

		var cordobas = numeral( $(`#${mth}_cordobas_input`).val() || 0 );

		var dollar = numeral( Math.round( $(`#${mth}_dolar_input`).val() * <?php echo $exchange->buy_price; ?> ) || 0 );

		if( dollar )
			cordobas.add( dollar.value() );

		system[mth]['subtotal'] = cordobas.value();
		
		if( method === "pos" )
			cordobas.add( cordobas.value() * (6.75/100)  );

		system[mth]['total'] = cordobas.value();
		
		$(`#${mth}_total_cordobas`).val( cordobas.format('0,0.00') );
	}

	$('.cash-input').each(function(index, element){
		var [mth, pay, inp] = element.id.split("_");

		$(element).on('keyup',function(){
			recalcAmountPayment(mth);
		});
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
		var container = $('.payment_container.dynamic');
		if( container.hasClass('hide') ){
			container.removeClass('hide');
			system.mth_visible = true;
		}else{
			new PNotify({
		            title: 'Notificación',
		            text: 'Ya se ha agregado otro metodo de pago',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 4000
		    });
		}
		
	});

	$('#dynamic_hide').on('click', function(event){
		var container = $('.payment_container.dynamic');
		if( !container.hasClass('hide') ){
			container.addClass('hide');
			system.mth_visible = false;
		}
	});

	$('#meth1_payment_method, #meth2_payment_method').on('change', function(event){
		var [mth, pay, desc] = event.target.id.split("_")
		var container = $(`#${mth}_payment_accountContainer`);
		var option = $(event.target);

		recalcAmountPayment(mth);
		
		if(option.val() === "transfer"){
			container.removeClass('hide');
		}else{
			container.addClass('hide');
		}
	});

</script>