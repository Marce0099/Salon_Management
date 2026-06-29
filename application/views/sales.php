<h3 class="section-title">Historial de ventas</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('sales/historial'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-1">
				<div class="form-group">
					<label for="nombre"># Venta</label>
					<input id="invoice_no" name="invoice_no" type="text" class="form-control" value="<?php echo Easysearch::getValue('invoice_no'); ?>">
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
			<div class="col-md-2">
				<div class="form-group">
					<label for="nombre">Vendedor</label>
					<select name="id_employee" id="" class="form-control">
						<option value=""></option>
						<?php foreach($employees as $em) : ?>
						<option value="<?php echo $em->id_employee; ?>" <?php if(Easysearch::getValue('id_employee')==$em->id_employee) echo 'selected'; ?>>
							<?php echo $em->name; ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="col-md-2">
				<div class="form-group">
					<label for="nombre">Cliente</label>
					<input id="client" name="client" type="text" class="form-control" value="<?php echo Easysearch::getValue('client'); ?>">
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('sales/historial') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($sales) ) : ?>
<p class="alert alert-info">No hay ventas registradas</p>
<?php else : ?>
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_id_sale'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Número de venta
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_date_time'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Fecha
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_client'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Cliente
			</th>
			<th>
				<?php $order = Easysearch::getFieldOrder('order_employee'); ?>
				<a href="<?php echo $order['link']; ?>">									
					<span class="white-color fa fa-sort-<?php echo $order['mode']; ?>"></span>
				</a>Vendedor
			</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $sales as $s ) : ?>
		<tr>
			<td><?php echo $s->id_sale; ?></td>
			<td><?php echo date('d/m/Y', strtotime($s->date_time)); ?></td>
			<td><?php echo $s->client; ?></td>
			<td><?php echo $s->employee; ?></td>
			<td>
				<a href="<?php echo base_url("sales/details/{$s->id_sale}"); ?>"><i class="fa fa-table"></i> Ver detalles</a>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
	
<?php echo $pagination; ?>

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