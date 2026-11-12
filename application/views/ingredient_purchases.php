<h3 class="section-title">Compras de ingredientes <a href="<?php echo base_url('ingredients/purchase'); ?>" class="btn btn-success btn-sm pull-right"><i class="fa fa-plus"></i> Nueva compra</a></h3>

<?php if( empty($purchases) ) : ?>
<p class="alert alert-info">Todavía no hay compras registradas.</p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-hover table-striped jambo_table">
	<thead><tr><th>#</th><th>Fecha</th><th>Comercio</th><th class="text-right">Ingredientes</th><th class="text-right">Total</th><th>Registró</th><th></th></tr></thead>
	<tbody>
	<?php foreach( $purchases as $p ) : ?>
		<tr>
			<td><?php echo (int) $p->id_purchase; ?></td>
			<td><?php echo date('d/m/Y', strtotime($p->purchased_at)); ?></td>
			<td><?php echo htmlspecialchars((string) $p->store); ?></td>
			<td class="text-right"><?php echo (int) $p->n_lines; ?></td>
			<td class="text-right">$ <?php echo number_format((float) $p->total_cost, 2); ?></td>
			<td><?php echo htmlspecialchars((string) $p->username); ?></td>
			<td><a href="<?php echo base_url('ingredients/purchase_detail/'.$p->id_purchase); ?>" class="btn btn-default btn-xs">Ver</a></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
