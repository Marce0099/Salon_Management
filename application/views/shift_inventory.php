<?php
$badge = array('open' => '<span class="label label-warning">En curso</span>', 'done' => '<span class="label label-success">Terminado</span>', 'void' => '<span class="label label-default">Anulado</span>');
?>
<h3 class="section-title">Inventario de turno
	<?php if( $this->controller->is_super() ) : ?><a href="<?php echo base_url('storages'); ?>" class="btn btn-default btn-sm pull-right">Almacenes</a><?php endif; ?></h3>
<p class="text-muted" style="font-size:13px;">Reporte informativo de lo que hay en cada almacén al cambiar de turno. No modifica el stock. Cada administrador cuenta su almacén; el primero en guardar un almacén es el que cuenta.</p>

<?php if( $current ) : ?>
<div class="alert alert-warning">
	<strong>Hay un inventario en curso:</strong> <?php echo htmlspecialchars($current->label); ?> (abierto <?php echo date('d/m/Y h:i A', strtotime($current->opened_at)); ?> por <?php echo htmlspecialchars((string) $current->opened_by_name); ?>)
	<a href="<?php echo base_url('shift_inventory/view/'.(int) $current->id_shift); ?>" class="btn btn-primary btn-sm" style="margin-left:8px;">Abrir</a>
</div>
<?php elseif( !$active_storages ) : ?>
<div class="alert alert-info">No hay almacenes registrados. <?php echo $this->controller->is_super() ? '<a href="'.base_url('storages/register').'">Registre el primero</a>.' : 'Pida al super que los registre en Almacenes.'; ?></div>
<?php else : ?>
<div class="well well-sm" style="background:#fff;">
	<form id="open-form" onsubmit="return false;" class="form-inline">
		<input type="text" name="label" maxlength="80" class="form-control" style="min-width:220px;max-width:100%;" placeholder="Turno <?php echo date('d/m/Y H:i'); ?> (opcional)">
		<input type="hidden" name="tk" id="open-tk" value="<?php echo $tk; ?>">
		<button class="btn btn-success" id="btn-open"><i class="fa fa-clipboard"></i> Iniciar inventario de turno</button>
	</form>
	<small class="text-muted"><?php echo (int) $active_storages; ?> almacén(es) activo(s) se incluirán en el reporte.</small>
</div>
<?php endif; ?>

<h4>Reportes</h4>
<?php if( empty($history) ) : ?>
<p class="alert alert-info">Aún no hay reportes.</p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>#</th><th>Turno</th><th>Estado</th><th>Almacenes</th><th>Unidades</th><th>Abierto</th><th>Cerrado</th><th></th></tr></thead>
	<tbody>
	<?php foreach( $history as $h ) : ?>
		<tr>
			<td><?php echo (int) $h->id_shift; ?></td>
			<td><?php echo htmlspecialchars($h->label); ?></td>
			<td><?php echo $badge[$h->status]; ?></td>
			<td><?php echo (int) $h->n_counted; ?>/<?php echo (int) $h->n_storages; ?></td>
			<td><?php echo (int) $h->total_units; ?></td>
			<td><?php echo date('d/m/Y h:i A', strtotime($h->opened_at)); ?><br><small><?php echo htmlspecialchars((string) $h->opened_by_name); ?></small></td>
			<td><?php echo $h->closed_at ? date('d/m/Y h:i A', strtotime($h->closed_at)).'<br><small>'.htmlspecialchars((string) $h->closed_by_name).'</small>' : '<span class="text-muted">—</span>'; ?></td>
			<td><a href="<?php echo base_url('shift_inventory/view/'.(int) $h->id_shift); ?>" class="btn btn-default btn-xs">Ver</a>
				<?php if( $h->status === 'done' ) : ?><a href="<?php echo base_url('shift_inventory/print_report/'.(int) $h->id_shift); ?>" target="_blank" class="btn btn-default btn-xs"><i class="fa fa-print"></i></a><?php endif; ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<script>
	$('#btn-open').on('click', function(e){
		e.preventDefault();
		var b = $(this).prop('disabled', true);
		$.ajax({ url: '<?php echo base_url('shift_inventory/open'); ?>', type: 'post', dataType: 'json', data: $('#open-form').serialize(),
			success: function(r){
				if( r.tk ){ $('#open-tk').val(r.tk); }
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3000 });
				if( r.type === 'success' && r.redirect ){ location.href = r.redirect; } else { b.prop('disabled', false); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); }
		});
	});
</script>
