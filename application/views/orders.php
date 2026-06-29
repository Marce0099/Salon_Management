<h3 class="section-title">Listado de ordenes de pedidos</h3>

<div class="clearfix text-right">
	<a href="<?php echo base_url('orders/register'); ?>" class="btn btn-primary"><i class="fa fa-plus"></i> Nueva orden</a>
</div>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('orders'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-2">
				<div class="form-group">
					<label for="nombre">No. orden</label>
					<input id="order_no" name="order_no" type="text" class="form-control" value="<?php echo Easysearch::getValue('order_no'); ?>">
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					<label for="provider">Proveedor</label>
					<select name="provider" id="provider" class="form-control">
						<option value="">---</option>
						<?php foreach( $providers as $p ) : ?>
						<option value="<?php echo $p->id_provider; ?>" <?php if($p->id_provider==Easysearch::getValue('provider')) echo 'selected'; ?>><?php echo $p->provider; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('orders') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>


<?php if( empty($orders) ) : ?>
	<?php if( isset($get) ) : ?>
	<p class="alert alert-info">No se encontraron ordenes con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay ordenes registradas</p>
	<?php endif; ?>
<?php else : ?>
<table class="table table-bordered jambo_table">
	<thead>
		<tr>
			<th>No. orden</th>
			<th>Proveedor</th>
			<th>PDF</th>
			<th>Fecha</th>
			<th>Acciones</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $orders as $o ) : ?>
		<tr>
			<td><?php echo $o->id_order; ?></td>
			<td><?php echo $o->provider; ?></td>
			<td><a href="<?php echo base_url("orders_pdf/{$o->file}"); ?>" target="_blank">Descargar</a></td>
			<td><?php echo date('d/m/Y',strtotime($o->date_time)); ?></td>
			<td>
				<div class="btn-group">
					<a href="#" data-toggle="dropdown" class="dropdown-toggle"><i class="fa fa-cog"></i></a>
					<ul class="dropdown-menu pull-right text-left">
						<li>
							<a class="confirm-action" data-msg="Confirma eliminar la orden de pedido?" href="<?php echo base_url("orders/delete/{$o->id_order}"); ?>"><i class="fa fa-times"></i> Eliminar</a>
						</li>
					</ul>
				</div>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
