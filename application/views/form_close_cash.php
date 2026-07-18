
<div class="section-title clearfix">
	<h3 class="pull-left" style="margin-bottom: 0;">Cierre de caja</h3>
	<h4 class="pull-right" style="margin-bottom: 0;"><?php echo formatDateIntl('now'); ?></h4>
</div>

<form action="<?php echo base_url('cash/close') ?>" method="post" class="form-horizontal" autocomplete="off" spellcheck="false" id="form">
	<input type="hidden" name="tk" value="<?php echo $tk; ?>">
	<h4>Ingrese las denominaciones del efectivo en caja</h4>
	<table class="table table-bordered currencies currencies-cordobas">
		<thead>
			<tr>
				<th>
					<img src="<?php echo base_url('assets/img/billete_10.jpg'); ?>" alt="" >
					<span>C$10</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_20.jpg'); ?>" alt="" >
					<span>C$20</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_50.jpg'); ?>" alt="" >
					<span>C$50</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_100.jpg'); ?>" alt="" >
					<span>C$100</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_200.jpg'); ?>" alt="" >
					<span>C$200</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_500.jpg'); ?>" alt="" >
					<span>C$500</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_1000.jpg'); ?>" alt="" >
					<span>C$1000</span>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><input type="text" class="form-control" data-mult="10" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="20" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="50" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="100" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="200" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="500" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="1000" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
			</tr>
		</tbody>
	</table>
	<table class="table table-bordered currencies currencies-cordobas">
		<thead class="monedas">
			<tr>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_5.jpg'); ?>" alt="" >
					<span>C$5cent.</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_25.jpg'); ?>" alt="" >
					<span>C$25cent.</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_10c.jpg'); ?>" alt="" >
					<span>C$10cent.</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_50.jpg'); ?>" alt="" >
					<span>C$50cent.</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_1.jpg'); ?>" alt="" >
					<span>C$1</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_5cor.jpg'); ?>" alt="" >
					<span>C$5</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/moneda_10.jpg'); ?>" alt="" >
					<span>C$10</span>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><input type="text" class="form-control" data-mult="0.05" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="0.25" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="0.10" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="0.50" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="1" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="5" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="10" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
			</tr>
		</tbody>
	</table>
	<table class="table table-bordered currencies currencies-dolares">
		<thead>
			<tr>
				<th>
					<img src="<?php echo base_url('assets/img/billete_1d.jpg'); ?>" alt="" >
					<span>$1</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_2d.jpg'); ?>" alt="" >
					<span>$2</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_5d.jpg'); ?>" alt="" >
					<span>$5</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_10d.jpg'); ?>" alt="" >
					<span>$10</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_20d.jpg'); ?>" alt="" >
					<span>$20</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_50d.jpg'); ?>" alt="" >
					<span>$50</span>
				</th>
				<th>
					<img src="<?php echo base_url('assets/img/billete_100d.jpg'); ?>" alt="" >
					<span>$100</span>
				</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><input type="text" class="form-control" data-mult="1" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="2" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="5" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="10" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="20" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="50" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
				<td><input type="text" class="form-control" data-mult="100" data-parsley-type="integer" data-parsley-trigger="keyup" data-parsley-validation-threshold="0"></td>
			</tr>
		</tbody>
	</table>
	<table class="table table-bordered results">
		<thead>
			<tr>
				<th>Montos ingresados</th>
				<th>Montos en caja</th>
				<th>Diferencias</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>C$<input type="text" id="monto-ingresado-cordobas" name="user_cordobas" class="no-input" readonly="true" value="0.00"></td>
				<td>C$<span id="monto-caja-cordobas"><?php echo number_format($cash['sales_total_cordobas'] + $cash['current']->open_quantity-$cash['outcomes_total'],2); ?></span></td>
				<td>C$<span id="diferencia-cordobas">0.00</span></td>
			</tr>
			<tr>
				<td>$<input type="text" id="monto-ingresado-dolares" name="user_dolares" class="no-input" readonly="true" value="0.00"></td>
				<td>$<span id="monto-caja-dolares"><?php echo number_format($cash['sales_total_dollar'] + $cash['current']->open_quantity_dollar,2); ?></span></td>
				<td>$<span id="diferencia-dolares">0.00</span></td>
			</tr>
		</tbody>
	</table>
	<button class="btn btn-primary">Cerrar caja</button>
