<h4 class="section-title">Cálculo de comisiones</h4>

<form action="<?php echo base_url('employees/commissions'); ?>" method="post" data-parsley-validate autocomplete="off" id="form">
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="">Empleado:</label>
				<select name="id_employee" class="form-control" required="true">
				<option value="">---</option>
				<?php foreach( $employees as $e ) : ?>
				<option value="<?php echo $e->id_employee; ?>"><?php echo $e->name; ?></option>
				<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="">Fecha desde</label>
				<input type="text" name="date_start" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_start'); ?>" required="true">
			</div>
		</div>
		<div class="col-md-4">
			<div class="form-group">
				<label for="">Fecha hasta</label>
				<input type="text" name="date_end" class="dtpicker form-control" value="<?php echo Easysearch::getValue('date_end'); ?>" required="true">
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12">
			<button class="btn btn-success">Generar reporte</button>
		</div>
	</div>
</form>

<script>
	$(document).on('ready',function(){

		$('.dtpicker').daterangepicker({
			locale : {
				 'format': 'DD-MM-YYYY',
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
			maxDate : '<?php echo date('d-m-Y'); ?>',
	        singleDatePicker: true,
	        showDropdowns: true
	    });
	});
</script>