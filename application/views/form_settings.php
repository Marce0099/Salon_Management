<h4 class="section-title">Configuración general</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="vip_discount_rate">Descuento cliente VIP (%):
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="vip_discount_rate" id="vip_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $vip_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="birthday_discount_rate">Descuento cumpleaños cliente (%):
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="birthday_discount_rate" id="birthday_discount_rate" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $birthday_discount_rate; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="birthday_discount_ceiling_min">Monto mínimo de servicios para aplicar descuento de cumpleaños (C$):
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="birthday_discount_ceiling_min" id="birthday_discount_ceiling_min" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $birthday_discount_ceiling_min; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="birthday_discount_ceiling_max">Monto máximo de servicios con descuento de cumpleaños (C$):
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="birthday_discount_ceiling_max" id="birthday_discount_ceiling_max" required="required" class="form-control col-md-7 col-xs-12" value="<?php echo $birthday_discount_ceiling_max; ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="company_name">Nombre de la empresa:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="company_name" id="company_name" required="required" maxlength="255" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($company_name); ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_ruc">RUC del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="receipt_ruc" id="receipt_ruc" required="required" maxlength="255" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($receipt_ruc); ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_address">Dirección del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <textarea name="receipt_address" id="receipt_address" required="required" maxlength="255" rows="3" class="form-control col-md-7 col-xs-12"><?php echo htmlspecialchars($receipt_address); ?></textarea>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_phone">Teléfono del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="receipt_phone" id="receipt_phone" required="required" maxlength="255" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($receipt_phone); ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_tagline">Eslogan del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="receipt_tagline" id="receipt_tagline" required="required" maxlength="255" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($receipt_tagline); ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_footer_notice">Aviso del pie del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="receipt_footer_notice" id="receipt_footer_notice" required="required" maxlength="255" class="form-control col-md-7 col-xs-12" value="<?php echo htmlspecialchars($receipt_footer_notice); ?>">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="receipt_footer_thanks">Agradecimiento del pie del recibo:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <textarea name="receipt_footer_thanks" id="receipt_footer_thanks" required="required" maxlength="255" rows="2" class="form-control col-md-7 col-xs-12"><?php echo htmlspecialchars($receipt_footer_thanks); ?></textarea>
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>

<script>

	$('#form').parsley().on('form:submit', function() {

	  	var btn = $('#btn-submit');
	  	var post = $('#form').serializeArray();

	  	btn.attr('disabled','true');

	  	$.ajax({

	  		url : '<?php echo base_url('settings/save'); ?>',
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

<h4 class="section-title">Logotipos</h4>

<style>
	.logo-group {
		margin-bottom: 30px;
		padding-bottom: 25px;
		border-bottom: 1px solid #e6e9ed;
	}
	.logo-group:last-child {
		border-bottom: 0;
	}
	.logo-preview-row {
		display: flex;
		flex-wrap: wrap;
		gap: 15px;
		margin-bottom: 12px;
	}
	.logo-preview-panel {
		border: 1px solid #e6e9ed;
		border-radius: 4px;
		padding: 8px 12px;
		min-width: 160px;
		text-align: center;
	}
	.logo-preview-panel img {
		max-height: 80px;
		display: block;
		margin: 0 auto;
	}
	.logo-preview-caption {
		display: block;
		font-size: 11px;
		color: #888;
		text-transform: uppercase;
		margin-bottom: 6px;
	}
	.logo-preview-pending {
		border-color: #26b99a;
		display: none;
	}
	.logo-file-input {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0,0,0,0);
		white-space: nowrap;
		border: 0;
	}
	.logo-file-label {
		margin: 0 5px 0 0;
	}
</style>

<form id="form-logos" action="" method="post" class="form-horizontal form-label-left" autocomplete="off" onsubmit="return false;">
	<input type="hidden" id="logo-tk" value="<?php echo $tk; ?>">

	<?php foreach( $logo_groups as $key => $group ) : ?>
	<div class="form-group logo-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12"><?php echo htmlspecialchars($group['label']); ?>:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
			<div class="logo-preview-row">
				<div class="logo-preview-panel">
					<span class="logo-preview-caption">Actual</span>
					<img id="preview-<?php echo $key; ?>" src="<?php echo $group['url']; ?>" class="img-responsive" alt="">
				</div>
				<div class="logo-preview-panel logo-preview-pending" id="pending-panel-<?php echo $key; ?>">
					<span class="logo-preview-caption">Nueva imagen</span>
					<img id="pending-preview-<?php echo $key; ?>" class="img-responsive" alt="">
				</div>
			</div>
            <input type="file" class="logo-file-input" id="logo-file-<?php echo $key; ?>" data-key="<?php echo $key; ?>" accept="image/png,image/jpeg">
            <label for="logo-file-<?php echo $key; ?>" class="btn btn-default btn-sm logo-file-label">Reemplazar</label>
            <button type="button" class="btn btn-success btn-sm logo-upload-btn" data-key="<?php echo $key; ?>">Guardar</button>
        </div>
	</div>
	<?php endforeach; ?>
</form>

<script>

	$('.logo-file-input').on('change', function() {

		var key = $(this).data('key');
		var pendingImg = document.getElementById('pending-preview-' + key);
		var pendingPanel = document.getElementById('pending-panel-' + key);

		if( pendingImg.dataset.blobUrl ){
			URL.revokeObjectURL(pendingImg.dataset.blobUrl);
			delete pendingImg.dataset.blobUrl;
		}

		if( this.files.length ){
			var url = URL.createObjectURL(this.files[0]);
			pendingImg.src = url;
			pendingImg.dataset.blobUrl = url;
			pendingPanel.style.display = 'block';
		}
		else{
			pendingImg.src = '';
			pendingPanel.style.display = 'none';
		}
	});

	$('.logo-upload-btn').on('click', function() {

		var key = $(this).data('key');
		var btn = $(this);
		var input = $('.logo-file-input[data-key="' + key + '"]')[0];

		if( !input.files.length ){

			new PNotify({
	            title: 'Notificación',
	            text: 'Seleccione una imagen primero',
	            type : 'error',
	            styling : 'bootstrap3',
	            delay : 3500
	        });

			return;
		}

		var fd = new FormData();
		fd.append('logo_key', key);
		fd.append('logo_file', input.files[0]);
		fd.append('tk', $('#logo-tk').val());

		btn.attr('disabled','true');

		$.ajax({

	  		url : '<?php echo base_url('settings/uploadLogo'); ?>',
	  		data : fd,
	  		type : 'post',
	  		contentType : false,
	  		processData : false,
	  		dataType : 'json',
	  		success : function(data){

	  			if( data.type === 'success' ){
	  				$('#preview-' + key).attr('src', data.url);
	  				input.value = '';

	  				var pendingImg = document.getElementById('pending-preview-' + key);
	  				if( pendingImg.dataset.blobUrl ){
	  					URL.revokeObjectURL(pendingImg.dataset.blobUrl);
	  					delete pendingImg.dataset.blobUrl;
	  				}
	  				pendingImg.src = '';
	  				document.getElementById('pending-panel-' + key).style.display = 'none';
	  			}

	  			if( data.tk ){
	  				$('#logo-tk').val(data.tk);
	  			}

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
		            text: 'Ha ocurrido un error subiendo la imagen',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 3500
		        });

		        btn.removeAttr('disabled');
	  		}
	  	});
	});
</script>