</form>

<style>

	.no-input{
	    border: none;
	    background: none;
	}
	#form table.currencies tbody{

		background: #ececec;
	}
	#form table.currencies thead img{
		
		display: inline-block;
		width: 97px;
		height: 50px;
	}
	#form table.currencies thead th{

		text-align: center;
		background: #fff;
	}
	#form table.currencies thead th span{

		display: block;
	}
	#form table.currencies thead.monedas img{
		
		display: inline-block;
		width: 50px;
		height: auto;
	}
	#form table.currencies td input{

		text-align: center;
	}
	#form table.results td{

		font-weight: 600;
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

<script>

	$('#form').parsley();

	var cash = {

		ingresado_cordobas : $('#monto-ingresado-cordobas'),
		ingresado_dolares : $('#monto-ingresado-dolares'),
		caja_cordobas : $('#monto-caja-cordobas'),
		caja_dolares : $('#monto-caja-dolares'),
		diferencia_cordobas : $('#diferencia-cordobas'),
		diferencia_dolares : $('#diferencia-dolares')
	};
	var ok_submit = false;

	$('table.currencies-cordobas input').on('keyup',function(){

		var cor = 0;

		$('table.currencies-cordobas input').each(function(index,element){

			cor += $(element).attr('data-mult') * $(element).val();
		});

		cash.ingresado_cordobas.val( numeral(cor).format('0,0.00') );

		var dif = numeral(cor).value() - numeral( cash.caja_cordobas.html() ).value();

		cash.diferencia_cordobas.html( numeral(dif).format('0,0.00') );

		cash.diferencia_cordobas.parents('td').removeClass();

		if( dif == 0 )
			cash.diferencia_cordobas.parents('td').addClass('equal');
		else if( dif > 0 )
			cash.diferencia_cordobas.parents('td').addClass('greater');
		else
			cash.diferencia_cordobas.parents('td').addClass('less');

	});

	$('table.currencies-dolares input').on('keyup',function(){

		var dol = 0;

		$('table.currencies-dolares input').each(function(index,element){

			dol += $(element).attr('data-mult') * $(element).val();
		});

		cash.ingresado_dolares.val( numeral(dol).format('0,0.00') );

		var dif = numeral(dol).value() - numeral( cash.caja_dolares.html() ).value();

		cash.diferencia_dolares.html( numeral(dif).format('0,0.00') );

		cash.diferencia_dolares.parents('td').removeClass();

		if( dif == 0 )
			cash.diferencia_dolares.parents('td').addClass('equal');
		else if( dif > 0 )
			cash.diferencia_dolares.parents('td').addClass('greater');
		else
			cash.diferencia_dolares.parents('td').addClass('less');

	});

	$('#form').on('submit',function(e){

		if( !$('#form').parsley().validate() )
			return false;

		if( !ok_submit )
			e.preventDefault();
		else
			return true;

		(new PNotify({
		    title: 'Confirmación',
		    text: 'Confirma el cierre de caja?',
		    icon: 'glyphicon glyphicon-question-sign',
		    hide: false,
		    confirm: {
		        confirm: true,
		        buttons : [
		        {text: "OK", addClass : 'btn-default'},
		        {text: 'Cancelar', addClass: 'btn-default'}
		        ]
		    },
		    buttons: {
		        closer: true,
		        sticker: false,
		        closer_hover : false,
		    },
		    history: {
		        history: false
		    },
		    animate: {
		        animate: true,
		        in_class: 'bounceInDown',
		        out_class: 'bounceOutUp'
		    },
		    addclass: 'stack-modal',
		    stack: {
		        'dir1': 'down',
		        'dir2': 'right',
		        'modal': true
		    }
		})).get().on('pnotify.confirm', function() {
		    ok_submit = true;
		    $('#form').submit();
		});
	});
</script>