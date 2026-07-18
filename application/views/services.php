<h3 class="section-title">Listado de servicios</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('services'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-2">
				<div class="form-group">
					<label for="barcode">Código de barra:</label>
					<input id="barcode" name="barcode" type="text" class="form-control" value="<?php echo Easysearch::getValue('barcode'); ?>">
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					<label for="name">Nombre del servicio:</label>
					<input id="name" name="name" type="text" class="form-control" value="<?php echo Easysearch::getValue('name'); ?>">
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					<label for="provider">Proveedor:</label>
					<select name="provider" id="provider" class="form-control">
						<option value="">---</option>
						<?php foreach( $providers as $p ) : ?>
						<option value="<?php echo $p->id_provider; ?>" <?php if($p->id_provider==Easysearch::getValue('provider')) echo 'selected'; ?>><?php echo $p->provider; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					<label for="id_service_category">Categoría:</label>
					<select name="id_service_category" id="id_service_category" class="form-control">
						<option value="">---</option>
						<?php foreach( $categories as $c ) : ?>
						<option value="<?php echo $c->id_service_category; ?>" <?php if($c->id_service_category==Easysearch::getValue('id_service_category')) echo 'selected'; ?>><?php echo $c->name_category; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('services') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($services) ) : ?>
	<?php if( isset($get) ) : ?>
	<p class="alert alert-info">No se encontraron servicios con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay servicios registrados</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th colspan="6">
				Servicios: <?php echo $no_limit_count; ?>
			</th>
		</tr>
		<tr>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_barcode'); ?>
				<a href="<?php echo $order['link']; ?>">
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Código de barra
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_name'); ?>
				<a href="<?php echo $order['link']; ?>">
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Nombre
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_provider'); ?>
				<a href="<?php echo $order['link']; ?>">
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Proveedor
			</th>
			<th>Categoría</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_price_sell'); ?>
				<a href="<?php echo $order['link']; ?>">
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>P. venta
			</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $services as $s ) : ?>
		<tr>
			<td><?php echo $s->barcode; ?></td>
			<td><?php echo $s->name; ?></td>
			<td><?php echo $s->provider; ?></td>
			<td><?php echo $s->name_category; ?></td>
			<td class="text-center"><?php echo number_format($s->price_sell,2); ?></td>
			<td class="text-center">
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a href="<?php echo base_url("products/edit/{$s->id_product}"); ?>"><i class="fa fa-pencil"></i> Editar</a>
						</li>
						<li class="divider"></li>
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar el servicio?" href="<?php echo base_url("products/delete/{$s->id_product}"); ?>"><i class="fa fa-times"></i> Eliminar</a>
						</li>
					</ul>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<div class="panel-footer">
	<?php echo $pagination; ?>
</div>
