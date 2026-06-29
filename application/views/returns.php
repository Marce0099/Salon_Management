<h3 class="section-title">Listado de devoluciones</h3>

<div class="clearfix">
	<a href="<?php echo base_url('returns/register'); ?>" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> Registrar devolución</a>
</div>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('returns'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
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
				<a href="<?php echo base_url('returns') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($returns) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron devoluciones con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay devoluciones registradas</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th colspan="4">
				Total de devoluciones: <?php echo $no_limit_count; ?> | 
				Total costo: $<?php echo number_format($totals->total_cost,2); ?>
			</th>
		</tr>
		<tr>
			<th>
				Fecha
			</th>
			<th>Proveedor</th>
			<th>Total devolución en costo</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $returns as $r ) : ?>
		<tr>
			<td><?php echo date('d/m/Y', strtotime($r->date_time)); ?></td>
			<td><?php echo $r->provider; ?></td>
			<td>$ <?php echo number_format($r->total_cost,2); ?></td>
			<td>
			<a href="<?php echo base_url("returns/details/{$r->id_return}"); ?>"><i class="fa fa-table"></i> Detalles</a>
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