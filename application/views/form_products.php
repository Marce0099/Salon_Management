<?php 
	
	if( isset($product) ){

		$url = base_url("products/edit/{$product->id_product}");
	}
	else{

		$url = base_url('products/register');
		$product = NULL;
	}
?>

<h4 class="section-title">Formulario de productos</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="barcode">Código de barra:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="barcode" id="barcode" class="form-control col-md-7 col-xs-12" value="<?php if($product){ echo $product->barcode; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name">Descripción:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="name" id="name" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($product){ echo $product->name; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="price_sell">Precio de costo:
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_cost" id="price_cost" class="form-control has-feedback-left" required="true" value="<?php if($product){ echo $product->price_cost; } ?>">
            <span class="form-control-feedback left">$</span>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="price_sell">Precio de venta:
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="price_sell" id="price_sell" class="form-control has-feedback-left" required="true" value="<?php if($product){ echo $product->price_sell; } ?>">
            <span class="form-control-feedback left">$</span>
        </div>
	</div>
	<?php if( !$product ) : ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="stock">Stock:
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="integer" name="stock" id="stock" class="form-control" value="<?php if($product){ echo $product->stock; }else{ echo '0'; } ?>">
        </div>
	</div>
	<input type="hidden" name="tk" value="<?php echo $tk; ?>">
	<?php endif; ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="id_provider">Proveedor:
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <select name="id_provider" id="id_provider" class="form-control">
            	<option value="">---</option>
            	<?php foreach( $providers as $p ) : ?>
            	<option value="<?php echo $p->id_provider; ?>" <?php if($product && $product->id_provider==$p->id_provider) echo 'selected'; ?>>
            		<?php echo $p->provider; ?>
            	</option>
            	<?php endforeach; ?>
            </select>
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
            <div class="checkbox">
	        	<label style="padding-left: 0;">
	        		<input type="checkbox" class="flat flat-green" name="is_service" value="1" id="is_service" <?php if( $product && $product->is_service ){ echo 'checked'; } ?>> Servicio
	        	</label>
	        </div>
        </div>
	</div>
	<div class="form-group" id="commission">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="commisssion_rate">Comision (%):
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="number" name="commisssion_rate" id="commisssion_rate" class="form-control" value="<?php if($product){ echo $product->commisssion_rate; }else{ echo '0'; } ?>">
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>


<script>
	const system = {
		'is_service' : $('#is_service').prop('checked')
	};

	function toggleCommission(element){
		const rate = $('#commisssion_rate');
		if(system.is_service){
			element.removeClass('hide');
			rate.prop('disabled', false);
		}else{
			element.addClass('hide');
			rate.prop('disabled', true);
		}
	}

	$(document).ready(function() {
		toggleCommission($('#commission'));
	});

	$('#is_service').on('ifChanged', function() {
		system.is_service = $(this).prop('checked');
		toggleCommission($('#commission'));
	});

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
</script>