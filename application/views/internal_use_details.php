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
			<td>$<?php echo number_format($d->price_cost,2); ?></td>
			<td>$<?php echo number_format( $d->quantity * $d->price_cost, 2 ); ?></td>
			<?php $total += $d->quantity * $d->price_cost; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<tr>
			<td colspan="4" class="text-right"><strong>Total costo: $<?php echo number_format($total,2); ?></strong></td>
		</tr>
	</tfoot>
</table>
