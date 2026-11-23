<?php
$is_open = $shift->status === 'open';
$n = count($storages);
$done_n = $n - $pending;
$pct = $n ? round($done_n * 100 / $n) : 0;
$badge = array('open' => '<span class="label label-warning">En curso</span>', 'done' => '<span class="label label-success">Terminado</span>', 'void' => '<span class="label label-default">Anulado</span>');
$is_super = $this->controller->is_super();
?>
<style>
	.sv-card{background:#fff;border:1px solid #ddd;border-left-width:5px;border-radius:6px;padding:10px 12px;margin-bottom:10px;}
	.sv-card.pending{border-left-color:#f0ad4e;} .sv-card.counted{border-left-color:#26b99a;}
	.sv-card h5{margin:0 0 4px;font-size:16px;font-weight:bold;}
	.sv-lines{margin:8px 0 0;font-size:13px;} .sv-lines td,.sv-lines th{padding:3px 6px !important;}
	.diff-pos{color:#26b99a;font-weight:bold;} .diff-neg{color:#d9534f;font-weight:bold;}
</style>

<h3 class="section-title"><?php echo htmlspecialchars($shift->label); ?> <?php echo $badge[$shift->status]; ?>
	<a href="<?php echo base_url('shift_inventory'); ?>" class="btn btn-default btn-sm pull-right">Reportes</a></h3>
<p class="text-muted" style="font-size:13px;">Abierto <?php echo date('d/m/Y h:i A', strtotime($shift->opened_at)); ?> por <?php echo htmlspecialchars((string) $shift->opened_by_name); ?>
	<?php if( $shift->closed_at ) : ?> · <?php echo $shift->status === 'void' ? 'Anulado' : 'Terminado'; ?> <?php echo date('d/m/Y h:i A', strtotime($shift->closed_at)); ?> por <?php echo htmlspecialchars((string) $shift->closed_by_name); ?><?php endif; ?></p>

<div class="progress" style="height:22px;margin-bottom:6px;"><div class="progress-bar progress-bar-success" style="width:<?php echo $pct; ?>%;line-height:22px;"><?php echo $done_n; ?>/<?php echo $n; ?> almacenes</div></div>

<div style="margin:10px 0;">
	<?php if( $is_open && $pending === 0 ) : ?>
		<button class="btn btn-success" id="btn-close"><i class="fa fa-check-circle"></i> Marcar turno como terminado</button>
	<?php elseif( $is_open ) : ?>
		<span class="text-muted">Faltan <strong><?php echo $pending; ?></strong> almacén(es) por inventariar para poder terminar el turno.</span>
	<?php endif; ?>
	<?php if( $shift->status === 'done' ) : ?>
		<a href="<?php echo base_url('shift_inventory/print_report/'.(int) $shift->id_shift); ?>" target="_blank" class="btn btn-primary"><i class="fa fa-print"></i> Imprimir inventario</a>
	<?php endif; ?>
	<?php if( $is_open && $is_super ) : ?>
		<button class="btn btn-danger btn-sm pull-right" id="btn-cancel">Anular turno</button>
	<?php endif; ?>
</div>

<div class="row">
<?php foreach( $storages as $s ) : $ls = isset($lines[(int) $s->id_shift_storage]) ? $lines[(int) $s->id_shift_storage] : array(); ?>
	<div class="col-md-6 col-xs-12">
		<div class="sv-card <?php echo $s->status; ?>">
			<h5><?php echo htmlspecialchars($s->storage_name); ?>
				<?php echo $s->status === 'counted' ? '<span class="label label-success pull-right">Inventariado</span>' : '<span class="label label-warning pull-right">Pendiente</span>'; ?></h5>
			<?php if( $s->status === 'counted' ) : ?>
				<div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars((string) $s->counted_by_name); ?> · <?php echo date('d/m/Y h:i A', strtotime($s->counted_at)); ?> · <strong><?php echo (int) $s->total_units; ?></strong> unidades</div>
				<?php if( $s->is_empty ) : ?><div><em>Almacén vacío</em></div><?php endif; ?>
				<?php if( $s->notes ) : ?><div style="font-size:12px;">Obs.: <?php echo htmlspecialchars($s->notes); ?></div><?php endif; ?>
				<?php if( $ls ) : ?>
				<table class="table table-condensed sv-lines"><thead><tr><th>Producto</th><th>Presentación</th><th class="text-right">Cant.</th><th class="text-right">Unid.</th></tr></thead><tbody>
				<?php foreach( $ls as $l ) : ?>
					<tr><td><?php echo htmlspecialchars($l->product_name); ?><?php echo $l->note ? '<br><small class="text-muted">'.htmlspecialchars($l->note).'</small>' : ''; ?></td>
					<td><?php echo $l->pack_name ? htmlspecialchars($l->pack_name).' (x'.(int) $l->pack_units.')' : 'Unidad'; ?></td>
					<td class="text-right"><?php echo (int) $l->qty; ?></td><td class="text-right"><?php echo (int) $l->base_units; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php endif; ?>
				<?php if( $is_open && $is_super ) : ?><a href="<?php echo base_url('shift_inventory/count/'.(int) $s->id_shift_storage); ?>" class="btn btn-default btn-xs" style="margin-top:6px;">Editar (super)</a><?php endif; ?>
			<?php else : ?>
				<div class="text-muted" style="font-size:12px;">Sin inventariar</div>
				<?php if( $is_open ) : ?><a href="<?php echo base_url('shift_inventory/count/'.(int) $s->id_shift_storage); ?>" class="btn btn-primary btn-sm" style="margin-top:6px;"><i class="fa fa-pencil"></i> Contar este almacén</a><?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; ?>
</div>

<?php if( !empty($totals) ) : ?>
<h4>Total por producto</h4>
<p class="text-muted" style="font-size:12px;">Suma de todos los almacenes en unidades. "Sistema" es el stock actual registrado (solo referencia; la diferencia puede deberse a platillos o ventas posteriores).</p>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Producto</th><th class="text-right">Contado</th><th class="text-right">Sistema</th><th class="text-right">Diferencia</th><th class="text-right">Almacenes</th></tr></thead>
	<tbody>
	<?php foreach( $totals as $t ) : $diff = $t->system_stock === NULL ? NULL : (int) $t->counted - (int) $t->system_stock; ?>
		<tr><td><?php echo htmlspecialchars($t->product_name); ?></td><td class="text-right"><strong><?php echo (int) $t->counted; ?></strong></td>
		<td class="text-right"><?php echo $t->system_stock === NULL ? '—' : (int) $t->system_stock; ?></td>
		<td class="text-right"><?php echo $diff === NULL ? '—' : ($diff == 0 ? '0' : '<span class="'.($diff > 0 ? 'diff-pos' : 'diff-neg').'">'.($diff > 0 ? '+' : '').$diff.'</span>'); ?></td>
		<td class="text-right"><?php echo (int) $t->n_storages; ?></td></tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<input type="hidden" id="sv-tk" value="<?php echo $tk; ?>">
<script>
	function svPost(url, done){
		$.ajax({ url: url, type: 'post', dataType: 'json', data: { tk: $('#sv-tk').val() },
			success: function(r){
				if( r.tk ){ $('#sv-tk').val(r.tk); }
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3000 });
				if( r.type === 'success' && r.redirect ){ setTimeout(function(){ location.href = r.redirect; }, 500); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); }
		});
	}
	$('#btn-close').on('click', function(){
		bootbox.confirm('¿Terminar este inventario de turno? Después no se podrán registrar ni editar almacenes.', function(ok){ if( ok ){ svPost('<?php echo base_url('shift_inventory/close/'.(int) $shift->id_shift); ?>'); } });
	});
	$('#btn-cancel').on('click', function(){
		bootbox.confirm('¿Anular este inventario de turno? Se descartará el reporte en curso.', function(ok){ if( ok ){ svPost('<?php echo base_url('shift_inventory/cancel/'.(int) $shift->id_shift); ?>'); } });
	});
</script>
