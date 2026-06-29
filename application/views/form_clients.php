<?php 
	
	if( isset($client) ){

		$url = base_url("clients/edit/{$client->id_client}");
	}
	else{

		$url = base_url('clients/register');
		$client = NULL;
	}
?>

<h4 class="section-title">Formulario de cliente</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false">
	<?php if( $client ) : ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="barcode">Código
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <span><?php echo $client->id_client; ?></span>
        </div>
	</div>
	<?php endif; ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="id_card_client">Cedula
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="id_card_client" id="id_card_client"  class="form-control col-md-7 col-xs-12" value="<?php if($client){ echo $client->id_card_client; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name_client">Nombre <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="name_client" id="name_client" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($client){ echo $client->name_client; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="surname_client">Apellido <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="surname_client" id="surname_client" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($client){ echo $client->surname_client; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="phone_client">Teléfono
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="phone_client" id="phone_client" class="form-control col-md-7 col-xs-12" value="<?php if($client){ echo $client->phone_client; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="email_client">Email
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="email_client" id="email_client" class="form-control col-md-7 col-xs-12" value="<?php if($client){ echo $client->email_client; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="birth_client">Fecha de nacimiento <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="birth_client" id="birth_client" required="required" class="dtpicker form-control col-md-7 col-xs-12" value="<?php if($client){ echo $this->clients_mdl->getDateFormat($client->birth_client); } ?>">
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>

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
		        	$('#form input').val('');
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
			minDate : '01/01/1900',
			singleDatePicker: true,
			showDropdowns: true
		});

		if(window.location.pathname.includes('register')){
			$('.dtpicker').val('');
		}
		
	});
</script>