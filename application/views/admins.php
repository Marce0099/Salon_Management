<h4 class="section-title">Usuarios registrados</h4>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>Usuario</th>
			<th>Nombre</th>
			<th>Rol</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($admins as $a): ?>
			<tr>
				<td><?php echo $a->username; ?></td>
				<td><?php echo $a->name; ?></td>
				<td><?php echo $a->role === 'administrador' ? 'Administrador' : 'Trabajador'; ?></td>
				<td>
					<div class="btn-group">
						<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
						<ul class="dropdown-menu pull-right text-left">
							<li>
								<a class="confirm-action" data-msg="Confirma eliminar este usuario?" href="<?php echo base_url("admins/delete/{$a->id_admin}"); ?>"><i class="fa fa-times"></i> Eliminar</a>
							</li>
						</ul>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h4 class="section-title">Registrar Usuario</h4>

<form id="form" action="" method="post" class="form-horizontal form-label-left" data-parsley-validate autocomplete="off" onsubmit="return false;" spellcheck="false">
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="username">Usuario <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="username" id="username" required="required" maxlength="50" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="name">Nombre <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="text" name="name" id="name" required="required" maxlength="100" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="password">Contraseña <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="password" name="password" id="password" required="required" minlength="6" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="password_confirm">Confirmar contraseña <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
            <input type="password" name="password_confirm" id="password_confirm" required="required" data-parsley-equalto="#password" class="form-control col-md-7 col-xs-12">
        </div>
	</div>
	<div class="form-group">
		<label class="control-label col-md-3 col-sm-3 col-xs-12" for="role">Rol <span class="required">*</span>
        </label>
		<div class="col-md-6 col-sm-6 col-xs-12">
			<select name="role" id="role" required="required" class="form-control">
				<option value="trabajador">Trabajador</option>
				<option value="administrador">Administrador</option>
			</select>
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

	  		url : '<?php echo base_url('admins/save'); ?>',
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

		        if( data.type == 'success' )
		        	location.reload(true);
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
