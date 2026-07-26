<h3 class="section-title">Historial de uso interno</h3>

<div class="clearfix">
	<a href="<?php echo base_url('products/internalUse'); ?>" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> Registrar uso interno</a>
</div>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('products/internal_use_list'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
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
				<a href="<?php echo base_url('products/internal_use_list') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($internal_uses) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron registros de uso interno con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay usos internos registrados</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th colspan="5">
				Total de registros: <?php echo $no_limit_count; ?> |
				Total costo: $<?php echo number_format($totals->total_cost,2); ?>
			</th>
		</tr>
		<tr>
			<th>Fecha</th>
			<th>Empleado</th>
			<th>Motivo</th>
			<th>Costo total</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $internal_uses as $iu ) : ?>
		<tr>
			<td><?php echo date('d/m/Y H:i', strtotime($iu->date_time)); ?></td>
			<td><?php echo $iu->admin_name; ?></td>
			<td><?php echo $iu->reason; ?></td>
			<td>$ <?php echo number_format($iu->total_cost,2); ?></td>
			<td>
			<a href="<?php echo base_url("products/internal_use_details/{$iu->id_internal_use}"); ?>"><i class="fa fa-table"></i> Detalles</a>
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
