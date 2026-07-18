<h3 class="section-title">Categorías de servicios</h3>

<form action="<?php echo base_url('service_categories'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false" style="margin-bottom:15px;">
	<div class="row">
		<div class="col-md-3">
			<input type="text" name="name_category" class="form-control" placeholder="Categoría" value="<?php echo Easysearch::getValue('name_category'); ?>">
		</div>
		<div class="col-md-3">
			<button type="submit" class="btn btn-default">Buscar</button>
			<a href="<?php echo base_url('service_categories'); ?>" class="btn btn-default">Reiniciar</a>
		</div>
		<?php if( $this->controller->is_admin() ) : ?>
		<div class="col-md-3 col-md-offset-3 text-right">
			<a href="<?php echo base_url('service_categories/register'); ?>" class="btn btn-success">Crear Categoria</a>
		</div>
		<?php endif; ?>
	</div>
</form>

<?php if( empty($categorias) ) : ?>
<p class="alert alert-info">No hay categorías registradas</p>
<?php else: ?>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>Categoría</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $categorias as $c ) : ?>
		<tr>
			<td><?php echo $c->name_category; ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
