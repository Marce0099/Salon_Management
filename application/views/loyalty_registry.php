<h3 class="section-title">Registro de puntos — <?php echo $client->name_client . ' ' . $client->surname_client; ?></h3>

<p class="alert alert-info">Saldo actual: <strong><?php echo number_format($balance,2); ?> pts</strong></p>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url("loyalty_points/registry/{$client->id_client}"); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">
			<div class="col-md-8">
				<div class="row">
					<div class="col-md-4">
						<div class="form-group">
							<label for="">Tipo</label>
							<select name="movement_type" class="form-control">
								<option value="">Todos</option>
								<option value="accrual" <?php echo Easysearch::getValue('movement_type') == 'accrual' ? 'selected' : ''; ?>>Acumulación</option>
								<option value="redemption" <?php echo Easysearch::getValue('movement_type') == 'redemption' ? 'selected' : ''; ?>>Canje</option>
							</select>
						</div>
					</div>
					<div class="col-md-4">
						<div class="form-group">
							<label for="">Fecha desde</label>
							<input type="text" name="date_start" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_start'); ?>">
						</div>
					</div>
					<div class="col-md-4">
						<div class="form-group">
							<label for="">Fecha hasta</label>
							<input type="text" name="date_end" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_end'); ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url("loyalty_points/registry/{$client->id_client}"); ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($movements) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron movimientos con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">Este cliente aún no tiene movimientos de puntos</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		<tr>
			<th>Fecha</th>
			<th>Tipo</th>
			<th>Puntos</th>
			<th>Etapa / Descuento</th>
			<th>Saldo resultante</th>
			<th>Venta</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $movements as $m ) : ?>
		<tr>
			<td><?php echo date('d/m/Y H:i:s', strtotime($m->date_time)); ?></td>
			<td>
				<?php if( $m->movement_type == 'accrual' ) : ?>
					<span class="label label-success">Acumulación</span>
				<?php else : ?>
					<span class="label label-warning">Canje</span>
				<?php endif; ?>
			</td>
			<td><?php echo ($m->points > 0 ? '+' : '') . number_format($m->points,2); ?></td>
			<td>
				<?php if( $m->movement_type == 'redemption' ) : ?>
					Etapa <?php echo $m->tier_applied; ?> (<?php echo number_format($m->discount_rate_applied,2); ?>%)
				<?php endif; ?>
			</td>
			<td><?php echo number_format($m->balance_after,2); ?></td>
			<td>
				<?php if( $m->id_sale ) : ?>
				<a href="<?php echo base_url("sales/details/{$m->id_sale}"); ?>">#<?php echo $m->id_sale; ?></a>
				<?php endif; ?>
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
