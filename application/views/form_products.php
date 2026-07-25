<?php 
	
	if( isset($product) ){

		$url = base_url("products/edit/{$product->id_product}");
	}
	else{

		$url = base_url('products/register');
		$product = NULL;
	}
?>

<h4 class="section-title">Formulario de productos / servicios</h4>

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
	<div class="form-group" id="stock-alert-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="stock_alert_threshold">Alerta de stock mínimo:
        </label>
		<div class="col-md-2 col-sm-3 col-xs-8">
            <input type="text" data-parsley-type="integer" name="stock_alert_threshold" id="stock_alert_threshold" class="form-control" value="<?php if($product && $product->stock_alert_threshold !== NULL){ echo $product->stock_alert_threshold; } ?>">
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
	<div class="form-group" id="service-category-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="service_category_name">Categoria:
        </label>
		<div class="col-md-3 col-sm-3 col-xs-8" style="position:relative;">
            <input type="text" id="service_category_name" class="form-control" autocomplete="off" value="<?php if($product && isset($product->name_category)){ echo $product->name_category; } ?>">
            <input type="hidden" name="id_service_category" id="id_service_category" value="<?php if($product && $product->id_service_category){ echo $product->id_service_category; } ?>">
            <ul class="dropdown-menu" id="service-category-dropdown" style="display:none; width:100%; max-height:200px; overflow-y:auto;"></ul>
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

	function toggleServiceCategory(element){
		if(system.is_service){
			element.removeClass('hide');
		}else{
			element.addClass('hide');
			$('#service_category_name').val('');
			$('#id_service_category').val('');
			$('#service-category-dropdown').hide().empty();
		}
	}

	function toggleStockAlert(element){
		if(system.is_service){
			element.addClass('hide');
			$('#stock_alert_threshold').val('');
		}else{
			element.removeClass('hide');
		}
	}

	$(document).ready(function() {
		toggleCommission($('#commission'));
		toggleServiceCategory($('#service-category-group'));
		toggleStockAlert($('#stock-alert-group'));

		var params = new URLSearchParams(window.location.search);

		if( params.get('is_service') === '1' && !system.is_service ){

			// defer until after custom.js has initialized iCheck on this checkbox
			setTimeout(function(){
				$('#is_service').iCheck('check');
			}, 0);
		}
	});

	$('#is_service').on('ifChanged', function() {
		system.is_service = $(this).prop('checked');
		toggleCommission($('#commission'));
		toggleServiceCategory($('#service-category-group'));
		toggleStockAlert($('#stock-alert-group'));
	});

	var _categoryTimer = null;
	var _categoryLastSelected = $('#service_category_name').val();

	function categorySearch(){

		var name = $('#service_category_name').val();
		var $dropdown = $('#service-category-dropdown');

		$.ajax({
			url : '<?php echo base_url('service_categories/search'); ?>',
			data : { name : name },
			type : 'post',
			dataType : 'json',
			success : function(data){

				$dropdown.empty();

				if( data.categories && data.categories.length ){

					$.each(data.categories, function(i, c){
						$('<li>').append(
							$('<a href="#">').text(c.name_category).on('click', function(ev){
								ev.preventDefault();
								$('#service_category_name').val(c.name_category);
								$('#id_service_category').val(c.id_service_category);
								_categoryLastSelected = c.name_category;
								$dropdown.hide().empty();
							})
						).appendTo($dropdown);
					});

					$dropdown.show();
				}
				else{

					$('<li class="disabled"><a href="#">Sin resultados</a></li>').appendTo($dropdown);
					$dropdown.show();
				}
			}
		});
	}

	$('#service_category_name').on('input', function(){

		if( $(this).val() !== _categoryLastSelected ){
			$('#id_service_category').val('');
		}

		clearTimeout(_categoryTimer);
		_categoryTimer = setTimeout(categorySearch, 300);
	});

	$('#service_category_name').on('focus', function(){

		if( !$(this).val() ){
			categorySearch();
		}
	});

	$('#service_category_name').on('blur', function(){

		var $field = $(this);

		// let a dropdown click register before we hide it
		setTimeout(function(){
			$('#service-category-dropdown').hide();
		}, 200);

		var name = $field.val();

		if( !name || $('#id_service_category').val() ){
			return;
		}

		$.ajax({
			url : '<?php echo base_url('service_categories/search'); ?>',
			data : { name : name, exact : 1 },
			type : 'post',
			dataType : 'json',
			success : function(data){

				if( data.category ){
					$field.val(data.category.name_category);
					$('#id_service_category').val(data.category.id_service_category);
					_categoryLastSelected = data.category.name_category;
				}
			}
		});
	});

	$('#form').parsley().on('field:validated', function() {

	    var ok = $('.parsley-error').length === 0;
	    $('.bs-callout-info').toggleClass('hidden', !ok);
	    $('.bs-callout-warning').toggleClass('hidden', ok);

	  })
	  .on('form:submit', function() {

	  	function doSave(){

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

		        		var urlParams = new URLSearchParams(window.location.search);

		        		if( urlParams.get('is_service') === '1' ){

		        			$('#is_service').val('1');

		        			if( !$('#is_service').prop('checked') ){
		        				$('#is_service').iCheck('check');
		        			}

		        			system.is_service = true;
		        			toggleCommission($('#commission'));
		        			toggleServiceCategory($('#service-category-group'));

		        		}else{

		        			$('#is_service').iCheck('uncheck');
		        		}

		        		if( data.tk ){
		        			$('input[name="tk"]').val(data.tk);
		        		}
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
	  	}

	  	var categoryName = $('#service_category_name').val();

	  	if( system.is_service && categoryName && !$('#id_service_category').val() ){

	  		$.ajax({
	  			url : '<?php echo base_url('service_categories/search'); ?>',
	  			data : { name : categoryName, exact : 1 },
	  			type : 'post',
	  			dataType : 'json',
	  			success : function(data){

	  				if( data.category ){
	  					$('#service_category_name').val(data.category.name_category);
	  					$('#id_service_category').val(data.category.id_service_category);
	  					_categoryLastSelected = data.category.name_category;
	  					doSave();
	  				}
	  				else{
	  					new PNotify({
			            	title: 'Notificación',
			            	text: 'La categoría ingresada no existe. Selecciónela de la lista o deje el campo vacío.',
			            	type : 'error',
			            	styling : 'bootstrap3',
			            	delay : 3500
			        	});
	  				}
	  			}
	  		});
	  	}
	  	else{

	  		doSave();
	  	}

	  	return false;

	  });
</script>