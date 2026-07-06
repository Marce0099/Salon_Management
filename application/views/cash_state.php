<h3 class="section-title">Estado de caja</h3>

<table class="table table-bordered" style="border-collapse: separate;">
	<tr>
		<td colspan="3" class="bg-blue2 bold">Apertura</td>
		<td class="bg-blue2">C$<?php echo number_format($current->open_quantity,2); ?> | $<?php echo number_format($current->open_quantity_dollar ?? 0,2); ?></td>
	</tr>
	<tr>
		<td colspan="4" class="bg-green2">Ventas</td>
	</tr>
	</tr>
		<?php $sales_global_total = 0; if( !empty($sales) ) : ?>
		<tr>
		<td colspan="4" style="padding: 0;border:0;">
			<table class="table table-bordered" style="margin-bottom: 0;background-color: initial;">
				<thead>
					<tr>
						<th>No. Factura</th>
						<th>Total</th>
						<th>Efectivo</th>
						<th>Cambio</th>
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
						<td><?php echo $s->id_sale; ?></td>
						<td>C$<?php echo number_format($_total_sale,2); ?></td>
						<td>
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
							<!--<?php // if( $s->pos_paid ) : ?>
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
						<td>
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
							<!--<?php //if( !$s->pos_paid ) : ?>
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
							<?php //$sales_global_total += $s->sale_total; ?>-->
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</td>
		</tr>
		<?php endif; ?>
		<tr>
		<td>Ventas realizadas: <?php echo $sales_num_total; ?> ( $<?php echo number_format($sales_global_total,2); ?>)</td>
		<td>POS(<?php echo $sales_pos_num; ?>): C$<?php echo number_format($sales_total_pos,2); ?></td>
		<td>Transferencia(<?php echo $sales_transfer_num; ?>): C$<?php echo number_format($sales_total_transfer,2); ?></td>
		<td colspan="2">
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
			<table class="table table-bordered" style="background-color: inherit;margin-bottom: 0;">
				<thead>
					<tr>
						<th>Monto</th>
						<th>Descripción</th>
						<th>Realizado por</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach( $outcomes as $o ) : ?>
				<tr>
					<td>C$<?php echo number_format($o->quantity,2); ?></td>
					<td><?php echo $o->description; ?></td>
					<td><?php echo $o->name; ?></td>
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

<style>
	.bg-green2 {
	    background: #e2ece6;
	    font-weight: 600;
	    border: 1px solid #abe2c1 !important;
	}
	.bg-red2 {
	    background: #f3e3e0;
	    font-weight: 600;
	    border: 1px solid #e0b7b0 !important;
	}
	.bg-blue2 {
	    background: #e3edf7;
	    border: 1px solid #cbdcec !important;
	}
</style>