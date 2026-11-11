<h3 class="section-title">Modificadores <a href="<?php echo base_url('modifiers/register'); ?>" class="btn btn-success btn-sm pull-right">Nuevo modificador</a></h3>
<p class="text-muted" style="font-size:13px;">Opciones que el mesero marca en un platillo ("Sin queso", "Término medio"). Salen en la comanda y, si quitan ingredientes, esa porción descuenta menos del inventario. No cambian el precio. Se asignan a cada platillo desde su <strong>Receta</strong>.</p>

<label style="font-weight:normal;"><input type="checkbox" onclick="location.href='<?php echo base_url('modifiers'); ?>' + (this.checked ? '?inactive=1' : '')" <?php echo $show_inactive ? 'checked' : ''; ?>> Ver desactivados</label>

<?php if( empty($modifiers) ) : ?>
<p class="alert alert-info" style="margin-top:10px;">No hay modificadores <?php echo $show_inactive ? 'desactivados' : 'registrados'; ?></p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Nombre</th><th>Texto en comanda</th><th>Quita del inventario</th><th>Acciones</th></tr></thead>
	<tbody>
	<?php foreach( $modifiers as $m ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($m->name); ?></td>
			<td><?php echo htmlspecialchars($m->kitchen_text); ?></td>
			<td><?php echo isset($summaries[(int) $m->id_modifier]) ? htmlspecialchars(implode(', ', $summaries[(int) $m->id_modifier])) : '<span class="text-muted">Solo texto</span>'; ?></td>
			<td>
				<?php if( $m->active ) : ?>
				<a href="<?php echo base_url('modifiers/edit/'.$m->id_modifier); ?>" class="btn btn-default btn-xs">Editar</a>
				<a href="<?php echo base_url('modifiers/delete/'.$m->id_modifier); ?>" class="btn btn-danger btn-xs confirm-action" data-msg="¿Desactivar este modificador?">Desactivar</a>
				<?php else : ?>
				<a href="<?php echo base_url('modifiers/restore/'.$m->id_modifier); ?>" class="btn btn-default btn-xs">Reactivar</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
