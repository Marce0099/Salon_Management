<?php $is_edit = isset($category); ?>
<h4 class="section-title"><?php echo $is_edit ? 'Editar categoría de servicio' : 'Formulario de categoría de servicio'; ?></h4>

<div class="col-md-4">
	<form action="<?php echo $is_edit ? base_url('service_categories/edit/'.$category->id_service_category) : base_url('service_categories/register'); ?>" class="form-horizontal" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false" id="form">
		<div class="form-group">
			<label for="name_category">Categoría:</label>
			<input type="text" name="name_category" id="name_category" required="required" class="form-control" value="<?php echo $is_edit ? $category->name_category : ''; ?>">
		</div>
		<div class="form-group">
			<label for="discount_rate">Descuento (%):</label>
			<div class="tr-input">
				<input type="text" name="discount_rate" id="discount_rate" class="form-control has-feedback-left" data-parsley-type="number" value="<?php echo ($is_edit && $category->discount_rate !== NULL) ? $category->discount_rate : ''; ?>">
				<span class="fa fa-percent form-control-feedback left"></span>
			</div>
			<span class="help-block">Descuento aplicado automáticamente a los servicios de esta categoría al agregarlos a una venta. Dejar en blanco para no aplicar descuento.</span>
		</div>
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<div class="form-group">
			<button class="btn btn-primary" id="btn-submit">Guardar</button>
		</div>
	</form>
</div>

<script>
	var isEdit = <?php echo $is_edit ? 'true' : 'false'; ?>;
	var submitUrl = '<?php echo $is_edit ? base_url('service_categories/edit/'.$category->id_service_category) : base_url('service_categories/register'); ?>';

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

	  		url : submitUrl,
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

		        if( data.type == 'success' ){

		        	if( data.tk ){
		        		$('input[name="tk"]').val(data.tk);
		        	}

		        	if( isEdit ){
		        		window.location = '<?php echo base_url('service_categories'); ?>';
		        	}
		        	else{
		        		$('#name_category').val('');
		        		$('#discount_rate').val('');
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

	  	return false;

	  });
</script>
