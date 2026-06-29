<h3 class="section-title">Detalles de factura</h3>

<table class="table-sale-detail table">
	<thead>
		<tr>
			<th>No. de factura:</th>
			<th>Proveedor</th>
			<th>Fecha:</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><?php echo $invoice->invoice_no; ?></td>
			<td><?php echo $invoice->provider; ?></td>
			<td><?php echo date('d-m-Y',strtotime($invoice->date_time)); ?></td>
		</tr>
	</tbody>
</table>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th width="20%">Código de barra</th>
			<th width="40%">Nombre</th>
			<th width="10%">Cantidad</th>
			<th width="10%">Precio costo</th>
			<th width="10%">Precio venta</th>
			<th width="10%">Valor</th>
		</tr>
	</thead>
	<tbody>
		<?php $total = 0; foreach( $invoice->products as $p ) : ?>
		<tr>
			<td><?php echo $p->barcode; ?></td>
			<td><?php echo $p->name; ?></td>
			<td><?php echo $p->quantity; ?></td>
			<td><?php echo $p->price_cost; ?></td>
			<td><?php echo $p->price_sell; ?></td>
			<?php $subtotal = $p->quantity * $p->price_cost; ?>
			<td><?php echo number_format($subtotal,2); ?></td>
			<?php $total += $subtotal; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<td colspan="6" class="text-right">
			<strong>Total: $ <?php echo number_format($total,2); ?></strong>
		</td>
	</tfoot>
</table>