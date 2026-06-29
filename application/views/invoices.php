<h3 class="section-title">Listado de facturas</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('invoices'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-2">
				<div class="form-group">
					<label for="nombre">No. factura</label>
					<input id="invoice_no" name="invoice_no" type="text" class="form-control" value="<?php echo Easysearch::getValue('invoice_no'); ?>">
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					<label for="id_provider">Proveedor</label>
					<select name="id_provider" id="id_provider" class="form-control">
						<option value="">---</option>
						<?php foreach( $providers as $p ) : ?>
						<option value="<?php echo $p->id_provider; ?>" <?php if($p->id_provider==Easysearch::getValue('id_provider')) echo 'selected'; ?>><?php echo $p->provider; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-4">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Fecha desde</label>
							<input type="text" name="date_start" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_start'); ?>">
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Fecha hasta</label>
							<input type="text" name="date_end" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_end'); ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('invoices') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($invoices) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron entradas con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay facturas registradas</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_invoice_no'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>No. Factura
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_date_time'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Fecha
			</th>
			<th>Proveedor</th>
			<th>Productos</th>
			<th>Total</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $invoices as $i ) : ?>
		<tr>
			<td><?php echo $i->invoice_no; ?></td>
			<td><?php echo date('d/m/Y', strtotime($i->date_time)); ?></td>
			<td><?php echo $i->provider; ?></td>
			<td><?php echo $i->num_products; ?></td>
			<td>C$ <?php echo number_format($i->total,2); ?></td>
			<td>
			<a href="<?php echo base_url("invoices/details/{$i->id_invoice}"); ?>"><i class="fa fa-table"></i> Detalles</a>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if( !empty($pagination) ) : ?>
<div class="panel-footer">
	<?php echo $pagination; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<script>
	$(document).on('ready',function(){

		$('.dtpicker').daterangepicker({
			locale : {
				 'format': 'DD/MM/YYYY',
				 "daysOfWeek": [
		            "Do",
		            "Lu",
		            "Ma",
		            "Mi",
		            "Ju",
		            "Vi",
		            "Sa"
		        ],
		        "monthNames": [
		            "Enero",
		            "Febrero",
		            "Marzo",
		            "Abril",
		            "Mayo",
		            "Junio",
		            "Julio",
		            "Agosto",
		            "Septiembre",
		            "Octubre",
		            "Noviembre",
		            "Diciembre"
		        ]
			},
			maxDate : '<?php echo date('d/m/Y'); ?>',
	        singleDatePicker: true,
	        showDropdowns: true
	    });

	    <?php if( !Easysearch::getValue('date_start') && !Easysearch::getValue('date_end') ) : ?>
	    	$('.dtpicker').val('');
		<?php endif; ?>
	});
</script>