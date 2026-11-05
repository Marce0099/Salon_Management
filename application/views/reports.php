<?php
function rp_usd($n){ return '$'.number_format((float) $n, 2); }
function rp_cor($n){ return 'C$'.number_format((float) $n, 2); }
$print_labels = array('printed' => array('Impresa', 'success'), 'failed' => array('No impresa', 'danger'), 'skipped' => array('Sin cocina', 'default'), 'pending' => array('Pendiente', 'warning'));

/** One breakdown table: rows keyed by name with the blank() money columns. */
function rp_table($title, $rows, $first){ ?>
	<div class="x_panel">
		<div class="x_title"><h2 style="font-size:16px;"><?php echo $title; ?></h2><div class="clearfix"></div></div>
		<?php if( empty($rows) ) : ?><p class="text-muted">Sin ventas en el período.</p><?php else : ?>
		<table class="table table-striped table-condensed">
			<thead><tr><th><?php echo $first; ?></th><th class="text-right">Ventas</th><th class="text-right">Cuentas</th><th class="text-right">Ventas C$</th><th class="text-right">Propina C$</th><th class="text-right">IVA C$</th><th class="text-right">Total C$</th></tr></thead>
			<tbody>
			<?php foreach( $rows as $name => $r ) : ?>
				<tr><td><?php echo htmlspecialchars($name); ?></td><td class="text-right"><?php echo rp_usd($r['ventas_usd']); ?></td><td class="text-right"><?php echo (int) $r['sales']; ?></td>
					<td class="text-right"><?php echo rp_cor($r['ventas_cor']); ?></td><td class="text-right"><?php echo rp_cor($r['tip_cor']); ?></td>
					<td class="text-right"><?php echo rp_cor($r['iva_cor']); ?></td><td class="text-right"><strong><?php echo rp_cor($r['total_cor']); ?></strong></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
<?php } ?>

<div class="page-title" style="margin-bottom:10px;"><h3>Reportes</h3></div>
<div class="clearfix"></div>

<form method="get" action="<?php echo base_url('reports'); ?>" class="form-inline" style="margin-bottom:14px;">
	<label for="r-from">Del</label> <input type="date" id="r-from" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
	<label for="r-to">al</label> <input type="date" id="r-to" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
	<button type="submit" class="btn btn-primary">Ver</button>
	<a class="btn btn-default" href="<?php echo base_url('reports?from='.date('Y-m-d').'&to='.date('Y-m-d')); ?>">Hoy</a>
	<a class="btn btn-default" href="<?php echo base_url('reports?from='.date('Y-m-01').'&to='.date('Y-m-d')); ?>">Este mes</a>
</form>

<div class="row" id="rp-summary">
	<?php foreach( array(
		array('Ventas', rp_cor($summary['ventas_cor']), rp_usd($summary['ventas_usd'])),
		array('Propinas', rp_cor($summary['tip_cor']), rp_usd($summary['tip_usd'])),
		array('IVA', rp_cor($summary['iva_cor']), rp_usd($summary['iva_usd'])),
		array('Total cobrado', rp_cor($summary['total_cor']), rp_usd($summary['total_usd'])),
	) as $t ) : ?>
	<div class="col-md-3 col-sm-6"><div class="panel panel-default kpi-tile"><div class="panel-heading"><?php echo $t[0]; ?></div>
		<div class="panel-body"><div class="kpi-value" style="font-size:24px;"><?php echo $t[1]; ?></div><div class="kpi-sub"><?php echo $t[2]; ?></div></div></div></div>
	<?php endforeach; ?>
</div>
<p class="text-muted">
	<?php echo (int) $summary['sales']; ?> cuenta(s) cobradas
	<?php if( $summary['sales'] > 0 ) : ?> · ticket promedio <?php echo rp_cor($summary['total_cor'] / $summary['sales']); ?><?php endif; ?>
	· Productos anulados: <?php echo (int) $voided->n; ?> (<?php echo rp_usd($voided->usd); ?>)
</p>

<div class="row">
	<div class="col-md-6"><?php rp_table('Ventas y propinas por mesero', $waiters, 'Mesero'); ?></div>
	<div class="col-md-6"><?php rp_table('Ventas por mesa', $tables, 'Mesa'); ?></div>
</div>
<div class="row">
	<div class="col-md-6"><?php rp_table('Ventas por turno', array_filter($shifts, function($r){ return $r['sales'] > 0; }), 'Turno'); ?></div>
	<div class="col-md-6">
		<div class="x_panel">
			<div class="x_title"><h2 style="font-size:16px;">Ventas por categoría</h2><div class="clearfix"></div></div>
			<?php if( empty($categories) ) : ?><p class="text-muted">Sin ventas en el período.</p><?php else : ?>
			<table class="table table-striped table-condensed">
				<thead><tr><th>Categoría</th><th class="text-right">Unidades</th><th class="text-right">Ventas $</th><th class="text-right">Ventas C$</th></tr></thead>
				<tbody><?php foreach( $categories as $c ) : ?>
					<tr><td><?php echo htmlspecialchars($c->cat_name); ?></td><td class="text-right"><?php echo (int) $c->qty; ?></td><td class="text-right"><?php echo rp_usd($c->usd); ?></td><td class="text-right"><?php echo rp_cor($c->cor); ?></td></tr>
				<?php endforeach; ?></tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<div class="x_panel">
			<div class="x_title"><h2 style="font-size:16px;">Lo más vendido</h2><div class="clearfix"></div></div>
			<?php if( empty($top) ) : ?><p class="text-muted">Sin ventas en el período.</p><?php else : ?>
			<table class="table table-striped table-condensed">
				<thead><tr><th>Producto</th><th class="text-right">Unidades</th><th class="text-right">Ventas C$</th></tr></thead>
				<tbody><?php foreach( $top as $p ) : ?><tr><td><?php echo htmlspecialchars($p->name); ?></td><td class="text-right"><?php echo (int) $p->qty; ?></td><td class="text-right"><?php echo rp_cor($p->cor); ?></td></tr><?php endforeach; ?></tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>
	<div class="col-md-6">
		<div class="x_panel">
			<div class="x_title"><h2 style="font-size:16px;">Historial de comandas</h2><div class="clearfix"></div></div>
			<?php if( empty($tickets) ) : ?><p class="text-muted">No se enviaron comandas en el período.</p><?php else : ?>
			<div style="max-height:360px; overflow:auto;">
			<table class="table table-condensed">
				<thead><tr><th>#</th><th>Hora</th><th>Mesa</th><th>Mesero</th><th>Prod.</th><th>Estado</th></tr></thead>
				<tbody><?php foreach( $tickets as $t ) : $pl = $print_labels[$t->print_status]; ?>
					<tr>
						<td><a href="<?php echo base_url('kitchen/ticket/'.(int) $t->id_order); ?>"><?php echo (int) $t->id_order; ?></a></td>
						<td><?php echo date('d/m H:i', strtotime($t->created_at)); ?></td>
						<td><?php echo htmlspecialchars($t->table_label); ?></td>
						<td><?php echo htmlspecialchars((string) $t->waiter); ?></td>
						<td><?php echo (int) $t->items; ?><?php if( $t->voids ) : ?> <span class="text-danger" title="Anulados">(−<?php echo (int) $t->voids; ?>)</span><?php endif; ?></td>
						<td><span class="label label-<?php echo $pl[1]; ?>" title="<?php echo htmlspecialchars((string) $t->print_error); ?>"><?php echo $pl[0]; ?></span></td>
					</tr>
				<?php endforeach; ?></tbody>
			</table></div>
			<?php endif; ?>
		</div>
	</div>
</div>
