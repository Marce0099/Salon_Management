<h4 class="section-title">Tarifas de comisiones</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="sell_rate">Comisión por venta:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="sell_rate" class="form-control has-feedback-left"  data-parsley-type="number" value="<?php echo $rates->sell_rate; ?>">
	    	<span class="fa fa-percent form-control-feedback left"></span>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="service_rate">Comisión por servicio:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="service_rate" class="form-control has-feedback-left"  data-parsley-type="number" value="<?php echo $rates->service_rate; ?>">
	    	<span class="fa fa-percent form-control-feedback left"></span>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="service_rate">Porcentaje resta POS:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="pos_subtraction" class="form-control has-feedback-left"  data-parsley-type="number" value="<?php echo $rates->pos_subtraction; ?>">
	    	<span class="fa fa-percent form-control-feedback left"></span>
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

	  		url : '<?php echo base_url('employees/save_commissions_rate'); ?>',
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