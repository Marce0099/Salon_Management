<h3 class="section-title">Inventario de productos</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('products'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-2">
				<div class="form-group">
					<label for="nombre">Código de barra:</label>
					<input id="barcode" name="barcode" type="text" class="form-control" value="<?php echo Easysearch::getValue('barcode'); ?>">
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					<label for="name">Nombre del producto:</label>
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
			<div class="col-md-3">
				<div class="form-group">
					<label for="stock">Cantidad:</label>
					<div class="row">
						<div class="col-md-6">
							<select name="stock_cmp" id="stock_cmp" class="form-control">
								<option value=""></option>
								<option value="gt" <?php if( Easysearch::getValue('stock_cmp') == 'gt' ){ echo 'selected'; } ?>>mayor a</option>
								<option value="lt" <?php if( Easysearch::getValue('stock_cmp') == 'lt' ){ echo 'selected'; } ?>>menor a</option>
								<option value="eq" <?php if( Easysearch::getValue('stock_cmp') == 'eq' ){ echo 'selected'; } ?>>igual a</option>
							</select>
						</div>
						<div class="col-md-6">
							<input id="stock" name="stock" type="text" class="form-control" value="<?php echo Easysearch::getValue('stock'); ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('products') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($products) ) : ?>
	<?php if( isset($get) ) : ?>
	<p class="alert alert-info">No se encontraron productos con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay productos registrados</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th colspan="7">
				Productos: <?php echo $no_limit_count; ?> | 
				Total costo: $<?php echo number_format($total_inventory->total_cost,2); ?> |
				Total venta: $<?php echo number_format($total_inventory->total_sell,2); ?> 
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
			<th>
				<?php $order = Easysearch::getFieldOrder('order_stock'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Stock
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_price_cost'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>P. costo
			</th>
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
		<?php foreach( $products as $p ) : ?>
		<tr>
			<td><?php echo $p->barcode; ?><span class="hide p-id_product"><?php echo $p->id_product; ?></span></td>
			<td><?php echo $p->name; ?></td>
			<td>
			<?php echo $p->provider; ?>
			</td>
			<td class="text-center">
			<?php echo $p->stock; ?>
			</td>
			<td class="text-center"><?php echo number_format($p->price_cost,2); ?></td>
			<td class="text-center"><?php echo number_format($p->price_sell,2); ?></td>
			<td class="text-center">
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a href="<?php echo base_url("products/edit/{$p->id_product}"); ?>"><i class="fa fa-pencil"></i> Editar</a>
						</li>
						<li class="divider"></li>
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar el producto del inventario?" href="<?php echo base_url("products/delete/{$p->id_product}"); ?>"><i class="fa fa-times"></i> Eliminar</a>
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
