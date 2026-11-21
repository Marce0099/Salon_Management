<h3 class="section-title">Detalles de uso interno</h3>

<div class="clearfix">
	<button class="btn btn-success pull-right" onclick="javascript:window.print();"><i class="fa fa-print"></i> Imprimir</button>
</div>

<p>Empleado: <?php echo $internal_use->admin_name; ?> | Fecha: <?php echo date('d/m/Y H:i', strtotime($internal_use->date_time)); ?></p>
<p>Motivo: <?php echo nl2br($internal_use->reason); ?></p>

<table class="table table-bordered jambo_table">
	<thead>
		<tr>
			<th>Producto</th>
			<th>Cantidad</th>
			<th>Precio costo</th>
			<th>Valor</th>
		</tr>
	</thead>
	<tbody>
		<?php $total = 0; foreach( $internal_use->details as $d ) : ?>
		<tr>
			<td><?php echo $d->barcode . ' : ' . $d->name; ?></td>
			<td><?php echo $d->quantity; ?></td>
			<td><?php echo fmt_cor_usd($d->price_cost_cor, $d->price_cost); ?></td>
			<td><?php echo fmt_cor_usd($d->price_cost_cor !== NULL ? $d->quantity * $d->price_cost_cor : NULL, $d->quantity * $d->price_cost); ?></td>
			<?php $total += $d->quantity * $d->price_cost; $total_cor = (isset($total_cor) ? $total_cor : 0) + ($d->price_cost_cor !== NULL ? $d->quantity * $d->price_cost_cor : $d->quantity * $d->price_cost * fx_rate()); ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="4" class="text-right"><strong>Total costo: <?php echo fmt_cor_usd(isset($total_cor) ? $total_cor : NULL, $total); ?></strong></td>
		</tr>
	</tfoot>
</table>
