<h3 class="section-title">Clientes eliminados</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda</div>
	<div class="panel-body">
		<form action="<?php echo base_url('/clients/recover'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-4">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label>Cedula</label>
							<input type="text" name="id_card_client" class="form-control" value="<?php echo Easysearch::getValue('id_card_client'); ?>">
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label>Telefono</label>
							<input type="text" name="phone_client" class="form-control" value="<?php echo Easysearch::getValue('phone_client'); ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('/clients/recover'); ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($clients) ) : ?>
<p class="alert alert-info">No hay clientes eliminados</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>Cedula</th>
			<th>Nombre y apellido</th>
			<th>Teléfono</th>
			<th>Email</th>
			<th>Es VIP</th>
			<th>Accion</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $clients as $e ) : ?>
		<tr>
			<td><?php echo $e->id_card_client; ?></td>
			<td><?php echo $e->name_client ." ". $e->surname_client; ?></td>
			<td><?php echo $e->phone_client; ?></td>
			<td><?php echo $e->email_client; ?></td>
			<td><?php echo $e->is_vip_client == 1 ? "Si" : "No"; ?></td>
			<td>
				<a class="confirm-action btn btn-xs btn-success"
				   data-msg="¿Reactivar este cliente?"
				   href="<?php echo base_url("clients/reactivate/{$e->id_client}"); ?>">
					<i class="fa fa-refresh"></i> Reactivar
				</a>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
