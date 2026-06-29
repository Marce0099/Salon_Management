<h4 class="section-title">Cuentas del banco registradas</h4>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>Numero de cuenta</th>
			<th>Nombre de cuenta</th>
			<?php if($privilege): ?>
				<th>Accion</th>
			<?php endif; ?>
		</tr>
	</thead>
	<tbody id="tbody">
		<?php foreach($accounts as $account): ?>
			<tr>
				<td><?php echo $account->token_account; ?></td>
				<td><?php echo $account->name_account; ?></td>

				<?php if($privilege): ?>
					<td>
						<div class="btn-group">
							<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
							<ul class="dropdown-menu pull-right text-left">
								<li>
									<a class="confirm-action" data-msg="Confirma eliminar cuenta bancaria?" href="<?php echo base_url("bank_account/dishabilitate/{$account->token_account }"); ?>"><i class="fa fa-times"></i> Eliminar</a>
								</li>
							</ul>
						</div>
					</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h4 class="section-title">Registrar Cuenta</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="token_account">Numero de cuenta:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-type="number" name="token_account" id="token_account" required="required" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name_account">Descripcion de cuenta:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-pattern="^[a-zA-Z\s]+$" name="name_account" id="name_account" required="required" class="form-control col-md-7 col-xs-12">
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

		var setErrorAlert = () => {
			new PNotify({
		            title: 'Notificación',
		            text: 'Ha ocurrido un error guardando los datos',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 3500
		    });

		    btn.removeAttr('disabled');
		}

	  	$.ajax({

	  		url : '<?php echo base_url('bank_account/save'); ?>',
	  		data : post,
	  		type : 'post',
	  		dataType : 'json',
	  		success : function(data){

				if(data.status.class !== "success"){
					setErrorAlert();
					return;
				}

				location.reload(true);
	  		},
	  		error : function(){
				setErrorAlert();
	  		}
	  	});

	  	return false;

	  });
</script>