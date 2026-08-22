<h4 class="section-title">Cálculo de comisiones</h4>

<table class="table table-bordered">
	<thead>
		<tr>
			<th>Empleado</th>
			<th>Fecha inicial</th>
			<th>Fecha final</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td><?php echo $employee->name; ?></td>
			<td><?php echo $date_start; ?></td>
			<td><?php echo $date_end; ?></td>
		</tr>
	</tbody>
</table>

<?php if( empty($sales) ) : ?>
	<p class="alert alert-info">No se encontraron registros de ventas</p>
<?php else : ?>
	<table class="table table-bordered jambo_table">
		<thead>
			<tr>
				<th>No. factura</th>
				<th>Fecha</th>
				<th>Venta en servicios</th>
				<th>Ganancia real</th>
				<th>Comisión</th>
			</tr>
		</thead>
		<tbody>
			<?php $com_total = 0; $com_total_usd = 0; foreach( $sales as $s ) : ?>

			<?php $details = $interface->getSaleDetail($s->id_sale, $id_employee); ?>
			<tr>
				<td><?php echo $s->id_sale; ?></td>
				<td><?php echo date("d-m-Y",strtotime($s->date_time)); ?></td>
				<td>C$<?php echo number_format($details['totalSale'],2); ?></td>
				<td>C$<?php echo number_format($details['realProfit'],2); ?></td>
				<?php
					$com_total += $details['commission'];
					$com_total_usd += $details['commissionUsd'];
					/*if(!$s->pos_paid)
						$com_sale = $s->sale_total * 2.5 / 100;
					else
						$com_sale = ($s->sale_total - $s->sale_total * 6 / 100 ) * 2.5 / 100;
					//$com_services = $s->service_total;
					$com_total += $com_sale;*/
				?>
				<td>C$<?php echo number_format($details['commission'],2); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="6" class="text-right bg-success">
				Comisión total: $<?php echo number_format($com_total_usd,2) . ' | C$' . number_format($com_total,2); ?>
				</td>
			</tr>
		</tfoot>
	</table>
<?php endif; ?>

<div class="text-right">
	<button class="btn btn-success" onclick="window.print();"><i class="fa fa-print"></i> Imprimir</button>
</div>