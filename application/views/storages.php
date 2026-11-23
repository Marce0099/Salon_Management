<h3 class="section-title">Almacenes <a href="<?php echo base_url('storages/register'); ?>" class="btn btn-success btn-sm pull-right">Nuevo almacén</a></h3>
<p class="text-muted" style="font-size:13px;">Lugares donde se guarda mercadería (bodega, refrigerador, barra...). Cada turno, los administradores reportan lo que hay en cada uno desde <a href="<?php echo base_url('shift_inventory'); ?>">Inventario de turno</a>. Es solo informativo: no cambia el stock.</p>

<label style="font-weight:normal;"><input type="checkbox" onclick="location.href='<?php echo base_url('storages'); ?>' + (this.checked ? '?inactive=1' : '')" <?php echo $show_inactive ? 'checked' : ''; ?>> Ver desactivados</label>

<?php if( empty($storages) ) : ?>
<p class="alert alert-info" style="margin-top:10px;">No hay almacenes <?php echo $show_inactive ? 'desactivados' : 'registrados'; ?></p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Nombre</th><th>Descripción</th><th>Orden</th><th>Acciones</th></tr></thead>
	<tbody>
	<?php foreach( $storages as $s ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($s->name); ?></td>
			<td><?php echo $s->description === NULL ? '<span class="text-muted">—</span>' : htmlspecialchars($s->description); ?></td>
			<td><?php echo (int) $s->sort_order; ?></td>
			<td>
				<?php if( $s->active ) : ?>
				<a href="<?php echo base_url('storages/edit/'.$s->id_storage); ?>" class="btn btn-default btn-xs">Editar</a>
				<a href="<?php echo base_url('storages/delete/'.$s->id_storage); ?>" class="btn btn-danger btn-xs confirm-action" data-msg="¿Desactivar este almacén? Los reportes anteriores lo conservan.">Desactivar</a>
				<?php else : ?>
				<a href="<?php echo base_url('storages/restore/'.$s->id_storage); ?>" class="btn btn-default btn-xs">Reactivar</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
