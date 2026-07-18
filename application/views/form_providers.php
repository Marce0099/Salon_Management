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
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<div class="form-group">
			<label for="provider">Empresa:</label>
			<input type="text" name="provider" id="provider" class="form-control" value="<?php if($provider) echo $provider->provider; ?>">
		</div>
		<div class="form-group">
			<label for="representative">Representante:</label>
			<input type="text" name="representative" id="representative" required="required" class="form-control" value="<?php if($provider) echo $provider->representative; ?>">
		</div>
		<div class="form-group">
			<label for="phone_provider">Teléfono:</label>
			<input type="text" name="phone_provider" id="phone_provider" class="form-control" value="<?php if($provider) echo $provider->phone_provider; ?>">
		</div>
		<div class="form-group">
			<label for="email_provider">Email:</label>
			<input type="text" name="email_provider" id="email_provider" class="form-control" value="<?php if($provider) echo $provider->email_provider; ?>">
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
	  	var post = {
	  		provider       : $('#provider').val(),
	  		representative : $('#representative').val(),
	  		phone_provider : $('#phone_provider').val(),
	  		email_provider : $('#email_provider').val(),
	  		tk             : $('input[name="tk"]').val()
	  	};

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
		        	$('#provider').val('');
		        	$('#representative').val('');
		        	$('#phone_provider').val('');
		        	$('#email_provider').val('');
		        }

		        if( data.tk ){
		        	$('input[name="tk"]').val(data.tk);
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