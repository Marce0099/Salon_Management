<h3 class="section-title">Listado de proveedores</h3>

<form action="<?php echo base_url('/providers'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false" style="margin-bottom:15px;">
	<div class="row">
		<div class="col-md-3">
			<input type="text" name="provider" class="form-control" placeholder="Empresa" value="<?php echo Easysearch::getValue('provider'); ?>">
		</div>
		<div class="col-md-3">
			<input type="text" name="representative" class="form-control" placeholder="Representante" value="<?php echo Easysearch::getValue('representative'); ?>">
		</div>
		<div class="col-md-2">
			<input type="text" name="phone_provider" class="form-control" placeholder="Teléfono" value="<?php echo Easysearch::getValue('phone_provider'); ?>">
		</div>
		<div class="col-md-2">
			<input type="text" name="email_provider" class="form-control" placeholder="Email" value="<?php echo Easysearch::getValue('email_provider'); ?>">
		</div>
		<div class="col-md-2">
			<button type="submit" class="btn btn-default">Buscar</button>
			<a href="<?php echo base_url('/providers'); ?>" class="btn btn-default">Reiniciar</a>
		</div>
	</div>
</form>

<?php if( empty($providers) ) : ?>
<p class="alert alert-info">No hay proveedores registrados</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table bulk_action">
	<thead>
		<tr>
			<th>Empresa</th>
			<th>Representante</th>
			<th>Teléfono</th>
			<th>Email</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $providers as $p ) : ?>
		<tr>
			<td><?php echo $p->provider; ?></td>
			<td><?php echo $p->representative; ?></td>
			<td><?php echo $p->phone_provider; ?></td>
			<td><?php echo $p->email_provider; ?></td>
			<td>
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a href="<?php echo base_url("providers/edit/{$p->id_provider}"); ?>"><i class="fa fa-pencil"></i> Editar</a>
						</li>
						<li class="divider"></li>
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar el proveedor?" href="<?php echo base_url("providers/delete/{$p->id_provider}"); ?>"><i class="fa fa-times"></i> Eliminar</a>
						</li>
					</ul>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
