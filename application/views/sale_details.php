<h3 class="section-title">Detalles de venta</h3>

<?php $total_dollar = 0; $total_cordobas = 0; ?>

<table class="table">
	<thead>
		<tr>
			<th>Fecha y hora:</th>
			<th>Tipo de pago</th>
			<th>No. factura:</th>
			<th>Cliente:</th>
			<th>Vendedor:</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($payments as $pay): ?>
			<?php if($pay->payment_method === 'cash') ?>
			<tr>
				<td><?php echo date('d-m-Y H:i:s',strtotime($sale->date_time)); ?></td>
				<td>
					<?php echo $payment_methods[$pay->payment_method] ?>
				</td>
				<td><?php echo $sale->id_sale; ?></td>
				<td><?php echo $sale->client; ?></td>
				<td><?php echo $sale->name; ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<table class="table table-hover jambo_table table-bordered" id="products-sale">
	<thead>
		<tr>
			<th width="30%">Producto</th>
			<th>Precio</th>
			<th>Cantidad</th>
			<th>Descuento</th>
			<th>Valor C$</th>
			<th>Valor $</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $details as $d ) : ?>
		<tr>
			<td><?php echo $d->barcode .' : '. $d->name; ?></td>
			<td>$<?php echo number_format($d->price_sell,2); ?></td>
			<td><?php echo $d->quantity; ?></td>
			<td><?php echo $d->discount; ?></td>
			<?php
				$subtotal = $d->quantity * $d->price;

				if( $d->discount )
					$subtotal -= $subtotal * $d->discount / 100;

				$total_dollar += $subtotal;
				$subtotal_cordobas = $subtotal*$sale->dollar_ex_sell;
				$total_cordobas += $subtotal_cordobas;
				$_total_cordobas = $total_cordobas;
				//$total += $subtotal;

			?>
			<td><?php echo number_format($subtotal_cordobas,2); ?></td>
			<td><?php echo number_format($subtotal,2); ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<table class="table">
	<thead>
		<tr>
			<th>Total en Córdobas</th>
			<th>Total en Dólares</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>C$<?php echo number_format($total_cordobas,2); ?></td>
			<td>$<?php echo number_format($total_dollar,2); ?></td>
		</tr>
	</tbody>
</table>


<table class="table">
	<thead>
		<tr>
			<th>Monto Pagado (C$)</th>
			<th>Metodo Pago</th>
			<th>Cordoba</th>
			<th>Dolar</th>
			<th>Cambio</th>

		</tr>
	</thead>
	<tbody>
		<?php
			/* a sale should only ever have one 'cash' row going forward, but this view
			   also renders sales created before that rule existed, so the cash amounts
			   are combined here to keep the Cambio column correct for those legacy sales */
			$cash_total_cordobas = 0;
			foreach($payments as $pay){
				if($pay->payment_method === 'cash'){
					$cash_total_cordobas += floatval($pay->cordoba) + floatval($pay->dolar) * floatval($sale->dollar_ex_sell);
				}
			}
			$cash_rows_seen = 0;
		?>
		<?php foreach($payments as $pay): ?>
			<?php
				$cor = floatval($pay->cordoba);
				$dol = floatval($pay->dolar);
				$dol_to_cor = $dol * floatval($sale->dollar_ex_sell);
				$total_cor = $cor + $dol_to_cor;

				if( $pay->payment_method === 'cash' )
					$cash_rows_seen++;
			?>
			<tr>
				<td>C$<?php
					if($pay->payment_method === 'cash'){
						echo $cash_rows_seen === 1 ? $_total_cordobas : "0.00";
					}else{
						echo $total_cor;
						$_total_cordobas -= $total_cor;
					}
					?>
				</td>
				<td>
					<?php echo $payment_methods[$pay->payment_method] ?>
				</td>
				<td>C$<?php echo $pay->cordoba; ?></td>
				<td>$<?php echo $pay->dolar; ?></td>
				<td>C$<?php
						if($pay->payment_method === 'cash' && $cash_rows_seen === 1){
							echo number_format($cash_total_cordobas - $_total_cordobas,2);
						}else{
							echo "0.00";
						}
					?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
