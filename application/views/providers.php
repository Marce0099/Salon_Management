<h3 class="section-title">Listado de proveedores</h3>

<?php if( empty($providers) ) : ?>
<p class="alert alert-info">No hay proveedores registrados</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table bulk_action">
	<thead>
		<tr>
			<th>Nombre</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $providers as $p ) : ?>
		<tr>
			<td><?php echo $p->provider; ?></td>
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
