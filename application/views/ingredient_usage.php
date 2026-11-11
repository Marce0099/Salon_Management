<?php
$n = function($x){ return rtrim(rtrim(number_format((float) $x, 3, '.', ','), '0'), '.') ?: '0'; };
$tot_cost = 0.0; $tot_waste_cost = 0.0; $tot_count_cost = 0.0;
foreach( $rows as $r ){
	if( $r->cost_unit !== NULL ){
		$tot_cost += $r->consumed * $r->cost_unit;
		$tot_waste_cost += $r->waste * $r->cost_unit;
		$tot_count_cost += $r->counted * $r->cost_unit;
	}
}
?>
<h3 class="section-title">Consumo de ingredientes <a href="<?php echo base_url('ingredients'); ?>" class="btn btn-default btn-sm pull-right">Volver</a></h3>

<form method="get" action="<?php echo base_url('ingredients/usage'); ?>" class="form-inline" style="margin-bottom:14px;">
	<label for="u-from">Del</label> <input type="date" id="u-from" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
	<label for="u-to">al</label> <input type="date" id="u-to" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
	<button type="submit" class="btn btn-primary">Ver</button>
</form>

<p class="text-muted" style="font-size:13px;">
	<strong>Consumo teórico</strong> = lo que las recetas descontaron en pedidos, menos anulaciones. <strong>Merma</strong> = salidas registradas como desperdicio.
	<strong>Diferencia de conteo</strong> = ajustes por conteo físico (negativo = hay menos en bodega de lo que el sistema decía: faltante no explicado).
</p>

<?php if( empty($rows) ) : ?>
<p class="alert alert-info">Sin movimientos de ingredientes en el período.</p>
<?php else : ?>
<div class="row" style="margin-bottom:10px;">
	<div class="col-xs-12 col-sm-4"><div class="alert alert-info" style="margin-bottom:6px;">Costo del consumo teórico: <strong>$<?php echo number_format($tot_cost, 2); ?></strong></div></div>
	<div class="col-xs-12 col-sm-4"><div class="alert alert-warning" style="margin-bottom:6px;">Costo de mermas: <strong>$<?php echo number_format($tot_waste_cost, 2); ?></strong></div></div>
	<div class="col-xs-12 col-sm-4"><div class="alert <?php echo $tot_count_cost < 0 ? 'alert-danger' : 'alert-success'; ?>" style="margin-bottom:6px;">Diferencia de conteo: <strong><?php echo ($tot_count_cost < 0 ? '-' : '').'$'.number_format(abs($tot_count_cost), 2); ?></strong></div></div>
</div>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>#</th><th>Ingrediente</th><th class="text-right">Consumo teórico</th><th class="text-right">Costo</th><th class="text-right">Merma</th><th class="text-right">Diferencia de conteo</th><th class="text-right">Entradas</th><th class="text-right">Existencia</th></tr></thead>
	<tbody>
	<?php foreach( $rows as $i => $r ) : ?>
		<tr>
			<td><?php echo $i + 1; ?></td>
			<td><a href="<?php echo base_url('ingredients/movements/'.$r->id_ingredient); ?>"><?php echo htmlspecialchars($r->name); ?></a></td>
			<td class="text-right"><strong><?php echo $n($r->consumed).' '.$r->unit; ?></strong></td>
			<td class="text-right"><?php echo $r->cost_unit === NULL ? '<span class="text-muted">—</span>' : '$'.number_format($r->consumed * $r->cost_unit, 2); ?></td>
			<td class="text-right <?php echo $r->waste > 0 ? 'text-warning' : ''; ?>"><?php echo $n($r->waste).' '.$r->unit; ?></td>
			<td class="text-right <?php echo $r->counted < 0 ? 'text-danger' : ( $r->counted > 0 ? 'text-success' : '' ); ?>"><?php echo ($r->counted > 0 ? '+' : '').$n($r->counted).' '.$r->unit; ?></td>
			<td class="text-right"><?php echo $n($r->received).' '.$r->unit; ?></td>
			<td class="text-right"><?php echo $n($r->stock).' '.$r->unit; ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
