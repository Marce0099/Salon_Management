<h3 class="section-title">Listado de vendedores</h3>

<?php if( empty($employees) ) : ?>
<p class="alert alert-info">No hay vendedores registrados</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table bulk_action">
	<thead>
		<tr>
			<th>Nombre</th>
			<th>Teléfono</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $employees as $e ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($e->name); ?></td>
			<td><?php echo htmlspecialchars((string) $e->phone); ?></td>
			<td>
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a href="<?php echo base_url("employees/edit/{$e->id_employee}"); ?>"><i class="fa fa-pencil"></i> Editar</a>
						</li>
						<li class="divider"></li>
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar el vendedor?" href="<?php echo base_url("employees/delete/{$e->id_employee}") . '?tk=' . $tk; ?>"><i class="fa fa-times"></i> Eliminar</a>
						</li>
					</ul>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

