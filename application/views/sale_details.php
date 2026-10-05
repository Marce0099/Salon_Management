<h3 class="section-title">Detalles de venta</h3>

<p>
	<?php if( !empty($sale->file) ): ?>
	<a href="<?php echo base_url('uploads/receipts/'.$sale->file); ?>" target="_blank" class="btn btn-primary">
		<i class="fa fa-print"></i> Reimprimir factura
	</a>
	<?php endif; ?>
	<button type="button" class="btn btn-default" id="btn-sale-comments">
		<i class="fa fa-comments"></i> Comentarios
	</button>
</p>

<style>
	#sale-comments-body{height:380px;overflow-y:auto;background:#ece5dd;padding:12px;}
	.sc-row{display:flex;margin-bottom:8px;}
	.sc-row.mine{justify-content:flex-end;}
	.sc-bubble{max-width:80%;padding:6px 10px;border-radius:8px;background:#fff;box-shadow:0 1px 1px rgba(0,0,0,.15);}
	.sc-row.mine .sc-bubble{background:#dcf8c6;}
	.sc-author{font-size:12px;font-weight:bold;color:#128c7e;}
	.sc-text{white-space:pre-wrap;word-wrap:break-word;overflow-wrap:anywhere;}
	.sc-time{font-size:11px;color:#888;text-align:right;}
	.sc-empty{text-align:center;color:#666;margin-top:150px;}
	#sc-counter{font-size:11px;color:#888;}
</style>

<div class="modal fade" id="sale-comments-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Comentarios de la factura #<?php echo (int) $sale->id_sale; ?></h4>
			</div>
			<div class="modal-body" style="padding:0;">
				<div id="sale-comments-body"></div>
			</div>
			<div class="modal-footer" style="text-align:left;">
				<textarea id="sc-input" class="form-control" rows="3" maxlength="1000" placeholder="Escriba un comentario..."></textarea>
				<div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center;">
					<span id="sc-counter">0 / 1000</span>
					<button type="button" class="btn btn-success" id="sc-send"><i class="fa fa-paper-plane"></i> Enviar</button>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
$(function(){
	var ID_SALE = <?php echo (int) $sale->id_sale; ?>;
	var ME = <?php echo (int) $current_admin_id; ?>;
	var base = '<?php echo site_url('sale_comments'); ?>';
	var $body = $('#sale-comments-body'), $input = $('#sc-input'), $send = $('#sc-send');
	var sending = false, loadSeq = 0;

	function notify(type, text){
		new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 3500 });
	}

	// Server answers JSON; anything else (login redirect, PHP error page) is a failure.
	function errMsg(xhr, fallback){
		var j = xhr && xhr.responseJSON;
		if( j && j.message ){ return j.message; }
		if( xhr && xhr.status === 0 ){ return 'Sin conexión con el servidor'; }
		return fallback;
	}

	function bubble(c){
		var $row = $('<div class="sc-row"></div>').toggleClass('mine', c.id_admin === ME);
		var $b = $('<div class="sc-bubble"></div>');
		$b.append($('<div class="sc-author"></div>').text(c.author));
		$b.append($('<div class="sc-text"></div>').text(c.comment));
		$b.append($('<div class="sc-time"></div>').text(c.created_at));
		return $row.append($b);
	}

	function scrollDown(){ $body.scrollTop($body[0].scrollHeight); }

	function showEmptyIfNeeded(){
		if( !$body.children('.sc-row').length && !$body.children('.sc-empty').length ){
			$body.html('<div class="sc-empty">Aún no hay comentarios para esta factura.</div>');
		}
	}

	function load(){
		var seq = ++loadSeq;
		$body.html('<div class="sc-empty">Cargando...</div>');
		$.ajax({ url: base + '/list_by_sale/' + ID_SALE, type: 'GET', dataType: 'json', cache: false })
		.done(function(r){
			if( seq !== loadSeq ){ return; }
			if( !r || r.type !== 'success' ){ return fail(); }
			$body.empty();
			$.each(r.comments, function(i, c){ $body.append(bubble(c)); });
			showEmptyIfNeeded();
			scrollDown();
		})
		.fail(function(xhr){
			if( seq !== loadSeq ){ return; }
			fail(errMsg(xhr, 'No se pudieron cargar los comentarios (¿sesión expirada?)'));
		});
		function fail(msg){
			$body.html('<div class="sc-empty"></div>');
			$body.children().text(msg || 'No se pudieron cargar los comentarios')
				.append('<br><button type="button" class="btn btn-xs btn-default" id="sc-retry" style="margin-top:8px;">Reintentar</button>');
		}
	}

	$body.on('click', '#sc-retry', load);

	$('#btn-sale-comments').on('click', function(){ $('#sale-comments-modal').modal('show'); });
	$('#sale-comments-modal').on('shown.bs.modal', function(){ load(); $input.focus(); });

	$input.on('input', function(){ $('#sc-counter').text($input.val().length + ' / 1000'); });

	$send.on('click', function(){
		if( sending ){ return; }
		var text = $.trim($input.val());
		if( !text ){ notify('warning', 'Escriba un comentario antes de enviar'); return; }

		sending = true;
		$send.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Enviando');

		$.ajax({ url: base + '/create/' + ID_SALE, type: 'POST', dataType: 'json', data: { comment: text } })
		.done(function(r){
			if( !r || r.type !== 'success' || !r.comment ){
				notify('error', (r && r.message) || 'No se pudo guardar el comentario');
				return;
			}
			$body.children('.sc-empty').remove();
			$body.append(bubble(r.comment));
			$input.val('').trigger('input');
			scrollDown();
		})
		.fail(function(xhr){
			// typed text is intentionally kept so the user can retry
			notify('error', errMsg(xhr, 'No se pudo guardar el comentario (¿sesión expirada?)'));
		})
		.always(function(){
			sending = false;
			$send.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Enviar');
		});
	});
});
</script>

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
			<th>Ingreso real (C$)</th>
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
						if( $pay->payment_method === 'cupon' ){
							$discount = floatval($pay->cupon_discount_rate ?? 0);
							echo number_format( floatval($pay->cordoba) * (1 - $discount/100), 2 );
						}else if( $pay->payment_method === 'pos' ){
							$tax_rate = floatval($pay->tax_rate ?? 0);
							echo number_format( $total_cor * (1 - $tax_rate/100), 2 );
						}else{
							echo number_format( $total_cor, 2 );
						}
					?>
				</td>
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
