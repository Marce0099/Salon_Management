<h3 class="section-title">Historial de caja</h3>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('cash/historial'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-6">
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
				<a href="<?php echo base_url('cash/historial') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($historial) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron entradas con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay registros de caja registrados</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th>Fecha apertura</th>
			<th>Monto de apertura</th>
			<th>Apertura por</th>
			<th>Fecha cierre</th>
			<th>Monto de cierre</th>
			<th>Cierre por</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $historial as $h ) : ?>
		<tr>
			<td><?php echo date('d/m/Y h:i:s', strtotime($h->open_date_time)); ?></td>
			<td>C$ <?php echo number_format($h->open_quantity,2); ?></td>
			<td><?php echo $h->open_admin; ?></td>
			<td>
				<?php if( $h->close_date_time ) : ?>
					<?php echo date('d/m/Y h:i:s', strtotime($h->close_date_time)); ?>
				<?php endif; ?>
			</td>
			<td>
				<?php if( $h->close_quantity_cordobas ) : ?>
					C$ <?php echo number_format($h->close_quantity_cordobas,2); ?>
				<?php endif; ?>
				<?php if( $h->close_quantity_dollar && $h->close_quantity_dollar ){ echo '|'; } ?>
				<?php if( $h->close_quantity_dollar ) : ?>
					$ <?php echo number_format($h->close_quantity_dollar,2); ?>
				<?php endif; ?>
			</td>
			<td><?php echo $h->close_admin; ?></td>
			<td>
			<a href="<?php echo base_url("cash/details/{$h->id_cash}"); ?>"><i class="fa fa-table"></i> Detalles</a>
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