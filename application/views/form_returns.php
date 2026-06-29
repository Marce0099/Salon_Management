<h4 class="section-title">Formulario de devoluciones</h4>

<form action="<?php echo base_url('returns/register'); ?>" method="post" class="form-horizontal" autocomplete="off" id="form">
	<div class="row">
		<div class="col-md-4">
			<div class="form-group">
				<label for="id_provider">Proveedor
			    </label>
				<select name="id_provider" id="id_provider" class="form-control">
					<option value="">---</option>
					<?php foreach( $providers as $p ) : ?>
					<option value="<?php echo $p->id_provider; ?>"><?php echo $p->provider; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<div class="col-md-3">
			<div class="form-group">
				<label for="">Fecha</label>
				<input type="text" name="date_time" id="date_time" class="dtpicker form-control" required="true">
			</div>
		</div>
	</div>
	<table class="table table-bordered" id="invoice-table" style="table-layout: fixed;">
		<thead>
			<tr>
				<th width="4%"></th>
				<th width="18%">Código de barra</th>
				<th width="48%">Descripción</th>
				<th width="15%">Cantidad en stock</th>
				<th width="15%">Cantidad a devolver</th>
			</tr>
		</thead>
		<tbody id="products-list">
		</tbody>
	</table>
	<div class="form-group">
		<a href="javascript:;" class="btn btn-primary" id="add-entry"><i class="fa fa-plus"></i> Agregar producto</a>
		<button class="btn btn-success" id="btn-submit" type="submit">Registrar devolución</button>
	</div>
</form>

<table class="hidden">
	<tr id="row-mdl" class="row-product">
		<td class="text-center">
			<span class="delete-item text-danger text-center">
				<i class="fa fa-times"></i>
			</span>
		</td>
		<td>
			<input type="text" class="p-barcode" required="true">
			<input type="hidden" class="p-id-products" name="id_products[]" required="true">
		</td>
		<td>
			<span class="p-name"></span>
		</td>
		<td class="text-center">
			<span class="p-stock"></span>
		</td>
		<td>
			<input type="text" name="quantities_returns[]" class="p-q-return text-center" required="true" data-parsley-type="integer" data-parsley-min="1" data-parsley-max="">
		</td>
	</tr>
</table>

<script>

	var ri = 0;

	$('#id_provider').on('change',function(){

		if( $(this).find('option:selected').val() ){

			$(this).attr('disabled',true);
		}
	});

	$('#add-entry').on('click',function(){

		var row = $('#row-mdl').clone().removeAttr('id');
		$('#products-list').append(row);
		row.find('.p-barcode').attr( 'id', 'index'+ri ).focus();
		ri++;

		window.scrollTo(0,document.body.scrollHeight);
	});

	$(document).on( 'keydown', '.p-barcode',function(e){

		var me = $(this);
		var barcode = $(this).val();
		var id_provider = $('#id_provider option:selected').val();

		if( e.which == 13 && barcode.length > 0 ){

			e.preventDefault();

			/* if( !id_provider ){

				new PNotify({
		            title: 'Notificación',
		            text: 'Debe seleccionar un proveedor',
		            type : 'warning',
		            styling : 'bootstrap3',
		            delay : 3500
		        });

		        return false;
			} */

			if( alreadyIncluded( me ) ){

				new PNotify({
		            title: 'Notificación',
		            text: 'El producto ya esta incluido en el listado',
		            type : 'warning',
		            styling : 'bootstrap3',
		            delay : 3500
		        });

		        return false;
			}

		  	var post = { 'barcode' : barcode, 'id_provider' : id_provider };
			
			$.ajax({

				url : '<?php echo base_url('products/getProductForReturn'); ?>',
				type : 'post',
				dataType : 'json',
				data : post,
				success : function(data){

					if( data.product ){

						me.parents('tr').find('.p-name').html(data.product.name);
						me.parents('tr').find('.p-stock').html(data.product.stock);
						me.parents('tr').find('.p-q-return').attr('data-parsley-max',data.product.stock).focus();
						me.parents('tr').find('.p-id-products').val( data.product.id_product );
					}
					else{

						me.focus();

						new PNotify({
				            title: 'Notificación',
				            text: 'No se ha encontrado el producto',
				            type : 'warning',
				            styling : 'bootstrap3',
				            delay : 2000
				        });
					}

				},
				error : function(){

					new PNotify({
			            title: 'Notificación',
			            text: 'Ha ocurrido un error obteniendo los datos',
			            type : 'error',
			            styling : 'bootstrap3',
			            delay : 3500
			        });
				}

			});
		}
	});

	$(document).on( 'click', '.delete-item',function(e){

		$(this).parents('tr').remove();
	});

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
	});

	var submit = false;

	$(function () {
	  $('#form').parsley()
	  .on('form:submit', function() {

	  		if( submit )
	  			return true;
	  		
	  		if( $('#form .row-product').length == 0 ){

				new PNotify({
		            title: 'Notificación',
		            text: 'No ha ingresado ningun producto de devolución',
		            type : 'error',
		            styling : 'bootstrap3',
		            delay : 2000
		        });

		        return false;
			}

			var ok = true;

			$('#form .p-id-products').each(function(index,element){

				if( $(element).val() == '' ){

					new PNotify({
			            title: 'Notificación',
			            text: 'Uno o varios productos posee un codigo de barra invalido',
			            type : 'error',
			            styling : 'bootstrap3',
			            delay : 2700
			        });

			        ok = false;

			        return false;
				}
			});

			if( !ok )
				return false;

			(new PNotify({
			    title: 'Confirmación',
			    text: 'Confirma realizar la devolución?',
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
			    	
			    	submit = true;
			    	$('#id_provider').removeAttr('disabled');
			    	$('#form').submit();
			});

			return false;
	  });
	});

	function alreadyIncluded( input ){

		var included = false;

		$('#invoice-table .p-barcode').each( function( index, element ){

			if( $(element).attr('id') != input.attr('id') && $(element).val() == input.val() ){

				included = true;
				return false;
			}
		});

		return included;
	}

</script>