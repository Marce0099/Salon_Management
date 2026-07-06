<?php 
	
	if( isset($product) ){

		$url = base_url("products/edit_new/{$product->id_product}");
	}
	else{

		$url = base_url('products/register_new');
		$product = NULL;
	}
?>

<h4 class="section-title">Formulario de productos</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="barcode">Código de barra
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="barcode" id="barcode" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($product){ echo $product->barcode; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name">Nombre
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="name" id="name" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($product){ echo $product->name; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="price_cost">Precio de costo
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_cost" id="price_cost" class="form-control has-feedback-left" value="<?php if($product){ echo $product->price_cost; } ?>">
            <span class="form-control-feedback left">C$</span>
        </div>
        <div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_cost_dollar" id="price_cost_dollar" class="form-control has-feedback-left" value="<?php if($product){ echo $product->price_cost; } ?>" required="true">
            <span class="form-control-feedback left">$</span>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="price_sell_percent">Precio en venta
        </label>
        <div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_sell_percent" id="price_sell_percent" class="form-control has-feedback-left">
            <span class="form-control-feedback left">%</span>
        </div>
        <div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" required="true" name="price_sell" id="price_sell" class="form-control has-feedback-left" value="<?php if($product){ echo $product->price_sell; } ?>">
            <span class="form-control-feedback left">$</span>
        </div>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_sell_cordobas" id="price_sell_cordobas" class="form-control has-feedback-left">
            <span class="form-control-feedback left">C$</span>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="stock">Stock
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="stock" id="stock" class="form-control" value="<?php if($product){ echo $product->stock; } ?>" required="true">
        </div>
    </div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
            <div class="checkbox">
	        	<label style="padding-left: 0;">
	        		<input type="checkbox" class="flat flat-green" name="is_service" value="1" <?php if( $product && $product->is_service ){ echo 'checked'; } ?>> Servicio
	        	</label>
	        </div>
        </div>
	</div>
	<input type="hidden" name="is_new" id="is_new" value="0">
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>


<script>

	var percent = $('#price_sell_percent');
	var price_cost = $('#price_cost');
	var price_cost_dollar = $('#price_cost_dollar');
	var price_sell = $('#price_sell');
	var price_sell_cordobas = $('#price_sell_cordobas');

	$('#form').parsley().on('field:validated', function() {

	    var ok = $('.parsley-error').length === 0;
	    $('.bs-callout-info').toggleClass('hidden', !ok);
	    $('.bs-callout-warning').toggleClass('hidden', ok);

	  })
	  .on('form:submit', function() {

	  	var btn = $('#btn-submit');
	  	var post = $('#form').serializeArray();

	  	btn.attr('disabled','true');

	  	$.ajax({

	  		url : '<?php echo $url; ?>',
	  		data : post,
	  		type : 'post',
	  		dataType : 'json',
	  		success : function(data){

	  			new PNotify({
		            title: 'Notificación',
		            text: data.message,
		            type : data.type,
		            styling : 'bootstrap3',
		            delay : 3500
		        });

		        btn.removeAttr('disabled');

		        if( data.type == 'success' && !data.edit ){

		        	$('#form input, #form textarea').val('');
		        	$("#form .icheckbox_flat-green").removeClass('checked');
		        }

		        $('#barcode').focus();
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
	  		}
	  	});

	  	return false;

	  });

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

						$('#name').val(data.product.name);
						price_cost.val( data.product.price_cost );
						$('#stock').val(data.product.stock);
						$('#is_new').val(0);
						updateCostDollar();

						if( data.product.price_sell ){

							price_cost_dollar.val( data.product.price_cost );
							price_sell.val( data.product.price_sell );
						}

						//if( data.product.price_sell && data.product.price_cost )
							
					}
					else{

						new PNotify({
				            title: 'Notificación',
				            text: 'No se ha encontrado el producto',
				            type : 'warning',
				            styling : 'bootstrap3',
				            delay : 2000
				        });

				        $('#is_new').val(1);
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

	$('#price_cost').on('keyup',updateCostDollar);

	function updateCostDollar(){

		var cost_dollar = numeral( price_cost.val() / <?php echo $exchange->sell_price; ?> );
		price_cost_dollar.val( cost_dollar.value().toFixed(2) );
		updatePrices();

	}

	/*$('#price_cost_dollar,#price_sell_percent').on('keyup',function(){

		updatePrices();
	});*/

	$('#price_sell').on('keyup',function(){

		var sell_dollar = numeral( price_sell.val() * <?php echo $exchange->sell_price; ?> );
		price_sell_cordobas.val( sell_dollar.value().toFixed(2) );

	});

	function updatePrices(){

		var pdollar = numeral(price_cost_dollar.val());

		if( percent.val() )
			pdollar = numeral( numeral( percent.val() ).value() * numeral(price_cost_dollar.val()).value() / 100 + numeral(price_cost_dollar.val()).value() );

		price_sell.val( pdollar.value().toFixed(2) );

		price_sell_cordobas.val( numeral( pdollar.value() * <?php echo $exchange->sell_price; ?> ).value().toFixed(2) );
	}

</script>