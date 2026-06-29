<?php 
	
	if( isset($provider) ){

		$url = base_url("providers/edit/{$provider->id_provider}");
	}
	else{

		$url = base_url('providers/register');
		$provider = NULL;
	}
?>

<h4 class="section-title">Formulario de proveedores</h4>

<div class="col-md-4">
	<form action="<?php echo $url; ?>" class="form-horizontal" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false" id="form">
		<div class="form-group">
			<label for="provider">Nombre:</span>
	        </label>
			<input type="text" name="provider" id="provider" required="required" class="form-control" value="<?php if($provider) echo $provider->provider; ?>">
		</div>
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
	  	var post = { provider : $('#provider').val() }

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

		        if( data.type == 'success' && !data.edit )
		        	$('#provider').val('');
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