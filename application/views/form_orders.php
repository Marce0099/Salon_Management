<h3 class="section-title">Formulario de orden de pedidos</h3>

<form action="<?php echo base_url('orders/register'); ?>" spellcheck="false" autocomplete="off" id="form" method="post">
	<div class="form-group">
		<label for="">Seleccione el proveedor</label>
		<select name="id_provider" id="id_provider" class="form-control" required="true">
			<option value="">---</option>
			<?php foreach( $providers as $p ) : ?>
			<option value="<?php echo $p->id_provider; ?>"><?php echo $p->provider; ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<table class="table table-bordered" id="tbl">
		<thead>
			<tr>
				<th width="5%"></th>
				<th width="20%">Código de barra</th>
				<th width="65%">Nombre del producto</th>
				<th width="10%">Cantidad</th>
			</tr>
		</thead>
		<tbody id="products-lst">
		</tbody>
	</table>
	<a href="javascript:void(0);" class="btn btn-primary" id="add-entry"><i class="fa fa-plus"></i> Agregar producto</a>
	<button class="btn btn-success" id="btn-submit"><i class="fa fa-check"></i> Generar orden</button>
</form>


<table class="hidden table table-bordered">
	<tr id="row-mdl">
		<td class="text-center">
			<span class="delete-item text-danger text-center">
				<i class="fa fa-times"></i>
			</span>
		</td>
		<td>
			<input type="text" class="p-barcode form-control" required="true">
			<input type="hidden" class="p-id-products" name="id_products[]">
		</td>
		<td class="p-name"></td>
		<td>
			<input type="text" name="quantities[]" class="p-quantity text-center form-control" required="true">
		</td>
	</tr>
</table>

<script>

    var submit = false;

	$('#add-entry').on('click',function(){

		var row = $('#row-mdl').clone().removeAttr('id');
		$('#products-lst').append(row);
		row.find('.p-barcode').focus();
		window.scrollTo(0,document.body.scrollHeight);
	});

	$(document).on( 'keydown', '.p-barcode',function(e){

		var me = $(this);
		var barcode = $(this).val();

		if( e.which == 13 && barcode.length > 0 ){

			e.preventDefault();

		  	var post = { 'barcode' : barcode };
			
			$.ajax({

				url : '<?php echo base_url('products/getByBarCode'); ?>',
				type : 'post',
				dataType : 'json',
				data : post,
				success : function(data){

					if( data.product ){

						me.parents('tr').find('.p-name').html(data.product.name);
						me.parents('tr').find('.p-id-products').val(data.product.id_product);
						me.parents('tr').find('.p-quantity').focus();
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

	$('#form').parsley();

	$('#form').on('submit',function(e) {

		if( !$('#form').parsley().validate() )
			return false;

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

		return ok;
	});
</script>

<style>
	#tbl{

		table-layout: fixed;
	}
	
</style>