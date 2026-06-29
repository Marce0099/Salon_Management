<?php 
	
	if( isset($employee) ){

		$url = base_url("employees/edit/{$employee->id_employee}");
	}
	else{

		$url = base_url('employees/register');
		$employee = NULL;
	}
?>

<h4 class="section-title">Formulario de vendedores</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false">
	<?php if( $employee ) : ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="barcode">Código
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <span><?php echo $employee->id_employee; ?></span>
        </div>
	</div>
	<?php endif; ?>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name">Nombre <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="name" id="name" required="required" class="form-control col-md-7 col-xs-12" value="<?php if($employee){ echo $employee->name; } ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="phone">Teléfono
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="phone" id="phone" class="form-control col-md-7 col-xs-12" value="<?php if($employee){ echo $employee->phone; } ?>">
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
</script>