<?php
$labels = array('receive' => 'Entrada', 'order' => 'Pedido', 'void' => 'Anulación', 'waste' => 'Merma', 'adjustment' => 'Ajuste / conteo', 'prepare_in' => 'Lote preparado', 'prepare_out' => 'Usado en lote');
$n = function($x){ return rtrim(rtrim(number_format((float) $x, 3, '.', ','), '0'), '.'); };
?>
<h3 class="section-title">Historial: <?php echo htmlspecialchars($ingredient->name); ?></h3>
<p>Existencia actual: <strong><?php echo $n($ingredient->stock).' '.$ingredient->unit; ?></strong> · <a href="<?php echo base_url('ingredients'); ?>">Volver a ingredientes</a></p>

<?php if( empty($movements) ) : ?>
<p class="alert alert-info">Sin movimientos todavía</p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Fecha</th><th>Tipo</th><th class="text-right">Cantidad</th><th>Motivo</th><th>Usuario</th></tr></thead>
	<tbody>
	<?php foreach( $movements as $m ) : ?>
		<tr>
			<td><?php echo $m->created_at; ?></td>
			<td><?php echo $labels[$m->type]; ?></td>
			<td class="text-right <?php echo $m->quantity < 0 ? 'text-danger' : 'text-success'; ?>"><?php echo ($m->quantity > 0 ? '+' : '').$n($m->quantity).' '.$ingredient->unit; ?></td>
			<td><?php echo htmlspecialchars((string) $m->reason); ?></td>
			<td><?php echo htmlspecialchars((string) $m->username); ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>
