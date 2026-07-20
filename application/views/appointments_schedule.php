<h3 class="section-title">Agenda &mdash; <?php echo date('d/m/Y', strtotime($date)); ?></h3>

<style>
	#schedule-nav{ margin-bottom: 15px; }

	.schedule-grid{ font-size: 12px; table-layout: fixed; width: 100%; }
	.schedule-grid th, .schedule-grid td{ vertical-align: middle; text-align: center; padding: 4px; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
	.schedule-grid th{ white-space: nowrap; }
	.schedule-grid th:not(.hour-col), .schedule-grid td:not(.hour-col){ width: 160px; }
	/* Bootstrap's .table-responsive forces white-space:nowrap on all th/td below 768px
	   (assets/gentelella/vendors/bootstrap/less/tables.less:186-196) with higher specificity
	   than the rule above, undoing our wrapping and letting content overflow the column. */
	.table-responsive .schedule-grid tbody tr td{ white-space: normal !important; }
	.schedule-grid td.cell-empty{ min-width: 70px; cursor: pointer; }
	.schedule-grid td.cell-empty:hover{ background: #f5f5f5; }
	.schedule-grid td.cell-occupied{ background: #eaf3ff; text-align: left; cursor: pointer; line-height: 1.2; }
	.schedule-grid td.cell-occupied .time{ font-weight: bold; display: block; }
	.schedule-grid td.cell-occupied .services{ color: #666; list-style: disc; margin: 0; padding-left: 16px; }
	.schedule-grid td.cell-occupied .services li{ line-height: 1.2; }
	.schedule-grid td.cell-occupied .btn-delete-appt{ margin-top: 4px; }
	.schedule-grid .hour-col{
		position: sticky;
		left: 0;
		width: 80px;
		background: #fff;
		font-weight: bold;
		text-align: left;
		white-space: nowrap;
		z-index: 1;
	}
	.schedule-grid thead th{
		position: sticky;
		top: 0;
		background: #fff;
		z-index: 2;
	}
	.schedule-grid thead .hour-col{ z-index: 3; }
	/* .table-responsive's overflow-x:auto implicitly promotes overflow-y to auto too
	   (CSS Overflow spec), but with no height limit the div never actually scrolls —
	   it just grows to fit all rows, so it becomes a scroll container that's always
	   at scrollTop 0 and position:sticky never sees a scroll offset to react to.
	   Bounding the height here turns it into a real scrollport for both axes. */
	.schedule-scroll{
		max-height: 70vh;
		overflow-y: auto;
	}

	#day-panel{
		position: fixed;
		top: 0;
		right: -360px;
		width: 360px;
		height: 100%;
		background: #fff;
		box-shadow: -2px 0 8px rgba(0,0,0,0.2);
		z-index: 2000;
		overflow-y: auto;
		padding: 20px;
		transition: right 0.25s ease;
	}
	#day-panel.open{ right: 0; }
	#day-panel-overlay{
		display: none;
		position: fixed;
		top: 0; left: 0; right: 0; bottom: 0;
		background: rgba(0,0,0,0.3);
		z-index: 1999;
	}
	#day-panel-overlay.open{ display: block; }
	body.panel-open{ overflow: hidden; }
	.service-list{ max-height: 220px; overflow-y: auto; border: 1px solid #e6e9ed; border-radius: 4px; padding: 8px; }
	.category-box{ border: 1px solid #e6e9ed; border-radius: 4px; margin-bottom: 6px; overflow: hidden; }
	.category-box:last-child{ margin-bottom: 0; }
	.category-header{ padding: 6px 10px; cursor: pointer; background: #f9f9f9; display: flex; justify-content: space-between; align-items: center; }
	.category-header:hover{ background: #f1f1f1; }
	.category-header .fa-chevron-down{ transition: transform 0.25s ease; font-size: 11px; }
	.category-header.open .fa-chevron-down{ transform: rotate(180deg); }
	.category-body{ display: none; padding: 6px 10px; border-top: 1px solid #e6e9ed; }
	#client-results{ position: relative; }
	#client-results-list{
		position: absolute;
		z-index: 10;
		background: #fff;
		border: 1px solid #e6e9ed;
		width: 100%;
		max-height: 160px;
		overflow-y: auto;
		display: none;
	}
	#client-results-list a{ display: block; padding: 5px 10px; color: #333; }
	#client-results-list a:hover{ background: #f5f5f5; text-decoration: none; }
</style>

<div id="schedule-nav">
	<a class="btn btn-default btn-sm" href="<?php echo base_url('appointments/schedule/'.$prev_date); ?>"><i class="fa fa-chevron-left"></i> Día anterior</a>
	<a class="btn btn-default btn-sm" href="<?php echo base_url('appointments/schedule/'.$next_date); ?>">Día siguiente <i class="fa fa-chevron-right"></i></a>
	<a class="btn btn-default btn-sm" href="<?php echo base_url('appointments'); ?>"><i class="fa fa-calendar"></i> Volver al calendario</a>
</div>

<div class="table-responsive schedule-scroll">
<table class="table table-bordered schedule-grid">
	<thead>
		<tr>
			<th class="hour-col">Hora</th>
			<?php foreach( $columns as $col ) : ?>
			<th><?php echo $col['label']; ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php foreach( $hours as $hour_index => $hour ) : ?>
		<tr>
			<td class="hour-col"><?php echo $hour['label']; ?></td>
			<?php foreach( $columns as $col ) : ?>
				<?php if( $col['skip'][$hour_index] ) : ?>
					<?php // covered by an earlier row's rowspan — no <td> here ?>
				<?php elseif( isset($col['hour_map'][$hour_index]) ) : $a = $col['hour_map'][$hour_index]['appt']; ?>
				<td rowspan="<?php echo $col['hour_map'][$hour_index]['rowspan']; ?>" class="cell-occupied"
					data-id="<?php echo (int) $a->id_appointment; ?>"
					data-employee="<?php echo $col['id_employee'] ? (int) $col['id_employee'] : ''; ?>"
					data-employee-label="<?php echo htmlspecialchars($col['label']); ?>"
					data-client-id="<?php echo (int) $a->id_client; ?>"
					data-client-name="<?php echo htmlspecialchars($a->client_name); ?>"
					data-date-start="<?php echo substr($a->start_datetime, 0, 10); ?>"
					data-time-start="<?php echo substr($a->start_datetime, 11, 5); ?>"
					data-date-end="<?php echo substr($a->end_datetime, 0, 10); ?>"
					data-time-end="<?php echo substr($a->end_datetime, 11, 5); ?>"
					data-notes="<?php echo htmlspecialchars((string) $a->notes); ?>"
					data-service-ids="<?php echo htmlspecialchars((string) $a->service_ids); ?>">
					<span class="time"><?php echo date('h:i a', strtotime($a->start_datetime)); ?> - <?php echo date('h:i a', strtotime($a->end_datetime)); ?></span>
					<span class="client"><?php echo $a->client_name; ?></span>
					<?php if( $a->services ) : ?>
					<ul class="services">
						<?php foreach( explode(', ', $a->services) as $service_name ) : ?>
						<li><?php echo $service_name; ?></li>
						<?php endforeach; ?>
					</ul>
					<?php else : ?>
					<span class="services">Sin servicios</span>
					<?php endif; ?>
					<?php if( $is_admin ) : ?>
					<button type="button" class="btn btn-danger btn-xs btn-delete-appt" data-id="<?php echo $a->id_appointment; ?>">Eliminar</button>
					<?php endif; ?>
				</td>
				<?php else :
					$date_start = substr( $hour['start'], 0, 10 );
					$time_start = substr( $hour['start'], 11, 5 );
					$date_end = substr( $hour['end'], 0, 10 );
					$time_end = substr( $hour['end'], 11, 5 );
				?>
				<td class="cell-empty"
					data-employee="<?php echo $col['id_employee'] ? (int) $col['id_employee'] : ''; ?>"
					data-employee-label="<?php echo htmlspecialchars($col['label']); ?>"
					data-date-start="<?php echo $date_start; ?>"
					data-time-start="<?php echo $time_start; ?>"
					data-date-end="<?php echo $date_end; ?>"
					data-time-end="<?php echo $time_end; ?>">
				</td>
				<?php endif; ?>
			<?php endforeach; ?>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
</div>

<div id="day-panel-overlay"></div>
<div id="day-panel">
	<button type="button" class="btn btn-default btn-xs pull-right" id="btn-close-panel"><i class="fa fa-close"></i></button>
	<h4 id="panel-title">&nbsp;</h4>

	<form id="form-appointment" autocomplete="off" spellcheck="false">
		<div class="form-group" id="client-results">
			<label>Cliente:</label>
			<input type="text" class="form-control" id="client-search" placeholder="Buscar cliente por nombre o apellido">
			<div id="client-results-list"></div>
			<input type="hidden" name="id_client" id="id_client">
			<span class="help-block" id="selected-client"></span>
		</div>
		<div class="form-group">
			<label>Servicios:</label>
			<div class="service-list">
				<?php foreach( $service_categories as $cat ) : ?>
				<div class="category-box">
					<div class="category-header">
						<span><?php echo $cat['name']; ?></span>
						<span class="fa fa-chevron-down"></span>
					</div>
					<div class="category-body">
						<?php foreach( $cat['services'] as $s ) : ?>
						<label class="checkbox-inline" style="display:block;">
							<input type="checkbox" name="id_products[]" value="<?php echo $s->id_product; ?>"> <?php echo $s->name; ?>
						</label>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
				<?php if( !$has_services ) : ?>
				<span class="text-muted">No hay servicios activos registrados</span>
				<?php endif; ?>
			</div>
		</div>
		<div class="row">
			<div class="col-xs-6">
				<label>Desde:</label>
				<input type="time" class="form-control" id="start_time">
			</div>
			<div class="col-xs-6">
				<label>Hasta:</label>
				<input type="time" class="form-control" id="end_time">
			</div>
		</div>
		<div class="form-group">
			<label>Notas:</label>
			<textarea class="form-control" name="notes" id="notes" rows="2"></textarea>
		</div>
		<input type="hidden" name="id_employee" id="id_employee">
		<input type="hidden" id="date_start">
		<input type="hidden" id="date_end">
		<input type="hidden" id="id_appointment">
		<input type="hidden" id="schedule_date" value="<?php echo $date; ?>">
		<input type="hidden" name="tk" id="tk" value="<?php echo $tk; ?>">
		<button type="submit" class="btn btn-primary" id="btn-save-appointment">Guardar reserva</button>
	</form>
</div>

<script>
var baseUrl = '<?php echo base_url(); ?>';

function notify(text, type){
	new PNotify({
		title: 'Notificación',
		text: text,
		type: type,
		styling: 'bootstrap3',
		delay: 3500
	});
}

function collapseAllCategories(){
	$('.category-body').hide();
	$('.category-header').removeClass('open');
}

function expandCategoriesWithChecked(){
	$('input[name="id_products[]"]:checked').each(function(){
		$(this).closest('.category-body').show().prev('.category-header').addClass('open');
	});
}

function openBooking(cell){

	$('#panel-title').text('Nueva reserva — ' + cell.data('employee-label') + ', ' + cell.data('time-start') + ' - ' + cell.data('time-end'));
	$('#btn-save-appointment').text('Guardar reserva');

	$('#id_appointment').val('');
	$('#id_employee').val(cell.data('employee'));
	$('#date_start').val(cell.data('date-start'));
	$('#date_end').val(cell.data('date-end'));
	$('#start_time').val(cell.data('time-start'));
	$('#end_time').val(cell.data('time-end'));

	$('#id_client').val('');
	$('#client-search').val('');
	$('#selected-client').text('');
	$('input[name="id_products[]"]').prop('checked', false);
	$('#notes').val('');
	collapseAllCategories();

	$('#day-panel, #day-panel-overlay').addClass('open');
	$('body').addClass('panel-open');
}

function openEditBooking(cell){

	$('#panel-title').text('Editar reserva — ' + cell.data('employee-label') + ', ' + cell.data('time-start') + ' - ' + cell.data('time-end'));
	$('#btn-save-appointment').text('Actualizar reserva');

	$('#id_appointment').val(cell.data('id'));
	$('#id_employee').val(cell.data('employee'));
	$('#date_start').val(cell.data('date-start'));
	$('#date_end').val(cell.data('date-end'));
	$('#start_time').val(cell.data('time-start'));
	$('#end_time').val(cell.data('time-end'));

	$('#id_client').val(cell.data('client-id'));
	$('#client-search').val(cell.data('client-name'));
	$('#selected-client').text('');
	$('#client-results-list').hide().empty();

	var serviceIds = (cell.data('service-ids') || '').toString().split(',');
	$('input[name="id_products[]"]').prop('checked', false);
	$.each(serviceIds, function(i, id){
		$('input[name="id_products[]"][value="' + id + '"]').prop('checked', true);
	});

	collapseAllCategories();
	expandCategoriesWithChecked();

	$('#notes').val(cell.data('notes') || '');

	$('#day-panel, #day-panel-overlay').addClass('open');
	$('body').addClass('panel-open');
}

function confirmDelete(id_appointment){

	(new PNotify({
		title: 'Confirmación',
		text: '¿Desea eliminar esta reserva?',
		icon: 'glyphicon glyphicon-question-sign',
		hide: false,
		confirm: {
			confirm: true,
			buttons: [
				{ text: 'OK', addClass: 'btn-default' },
				{ text: 'Cancelar', addClass: 'btn-default' }
			]
		},
		buttons: { closer: true, sticker: false, closer_hover: false },
		history: { history: false }
	})).get().on('pnotify.confirm', function(){

		$.post(baseUrl + 'appointments/delete/' + id_appointment, function(data){

			notify(data.message, data.type);

			if( data.type === 'success' ){
				window.location.reload();
			}
		}, 'json');
	});
}

$(function(){

	$('.cell-empty').on('click', function(){
		openBooking( $(this) );
	});

	$('.cell-occupied').on('click', function(){
		openEditBooking( $(this) );
	});

	$('.btn-delete-appt').on('click', function(e){
		e.stopPropagation();
		confirmDelete( $(this).data('id') );
	});

	$('.category-header').on('click', function(){
		$(this).toggleClass('open').next('.category-body').slideToggle(200);
	});

	$('#btn-close-panel, #day-panel-overlay').on('click', function(){
		$('#day-panel, #day-panel-overlay').removeClass('open');
		$('body').removeClass('panel-open');
	});

	$('#client-search').on('keyup', function(){

		var name = $(this).val();
		$('#id_client').val('');

		if( name.length < 2 ){
			$('#client-results-list').hide().empty();
			return;
		}

		$.post(baseUrl + 'clients/searchClients', { term: name }, function(data){

			var list = $('#client-results-list').empty();

			if( !data.clients || data.clients.length === 0 ){
				list.hide();
				return;
			}

			$.each(data.clients, function(i, c){
				var a = $('<a href="javascript:;"></a>').text(c.name_client + ' ' + c.surname_client);
				a.on('click', function(){
					$('#id_client').val(c.id_client);
					$('#client-search').val(c.name_client + ' ' + c.surname_client);
					list.hide();
				});
				list.append(a);
			});

			list.show();

		}, 'json');
	});

	$('#form-appointment').on('submit', function(){

		var id_client = $('#id_client').val();
		var id_employee = $('#id_employee').val();

		if( !id_employee ){
			notify('Este horario no tiene un empleado asignado', 'error');
			return false;
		}

		if( !id_client ){
			notify('Debe seleccionar un cliente', 'error');
			return false;
		}

		var id_products = $('input[name="id_products[]"]:checked').map(function(){ return this.value; }).get();

		if( id_products.length === 0 ){
			notify('Debe seleccionar al menos un servicio', 'error');
			return false;
		}

		var start_datetime = $('#date_start').val() + ' ' + $('#start_time').val() + ':00';
		var end_datetime = $('#date_end').val() + ' ' + $('#end_time').val() + ':00';

		var id_appointment = $('#id_appointment').val();
		var url = id_appointment ? (baseUrl + 'appointments/update/' + id_appointment) : (baseUrl + 'appointments/register');

		var btn = $('#btn-save-appointment').attr('disabled', 'true');

		$.post(url, {
			id_client: id_client,
			id_employee: id_employee,
			start_datetime: start_datetime,
			end_datetime: end_datetime,
			notes: $('#notes').val(),
			id_products: id_products,
			schedule_date: $('#schedule_date').val(),
			tk: $('#tk').val()
		}, function(data){

			notify(data.message, data.type);
			btn.removeAttr('disabled');

			if( data.type === 'success' ){
				window.location.reload();
			}

		}, 'json').fail(function(){

			notify('Ha ocurrido un error guardando la reserva', 'error');
			btn.removeAttr('disabled');
		});

		return false;
	});
});
</script>
