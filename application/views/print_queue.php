<h3 class="section-title">Cola de impresión <a href="<?php echo base_url('printers'); ?>" class="btn btn-default btn-sm pull-right">Impresoras</a></h3>
<p class="text-muted" style="font-size:13px;">Cada ticket se guarda antes de enviarse. Si la impresora estaba apagada o sin conexión, aquí puede <b>Reintentar</b> exactamente el mismo ticket cuando vuelva; <b>Reimprimir</b> envía otra copia de uno ya impreso.</p>

<ul class="nav nav-pills" style="margin-bottom:10px;">
	<?php foreach( array('' => 'Todos', 'failed' => 'No impresos', 'printed' => 'Impresos', 'pending' => 'Pendientes') as $k => $lbl ) : ?>
	<li class="<?php echo $filter === (string) $k ? 'active' : ''; ?>"><a href="<?php echo base_url('printers/queue'.($k === '' ? '' : '?status='.$k)); ?>"><?php echo $lbl; ?><?php if( $k !== '' && $counts[$k] ) : ?> <span class="badge"><?php echo (int) $counts[$k]; ?></span><?php endif; ?></a></li>
	<?php endforeach; ?>
</ul>

<?php if( empty($jobs) ) : ?>
<p class="alert alert-info">No hay trabajos de impresión<?php echo $filter !== '' ? ' con ese estado' : ''; ?>.</p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>#</th><th>Ticket</th><th>Impresora</th><th>Estado</th><th>Hora</th><th>Acciones</th></tr></thead>
	<tbody>
	<?php foreach( $jobs as $j ) : ?>
		<tr>
			<td><?php echo (int) $j->id_job; ?></td>
			<td><?php echo htmlspecialchars($j->title); ?></td>
			<td><?php echo $j->printer_name === NULL ? '<span class="text-muted">—</span>' : htmlspecialchars($j->printer_name); ?></td>
			<td>
				<?php if( $j->status === 'printed' ) : ?><span class="label label-success">Impreso</span>
				<?php elseif( $j->status === 'failed' ) : ?><span class="label label-danger">No impreso</span> <small class="text-danger"><?php echo htmlspecialchars((string) $j->last_error); ?></small>
				<?php else : ?><span class="label label-default"><?php echo $j->status === 'printing' ? 'Imprimiendo' : 'Pendiente'; ?></span><?php endif; ?>
				<?php if( (int) $j->attempts > 1 ) : ?><small class="text-muted"> (<?php echo (int) $j->attempts; ?> intentos)</small><?php endif; ?>
			</td>
			<td><?php echo date('d/m/Y h:i A', strtotime($j->created_at)); ?></td>
			<td>
				<?php if( $j->status !== 'printed' && (int) $j->size > 0 ) : ?><button type="button" class="btn btn-warning btn-xs js-job" data-act="retry" data-id="<?php echo (int) $j->id_job; ?>">Reintentar</button><?php endif; ?>
				<?php if( (int) $j->size > 0 ) : ?>
				<button type="button" class="btn btn-default btn-xs js-job" data-act="reprint" data-id="<?php echo (int) $j->id_job; ?>"><i class="fa fa-print"></i> Reimprimir</button>
				<a href="<?php echo base_url('printers/preview/'.$j->id_job); ?>" class="btn btn-default btn-xs">Ver</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
<input type="hidden" id="tk" value="<?php echo $tk; ?>">
<script>
	$('.js-job').on('click', function(){
		var b = $(this).prop('disabled', true);
		$.post('<?php echo base_url('printers'); ?>/' + b.data('act'), { tk: $('#tk').val(), id_job: b.data('id') }, function(r){
			new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 5000 });
			setTimeout(function(){ location.reload(); }, 1500);
		}, 'json').fail(function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); });
	});
</script>
