<?php $n = function($x){ return rtrim(rtrim(number_format((float) $x, 3, '.', ''), '0'), '.'); }; ?>
<h3 class="section-title">Receta: <?php echo htmlspecialchars($product->name); ?></h3>
<p><a href="<?php echo base_url('services'); ?>">Volver a platillos</a></p>

<?php if( empty($recipe) ) : ?>
<p class="alert alert-info">Este platillo aún no tiene receta.</p>
<?php else : ?>
<p class="text-muted">Cantidad de cada ingrediente que consume <strong>una porción</strong>.</p>
<div class="table-responsive">
<table class="table table-striped" style="max-width:560px;">
	<thead><tr><th>Ingrediente</th><th>Cantidad por porción</th><th>Unid.</th></tr></thead>
	<tbody>
		<?php foreach( $recipe as $r ) : ?>
		<tr><td><?php echo htmlspecialchars($r->name); ?></td><td><?php echo $n($r->quantity); ?></td><td><?php echo htmlspecialchars($r->unit); ?></td></tr>
		<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<?php if( !empty($mods) ) : ?>
<h4>Modificadores</h4>
<ul>
	<?php foreach( $mods as $m ) : ?>
	<li><?php echo htmlspecialchars($m->name); ?> <small class="text-muted">(<?php echo htmlspecialchars($m->kitchen_text); ?>)</small></li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>
