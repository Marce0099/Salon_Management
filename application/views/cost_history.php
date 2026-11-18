<?php
	$money = function($v){ return ($v === NULL || $v === '') ? '—' : '$'.number_format((float)$v, 2); };
	$money4 = function($v){ return ($v === NULL || $v === '') ? '—' : '$'.preg_replace('/(\.\d\d)0+$|(\.\d\d\d)0$/', '$1$2', number_format((float)$v, 4, '.', '')); };
	$labels = array('purchase' => 'Compra', 'manual' => 'Edición manual', 'import' => 'Importación');
	$qs = function($extra) use ($f){ return base_url('cost_history').'?'.http_build_query(array_merge(array('id_provider' => $f['id_provider'] ?: '', 'source' => $f['source'], 'from' => $f['from'], 'to' => $f['to'], 'only_changes' => $f['only_changes'] ?: ''), $extra)); };
?>
<h3 class="section-title">Historial de costos<?php if( $product ) echo ': '.htmlspecialchars($product->name); ?></h3>
<p class="text-muted">Cada compra y cada cambio manual del costo queda registrado. Úsalo para ver el costo promedio y el más alto antes de cambiar un precio de venta. Costos en dólares por unidad.</p>

<div class="panel panel-default">
	<div class="panel-heading">Filtros</div>
	<div class="panel-body">
		<form action="<?php echo base_url('cost_history'); ?>" method="get" autocomplete="off">
			<div class="row">
				<div class="col-md-3 form-group">
					<label for="id_product">Producto</label>
					<select name="id_product" id="id_product" class="form-control">
						<option value="">Todos (resumen)</option>
						<?php foreach( $products as $p ) : ?>
						<option value="<?php echo (int)$p->id_product; ?>" <?php if( (int)$p->id_product === $f['id_product'] ) echo 'selected'; ?>><?php echo htmlspecialchars($p->name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3 form-group">
					<label for="id_provider">Proveedor</label>
					<select name="id_provider" id="id_provider" class="form-control">
						<option value="">---</option>
						<?php foreach( $providers as $p ) : ?>
						<option value="<?php echo (int)$p->id_provider; ?>" <?php if( (int)$p->id_provider === $f['id_provider'] ) echo 'selected'; ?>><?php echo htmlspecialchars($p->provider); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 form-group">
					<label for="source">Origen</label>
					<select name="source" id="source" class="form-control">
						<option value="">Todos</option>
						<?php foreach( $labels as $k => $l ) : ?>
						<option value="<?php echo $k; ?>" <?php if( $f['source'] === $k ) echo 'selected'; ?>><?php echo $l; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 form-group">
					<label for="from">Desde</label>
					<input type="date" name="from" id="from" class="form-control" value="<?php echo $f['from']; ?>">
				</div>
				<div class="col-md-2 form-group">
					<label for="to">Hasta</label>
					<input type="date" name="to" id="to" class="form-control" value="<?php echo $f['to']; ?>">
				</div>
			</div>
			<div class="checkbox">
				<label><input type="checkbox" name="only_changes" value="1" <?php if( $f['only_changes'] ) echo 'checked'; ?>> Solo compras donde el costo cambió</label>
			</div>
			<button type="submit" class="btn btn-primary">Buscar</button>
			<a href="<?php echo base_url('cost_history'); ?>" class="btn btn-default">Reiniciar</a>
		</form>
	</div>
</div>

<?php if( $product ) : ?>

	<?php if( !$stats ) : ?>
		<div class="alert alert-info">No hay registros de costo para este producto con los filtros elegidos.</div>
	<?php else :
		$cur = (float)$product->price_cost; $sell = (float)$product->price_sell;
		$margin = function($cost) use ($sell){ return ($sell > 0 && $cost !== NULL) ? number_format(($sell - $cost) / $sell * 100, 0).'%' : '—'; };
	?>
	<div class="row">
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Costo actual</small><h4><?php echo $money4($cur); ?></h4></div></div>
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Promedio ponderado</small><h4><?php echo $money4($stats->avg_cost); ?></h4><small>margen <?php echo $margin($stats->avg_cost); ?></small></div></div>
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Más alto</small><h4><?php echo $money4($stats->max_cost); ?></h4><small>margen <?php echo $margin($stats->max_cost); ?></small></div></div>
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Más bajo</small><h4><?php echo $money4($stats->min_cost); ?></h4></div></div>
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Precio de venta</small><h4><?php echo $money($sell); ?></h4></div></div>
		<div class="col-md-2 col-xs-6"><div class="well well-sm text-center"><small>Registros</small><h4><?php echo (int)$stats->n; ?></h4></div></div>
	</div>

	<div class="table-responsive">
	<table class="table table-hover table-striped jambo_table table-bordered">
		<thead><tr><th>Fecha</th><th>Origen</th><th>Factura</th><th>Proveedor</th><th class="text-right">Unidades</th><th class="text-right">Costo anterior</th><th class="text-right">Costo comprado</th><th class="text-right">Diferencia</th><th class="text-right">Precio venta</th><th>Usuario</th></tr></thead>
		<tbody>
		<?php foreach( $rows as $r ) :
			$diff = $r->old_cost !== NULL ? (float)$r->new_cost - (float)$r->old_cost : NULL;
			$pct = ( $diff !== NULL && (float)$r->old_cost > 0 ) ? $diff / (float)$r->old_cost * 100 : NULL;
		?>
			<tr>
				<td><?php echo date('d/m/Y H:i', strtotime($r->created_at)); ?></td>
				<td><?php echo $labels[$r->source]; ?></td>
				<td><?php if( $r->id_invoice ) : ?><a href="<?php echo base_url('invoices/details/'.(int)$r->id_invoice); ?>"><?php echo htmlspecialchars($r->invoice_no); ?></a><?php else : ?>—<?php endif; ?></td>
				<td><?php echo $r->provider !== NULL ? htmlspecialchars($r->provider) : '—'; ?></td>
				<td class="text-right"><?php echo $r->quantity !== NULL ? (int)$r->quantity : '—'; ?></td>
				<td class="text-right"><?php echo $money4($r->old_cost); ?></td>
				<td class="text-right"><strong><?php echo $money4($r->new_cost); ?></strong></td>
				<td class="text-right"><?php if( $diff === NULL ) : ?>—<?php elseif( abs($diff) < 0.00005 ) : ?><span class="text-muted">sin cambio</span><?php else : ?><span class="<?php echo $diff > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo ($diff > 0 ? '+' : '-').$money4(abs($diff)); ?><?php if( $pct !== NULL ) echo ' ('.($diff > 0 ? '+' : '-').number_format(abs($pct), 1).'%)'; ?></span><?php endif; ?></td>
				<td class="text-right"><?php echo $money4($r->new_sell); ?></td>
				<td><?php echo $r->username !== NULL ? htmlspecialchars($r->username) : '—'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<p class="text-muted"><small>Promedio ponderado = suma(costo × unidades) / unidades de las compras del rango. Se muestran hasta 500 registros.</small></p>
	<?php endif; ?>

<?php else : ?>

	<?php if( empty($summary) ) : ?>
		<div class="alert alert-info">No hay registros de costo con los filtros elegidos.</div>
	<?php else : ?>
	<div class="table-responsive">
	<table class="table table-hover table-striped jambo_table table-bordered">
		<thead><tr><th>Producto</th><th class="text-right">Registros</th><th class="text-right">Costo actual</th><th class="text-right">Promedio</th><th class="text-right">Más alto</th><th class="text-right">Más bajo</th><th class="text-right">Último</th><th>Última fecha</th><th></th></tr></thead>
		<tbody>
		<?php foreach( $summary as $s ) : ?>
			<tr>
				<td><?php echo htmlspecialchars($s->name); ?></td>
				<td class="text-right"><?php echo (int)$s->n; ?></td>
				<td class="text-right"><?php echo $money4($s->current_cost); ?></td>
				<td class="text-right"><?php echo $money4($s->avg_cost); ?></td>
				<td class="text-right"><?php echo $money4($s->max_cost); ?></td>
				<td class="text-right"><?php echo $money4($s->min_cost); ?></td>
				<td class="text-right"><?php echo $money4($s->last_cost); ?></td>
				<td><?php echo date('d/m/Y', strtotime($s->last_date)); ?></td>
				<td><a class="btn btn-xs btn-default" href="<?php echo $qs(array('id_product' => (int)$s->id_product)); ?>">Ver historial</a></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php endif; ?>

<?php endif; ?>
