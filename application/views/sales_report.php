<h3 class="section-title">Reporte detallado de ventas</h3>

<style>
	#sales-report-dashboard .kpi-tile .kpi-value{
		font-size: 22px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
</style>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-md-3">
				<div class="form-group">
					<label for="">Fecha desde</label>
					<input type="text" id="date_start" class="dtpicker form-control" autocomplete="off">
				</div>
			</div>
			<div class="col-md-3">
				<div class="form-group">
					<label for="">Fecha hasta</label>
					<input type="text" id="date_end" class="dtpicker form-control" autocomplete="off">
				</div>
			</div>
			<div class="col-md-6" style="margin-top:24px;">
				<button type="button" id="btnGenerarReporte" class="btn btn-success"><i class="fa fa-bar-chart"></i> Generar reporte</button>
				<button type="button" id="btnDescargarExcel" class="btn btn-default" disabled><i class="fa fa-file-excel-o"></i> Descargar Excel</button>
			</div>
		</div>
	</div>
</div>

<p id="sales-report-empty" class="alert alert-info" style="display:none;">No se encontraron ventas en el rango de fechas seleccionado.</p>

<div id="sales-report-dashboard" style="display:none;">

	<div class="row">
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-dollar"></i> Total ventas</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-total-venta">C$0.00</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-line-chart"></i> Utilidad bruta</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-total-utilidad">C$0.00</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-percent"></i> Comisiones</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-total-comision">C$0.00</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-money"></i> Ganancia real</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-total-ganancia">C$0.00</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-list-ol"></i> No. de ventas</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-num-ventas">0</div>
				</div>
			</div>
		</div>
		<div class="col-md-2">
			<div class="panel panel-default kpi-tile">
				<div class="panel-heading"><i class="fa fa-ticket"></i> Ticket promedio</div>
				<div class="panel-body">
					<div class="kpi-value" id="kpi-ticket-promedio">C$0.00</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-md-6">
			<div class="graph" id="chartTrend">
				<h3 class="graph_tittle">Venta vs. ganancia real por día</h3>
				<canvas></canvas>
				<div class="graph_legend"></div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="graph" id="chartByEmployee">
				<h3 class="graph_tittle">Ganancia real por empleado</h3>
				<canvas></canvas>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<div class="graph" id="chartTopProducts">
				<h3 class="graph_tittle">Top 10 productos/servicios por ganancia real</h3>
				<canvas></canvas>
			</div>
		</div>
	</div>

	<div class="panel panel-default">
		<div class="panel-heading">Detalle de ventas</div>
		<div class="panel-body" style="overflow-x:auto;">
			<table class="table table-bordered table-striped jambo_table">
				<thead>
					<tr>
						<th>No. Factura</th>
						<th>Fecha</th>
						<th>Cliente</th>
						<th>Producto/Servicio</th>
						<th>Tipo</th>
						<th>Cant.</th>
						<th>Precio unit.</th>
						<th>Desc. %</th>
						<th>Atendido por</th>
						<th>Venta</th>
						<th>Utilidad bruta</th>
						<th>Comisión</th>
						<th>Ganancia real</th>
					</tr>
				</thead>
				<tbody id="sales-report-table-body"></tbody>
			</table>
		</div>
	</div>

</div>

<!-- Loading modal -->
<div class="modal fade" id="reportLoadingModal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-body text-center">
				<i class="fa fa-spinner fa-spin fa-2x"></i>
				<p style="margin-top:15px;">Un momento, estamos generando el reporte...</p>
			</div>
		</div>
	</div>
</div>

<script>
	var SALES_REPORT_BASE_URL = '<?php echo base_url(); ?>';
</script>
