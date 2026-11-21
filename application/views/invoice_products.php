<h3 class="section-title">Detalles de factura</h3>

<?php if( $this->controller->is_admin() ) : ?>
<a href="<?php echo base_url('returns/register?invoice='.(int)$invoice->id_invoice); ?>" class="btn btn-warning pull-right"><i class="fa fa-undo"></i> Devolver</a>
<?php endif; ?>
<div class="clearfix"></div>

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
			<th width="14%">Código de barra</th>
			<th width="28%">Nombre</th>
			<th width="14%">Cantidad</th>
			<th width="15%">Precio costo</th>
			<th width="15%">Precio venta</th>
			<th width="14%">Valor</th>
		</tr>
	</thead>
	<tbody>
		<?php $total_cor = 0; foreach( $invoice->products as $p ) : ?>
		<tr>
			<td><?php echo $p->barcode; ?></td>
			<td><?php echo $p->name; ?></td>
			<td><?php echo $p->quantity; ?><?php if( !empty($p->pack_name) ): ?> <small class="text-muted">(<?php echo (int)$p->pack_qty; ?> &times; <?php echo htmlspecialchars($p->pack_name); ?> de <?php echo (int)$p->pack_units; ?> u)</small><?php endif; ?></td>
			<td><?php echo fmt_cor_unit($p->price_cost_cor); ?></td>
			<td><?php echo fmt_cor_pair($p->price_sell_cor); ?></td>
			<?php $subtotal_cor = $p->price_cost_cor !== NULL ? round($p->quantity * $p->price_cost_cor, 2) : NULL; ?>
			<td><?php echo fmt_cor_pair($subtotal_cor); ?></td>
			<?php $total_cor = ($subtotal_cor !== NULL && $total_cor !== NULL) ? $total_cor + $subtotal_cor : NULL; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
	<tfoot>
			<tr><td colspan="6" class="text-right">
			<strong>Total: <?php echo fmt_cor_pair($total_cor); ?></strong>
		</td></tr>
		</tfoot>
</table>