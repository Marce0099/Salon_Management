<h4 class="section-title">Formulario de categoría de servicio</h4>

<div class="col-md-4">
	<form action="<?php echo base_url('service_categories/register'); ?>" class="form-horizontal" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false" id="form">
		<div class="form-group">
			<label for="name_category">Categoría:</label>
			<input type="text" name="name_category" id="name_category" required="required" class="form-control">
		</div>
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<div class="form-group">
			<button class="btn btn-primary" id="btn-submit">Guardar</button>
		</div>
	</form>
</div>

<script>
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

	  		url : '<?php echo base_url('service_categories/register'); ?>',
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
		        	$('#name_category').val('');

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

	  	return false;

	  });
</script>
