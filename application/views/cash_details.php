<h3 class="section-title">Estado de caja</h3>

<?php $show = isset($implements)?'display: none;':'' ?>
<div class="text-right" style="<?php echo $show; ?>">
	<button class="btn btn-success" onclick="window.print();"><i class="fa fa-print"></i> Imprimir</button>
</div>

<p class="bold text-center">Fecha: <?php echo formatDateIntl($cash->open_date_time); ?></p>

<?php $bor = isset($implements)?"border: 1px solid black;padding: 6px;":""; ?>
<?php $coll = isset($implements)?"border-collapse: collapse;":""; ?>

<table class="table table-bordered" style="border-collapse: separate;<?php echo $coll; ?>">
	<tr>
		<td colspan="3" class="bg-blue2 bold" style="<?php echo $bor; ?>">Apertura</td>
		<td class="bg-blue2" style="<?php echo $bor; ?>">C$<?php echo number_format($current->open_quantity,2); ?> | $<?php echo number_format($current->open_quantity_dollar ?? 0,2); ?></td>
	</tr>
	<tr>
		<td colspan="4" class="bg-green2">Ventas</td>
	</tr>
	</tr>
		<?php $sales_global_total = 0; if( !empty($sales) ) : ?>
		<tr>
		<td colspan="4" style="padding: 0;border:0;">
			<table class="table table-bordered" style="margin-bottom: 0;background-color: initial;<?php echo $coll; ?>">
				<thead>
					<tr>
						<th style="<?php echo $bor; ?>">No. Factura</th>
						<th style="<?php echo $bor; ?>">Total</th>
						<th style="<?php echo $bor; ?>">Efectivo</th>
						<th style="<?php echo $bor; ?>">Cambio</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach($sales as $s) : ?>
						<?php 
							$_total_sale = $s->sale_total * $s->dollar_ex_sell;
							$total_cordobas = $_total_sale;

							$query = "SELECT 
							sp.payment_method,
							ba.name_account AS account,
							sp.cordoba,
							sp.dolar
							FROM `sale_payments` sp
							LEFT JOIN bank_account ba ON ba.id_account = sp.account
							WHERE sp.id_sale = '" . $s->id_sale . "';";
							$payments = $this->db->query($query)->result();
							usort($payments, function($a, $b) {
								$priority = [
									'transfer' => 1,
									'pos' => 1,
									'cash' => 2
								];
								return ($priority[$a->payment_method] ?? 99) <=> ($priority[$b->payment_method] ?? 99);
							});
							$paymentCount = 0;
						?>
					<tr>
						<td style="<?php echo $bor; ?>"><?php echo $s->id_sale; ?></td>
						<td style="<?php echo $bor; ?>">C$<?php echo number_format($_total_sale,2); ?></td>
						<td style="<?php echo $bor; ?>">
							<?php foreach($payments as $pay): ?>
								<?php if($paymentCount >0): ?>
									<br>
								<?php endif; ?>

								<?php 
									$_account =  is_null($pay->account) ? "" : " " . $pay->account;
									$printEf = $payment_methods[$pay->payment_method] . $_account .":";
									$printEf .= "(\${$pay->dolar} / C\${$pay->cordoba})";

									echo $printEf;
								?>

							<?php $paymentCount++; endforeach;?>
							
							<!--
							<?php // if( $s->pos_paid ) : ?>
								POS
							<?php // else : ?>
								<?php // if( $s->cash_dollar ) :  ?>
									<?php // echo '$'.$s->cash_dollar; ?>
								<?php // endif; ?>
								<?php // if( $s->cash_dollar && $s->cash_cordobas ){ echo '|'; } ?>
								<?php // if( $s->cash_cordobas ) :  ?>
									C$<?php // echo number_format(+$s->cash_cordobas,2); ?>
								<?php // endif; ?>
							<?php // endif; ?>-->
						</td>
						<td style="<?php echo $bor; ?>">
							<?php $paymentCount = 0; foreach($payments as $pay): ?>
								<?php if($paymentCount >0): ?>
									<br>
								<?php endif; ?>

								<?php 
									$cor = floatval($pay->cordoba); 
									$dol = floatval($pay->dolar); 
									$dol_to_cor = $dol * floatval($s->dollar_ex_sell);
									$total_cor = $cor + $dol_to_cor;

									if($pay->payment_method !== 'cash'){
										$total_cordobas -= $total_cor;
									}else{
										echo "C\$".number_format($total_cor - $total_cordobas,2);
									}
								?>

							<?php $paymentCount++; endforeach;?>
							<!--
							<?php //if( !$s->pos_paid ) : ?>
								<?php
