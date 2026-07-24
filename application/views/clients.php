<h3 class="section-title">Listado de clientes</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('/clients'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">

			<div class="col-md-4">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Cedula</label>
							<input type="text" name="id_card_client" class="form-control" value="<?php echo Easysearch::getValue('id_card_client'); ?>">
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Telefono</label>
							<input type="text" name="phone_client" class="form-control" value="<?php echo Easysearch::getValue('phone_client'); ?>">
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="">VIP</label>
							<select name="is_vip_client" class="form-control">
								<option value="">--</option>
								<option value="1" <?php echo Easysearch::getValue('is_vip_client')==1?"selected":""; ?>>Si</option>
								<option value="0" <?php echo Easysearch::getValue('is_vip_client')==0?"selected":""; ?>>No</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('/clients') ?>" class="btn btn-default">Reiniciar</a>
				<a href="<?php echo base_url('/clients/recover') ?>" class="btn btn-default">Recuperar</a>
			</div>
		</form>
	</div>
</div> <!-- herramienta de busqueda -->

<?php if( empty($clients) ) : ?>
<p class="alert alert-info">No hay clientes registrados</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table bulk_action">
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
			<td><?php echo $e->id_card_client ; ?></td>
			<td><?php echo $e->name_client ." ". $e->surname_client; ?></td>
			<td><?php echo $e->phone_client; ?></td>
			<td><?php echo $e->email_client; ?></td>
			<td><?php echo $e->is_vip_client == 1?"Si":"No"; ?></td>
			<td>
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a href="<?php echo base_url("clients/edit/{$e->id_client }"); ?>"><i class="fa fa-pencil"></i> Editar</a>
						</li>
						<li class="divider"></li>
						<li>
							<a href="<?php echo base_url("loyalty_points/registry/{$e->id_client }"); ?>"><i class="fa fa-star"></i> Registro de puntos</a>
						</li>
						<li class="divider"></li>
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar el cliente?" href="<?php echo base_url("clients/delete/{$e->id_client }"); ?>"><i class="fa fa-times"></i> Eliminar</a>
						</li>
					</ul>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if( !empty($pagination) ) : ?>
<div class="panel-footer">
	<?php echo $pagination; ?>
</div>
<?php endif; ?>
<?php endif; ?>

