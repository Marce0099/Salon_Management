<h4 class="section-title">Cuentas del banco registradas</h4>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>Numero de cuenta</th>
			<th>Nombre de cuenta</th>
			<th>Tipo</th>
			<th>Tasa compra</th>
			<th>Tasa venta</th>
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
				<td><?php echo $account->type === 'dolares' ? 'Dólares' : 'Córdobas'; ?></td>
				<td><?php echo $account->buy_rate; ?></td>
				<td><?php echo $account->sell_rate; ?></td>

				<?php if($privilege): ?>
					<td>
						<div class="btn-group">
							<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
							<ul class="dropdown-menu pull-right text-left">
								<li>
									<a class="edit-account-trigger" href="#"
										data-token="<?php echo $account->token_account; ?>"
										data-name="<?php echo $account->name_account; ?>"
										data-type="<?php echo $account->type; ?>"
										data-type-label="<?php echo $account->type === 'dolares' ? 'Dólares' : 'Córdobas'; ?>"
										data-buy="<?php echo $account->buy_rate; ?>"
										data-sell="<?php echo $account->sell_rate; ?>"><i class="fa fa-pencil"></i> Editar</a>
								</li>
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
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="type">Tipo de cuenta:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
			<select name="type" id="type" required="required" class="form-control">
				<option value="cordobas">Córdobas</option>
				<option value="dolares">Dólares</option>
			</select>
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="buy_rate">Tasa de compra:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-pattern="^\d+(\.\d{1,2})?$" name="buy_rate" id="buy_rate" required="required" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="sell_rate">Tasa de venta:
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" data-parsley-pattern="^\d+(\.\d{1,2})?$" name="sell_rate" id="sell_rate" required="required" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
			<button class="btn btn-success" id="btn-submit" type="submit">Guardar</button>
		</div>
	</div>
</form>

<div class="modal fade" id="edit-account-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Editar tasas de cuenta</h4>
			</div>
			<form id="edit-form" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;">
				<div class="modal-body">
					<input type="hidden" name="token_account" id="edit_token_account">
					<div class="form-group">
						<label class="control-label col-md-4 col-sm-4 col-xs-12">Numero de cuenta:</label>
						<div class="col-md-8 col-sm-8 col-xs-12">
							<p class="form-control-static" id="edit_token_account_display"></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-4 col-sm-4 col-xs-12">Nombre de cuenta:</label>
						<div class="col-md-8 col-sm-8 col-xs-12">
							<p class="form-control-static" id="edit_name_account_display"></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-4 col-sm-4 col-xs-12">Tipo de cuenta:</label>
						<div class="col-md-8 col-sm-8 col-xs-12">
							<p class="form-control-static" id="edit_type_display"></p>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-4 col-sm-4 col-xs-12" for="edit_buy_rate">Tasa de compra:
				        </label>
						<div class="col-md-8 col-sm-8 col-xs-12">
				            <input type="text" data-parsley-pattern="^\d+(\.\d{1,2})?$" name="buy_rate" id="edit_buy_rate" required="required" class="form-control">
				        </div>
					</div>
					<div class="form-group">
						<label class="control-label col-md-4 col-sm-4 col-xs-12" for="edit_sell_rate">Tasa de venta:
				        </label>
						<div class="col-md-8 col-sm-8 col-xs-12">
				            <input type="text" data-parsley-pattern="^\d+(\.\d{1,2})?$" name="sell_rate" id="edit_sell_rate" required="required" class="form-control">
				        </div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
					<button class="btn btn-success" id="edit-btn-submit" type="submit">Actualizar</button>
				</div>
			</form>
		</div>
	</div>
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

	$(document).on('click', '.edit-account-trigger', function(e){
		e.preventDefault();
		var $a = $(this);

		$('#edit_token_account').val($a.data('token'));
		$('#edit_token_account_display').text($a.data('token'));
		$('#edit_name_account_display').text($a.data('name'));
		$('#edit_type_display').text($a.data('type-label'));
		$('#edit_buy_rate').val($a.data('buy'));
		$('#edit_sell_rate').val($a.data('sell'));
		$('#edit-account-modal').modal('show');
	});

	$('#edit-form').parsley().on('field:validated', function() {

	    var ok = $('.parsley-error').length === 0;
	    $('.bs-callout-info').toggleClass('hidden', !ok);
	    $('.bs-callout-warning').toggleClass('hidden', ok);

	  })
	  .on('form:submit', function() {

	  	var btn = $('#edit-btn-submit');
	  	var post = $('#edit-form').serializeArray();

	  	btn.attr('disabled','true');

		var setErrorAlert = () => {
			new PNotify({
		            title: 'Notificación',
		            text: 'Ha ocurrido un error actualizando los datos',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 3500
		    });

		    btn.removeAttr('disabled');
		}

	  	$.ajax({

	  		url : '<?php echo base_url('bank_account/update'); ?>',
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