/*
									if( $s->cash_cordobas && !$s->cash_dollar ){

										$st_cor = round( $s->sale_total * $s->dollar_ex_buy );
										$change = $s->cash_cordobas - $st_cor;
									}
									else if( $s->cash_dollar && !$s->cash_cordobas ){

										$change = round( $s->cash_dollar * $s->dollar_ex_buy ) - round( $s->sale_total * $s->dollar_ex_buy );
									}
									else{

										$cor = round( $s->cash_dollar * $s->dollar_ex_buy );
										$cor += $s->cash_cordobas;
										$change = $cor - round( $s->sale_total * $s->dollar_ex_buy );
									}
*/
								?>
								C$<?php //echo number_format( $change,2); ?>
							<?php //endif; ?>
							<?php //$sales_global_total += $s->sale_total; ?>
							-->
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</td>
		</tr>
		<?php endif; ?>
		<tr>
		<td style="<?php echo $bor; ?>">Ventas realizadas: <?php echo $sales_num_total; ?> ( $<?php echo number_format($sales_global_total,2); ?>)</td>
		<td style="<?php echo $bor; ?>">POS(<?php echo $sales_pos_num; ?>): C$<?php echo number_format($sales_total_pos,2); ?></td>
		<td style="<?php echo $bor; ?>">Transferencia(<?php echo $sales_transfer_num; ?>): C$<?php echo number_format($sales_total_transfer,2); ?></td>
		<td style="<?php echo $bor; ?>" colspan="2">
		Efectivo(<?php echo $sales_cash_num; ?>): 
		$<?php echo number_format($sales_total_dollar); ?> |
		C$<?php echo number_format($sales_total_cordobas,2); ?>
		</td>
		</tr>
	</tr>
	<?php $total_outcomes = 0; if( !empty($outcomes) ) : ?>
	<tr>
		<td colspan="4" class="bg-red2">Egresos</td>
	</tr>
	<tr>
		<td colspan="4" style="padding: 0; border:0;">
			<table class="table table-bordered" style="background-color: inherit;margin-bottom: 0;<?php echo $coll; ?>">
				<thead>
					<tr>
						<th style="<?php echo $bor; ?>">Monto</th>
						<th style="<?php echo $bor; ?>">Descripción</th>
						<th style="<?php echo $bor; ?>">Realizado por</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach( $outcomes as $o ) : ?>
				<tr>
					<td style="<?php echo $bor; ?>">C$<?php echo number_format($o->quantity,2); ?></td>
					<td style="<?php echo $bor; ?>"><?php echo $o->description; ?></td>
					<td style="<?php echo $bor; ?>"><?php echo $o->name; ?></td>
					<?php $total_outcomes += $o->quantity; ?>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="4" class="text-right">Total egresos: C$<?php echo number_format($total_outcomes,2); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<td colspan="4" class="bold">Total en caja: C$<?php echo number_format( $current->open_quantity + $sales_total_cordobas - $total_outcomes, 2 ); ?> | $<?php echo number_format($current->open_quantity_dollar + $sales_total_dollar,2); ?>
		</td>
	</tr>
</table>
<?php if( $cash->close_date_time ) : ?>

<table class="table table-bordered results" style="<?php echo $show; ?>">
	<thead>
		<tr>
			<th>Montos ingresados</th>
			<th>Montos en caja</th>
			<th>Diferencias</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>C$<?php echo number_format($cash->close_user_quantity_cordobas,2); ?></td>
			<td>C$<?php echo number_format($cash->close_quantity_cordobas,2); ?></span></td>
			<?php
				$dif = $cash->close_user_quantity_cordobas-$cash->close_quantity_cordobas;
				$class = $dif > 0 ? 'warning' : 'danger';
				if($dif==0)$class = 'success';
			?>
			<td class="text-<?php echo $class; ?>">C$<?php echo number_format($dif,2); ?></td>
		</tr>
		<tr>
			<td>$<?php echo number_format($cash->close_user_quantity_dollar,2); ?></td>
			<td>$<?php echo number_format($cash->close_quantity_dollar,2); ?></td>
			<?php
				$dif = $cash->close_user_quantity_dollar-$cash->close_quantity_dollar;
				$class = $dif > 0 ? 'warning' : 'danger';
				if($dif==0)$class = 'success';
			?>
			<td class="text-<?php echo $class; ?>">$<?php echo number_format($dif,2); ?></td>
		</tr>
	</tbody>
</table>

<?php endif; ?>

<style>
	.bg-green2 {
	    background: #e2ece6 !important;
	    font-weight: 600;
	    border: 1px solid #abe2c1 !important;
	}
	.bg-red2 {
	    background: #f3e3e0 !important;
	    font-weight: 600;
	    border: 1px solid #e0b7b0 !important;
	}
	.bg-blue2 {
	    background: #e3edf7 !important;
	    border: 1px solid #cbdcec !important;
	}
	.equal{
		background: #e0f3d4;
	}
	.greater{
		background: #f7ecde;
	}
	.less{
		background: #f7dede;
	}
</style>