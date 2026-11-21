<h3 class="section-title">Detalles de factura</h3>

<?php if( $this->controller->is_admin() ) : ?>
<a href="<?php echo base_url('returns/register?invoice='.(int)$invoice->id_invoice); ?>" class="btn btn-warning pull-right"><i class="fa fa-undo"></i> Devolver</a>
<?php endif; ?>

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
			<td><?php echo $p->quantity; ?><?php if( !empty($p->pack_name) ): ?> <small class="text-muted">(<?php echo (int)$p->pack_qty; ?> &times; <?php echo htmlspecialchars($p->pack_name); ?> de <?php echo (int)$p->pack_units; ?> u)</small><?php endif; ?></td>
			<td><?php echo fmt_cor_usd($p->price_cost_cor, $p->price_cost); ?></td>
			<td><?php echo fmt_cor_usd($p->price_sell_cor, $p->price_sell); ?></td>
			<?php $subtotal = $p->quantity * $p->price_cost; $subtotal_cor = $p->price_cost_cor !== NULL ? $p->quantity * $p->price_cost_cor : NULL; ?>
			<td><?php echo fmt_cor_usd($subtotal_cor, $subtotal); ?></td>
			<?php $total += $subtotal; $total_cor = isset($total_cor) ? $total_cor : 0; $total_cor = ($subtotal_cor !== NULL && $total_cor !== NULL) ? $total_cor + $subtotal_cor : NULL; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
		<td colspan="6" class="text-right">
			<strong>Total: <?php echo fmt_cor_usd($total_cor, $total); ?></strong>
		</td>
	</tfoot>
</table>