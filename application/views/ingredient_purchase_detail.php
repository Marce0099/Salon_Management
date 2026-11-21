<?php $n = function($v){ return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); }; ?>
<h3 class="section-title">Compra #<?php echo (int) $purchase->id_purchase; ?> <a href="<?php echo base_url('ingredients/purchases'); ?>" class="btn btn-default btn-sm pull-right">Volver</a></h3>
<p>
	<strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($purchase->purchased_at)); ?>
	<?php if( $purchase->store ) : ?> · <strong>Comercio:</strong> <?php echo htmlspecialchars($purchase->store); ?><?php endif; ?>
	· <strong>Registró:</strong> <?php echo htmlspecialchars((string) $purchase->username); ?>
	<?php if( $purchase->note ) : ?><br><strong>Nota:</strong> <?php echo htmlspecialchars($purchase->note); ?><?php endif; ?>
</p>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Ingrediente</th><th class="text-right">Cantidad</th><th class="text-right">Entra a existencia</th><th class="text-right">Pagado</th></tr></thead>
	<tbody>
	<?php foreach( $lines as $l ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($l->name); ?></td>
			<td class="text-right"><?php echo $n($l->pack_qty).' '.htmlspecialchars($l->pack_name !== NULL ? $l->pack_name : $l->unit); ?></td>
			<td class="text-right"><?php echo $n($l->base_qty).' '.$l->unit; ?></td>
			<td class="text-right"><?php echo $l->line_cost_cor !== NULL ? 'C$ '.number_format((float) $l->line_cost_cor, 2) : '—'; ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
	<tfoot><tr><th colspan="3" class="text-right">Total</th><th class="text-right">C$ <?php echo number_format((float) $purchase->total_cost_cor, 2); ?></th></tr></tfoot>
</table>
</div>
