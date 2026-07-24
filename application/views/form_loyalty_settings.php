<h4 class="section-title">Programa de puntos de fidelidad</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="enabled">Programa activo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="checkbox" name="enabled" id="enabled" value="1" <?php echo $settings->enabled ? 'checked' : ''; ?>>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="points_ceiling">Tope de puntos:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="points_ceiling" id="points_ceiling" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $settings->points_ceiling; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="tier1_discount_rate">Etapa 1 (<span class="tier-threshold" data-n="1"></span> pts) — % descuento:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="tier1_discount_rate" id="tier1_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $settings->tier1_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="tier2_discount_rate">Etapa 2 (<span class="tier-threshold" data-n="2"></span> pts) — % descuento:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="tier2_discount_rate" id="tier2_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $settings->tier2_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="tier3_discount_rate">Etapa 3 (<span class="tier-threshold" data-n="3"></span> pts) — % descuento:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="tier3_discount_rate" id="tier3_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $settings->tier3_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="tier4_discount_rate">Etapa 4 (<span class="tier-threshold" data-n="4"></span> pts) — % descuento:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="tier4_discount_rate" id="tier4_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $settings->tier4_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>

<script>

	function refreshTierThresholds(){

		var ceiling = numeral( $('#points_ceiling').val() || 0 ).value();

		$('.tier-threshold').each(function(){
			var n = $(this).data('n');
			$(this).text( numeral( ceiling * n / 4 ).format('0,0') );
		});
	}

	$('#points_ceiling').on('keyup change', refreshTierThresholds);

	$(document).on('ready', refreshTierThresholds);

	$('#form').parsley().on('field:validated', function() {

	    var ok = $('.parsley-error').length === 0;
	    $('.bs-callout-info').toggleClass('hidden', !ok);
	    $('.bs-callout-warning').toggleClass('hidden', ok);

	  })
	  .on('form:submit', function() {

	  	var btn = $('#btn-submit');
	  	var post = $('#form').serializeArray();

	  	if( $('#enabled').is(':checked') ){
	  		post.push({ name : 'enabled', value : 1 });
	  	}

	  	btn.attr('disabled','true');

	  	$.ajax({

	  		url : '<?php echo base_url('loyalty_points/save'); ?>',
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
