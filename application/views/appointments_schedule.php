<h3 class="section-title">Agenda &mdash; <?php echo date('d/m/Y', strtotime($date)); ?></h3>

<?php if( $date < date('Y-m-d') ) : ?>
<div class="alert alert-warning">Estás viendo una fecha pasada. No se pueden crear nuevas reservas ni reprogramar otras a esta fecha.</div>
<?php endif; ?>

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
	.schedule-grid td.cell-empty.cell-past{ cursor: not-allowed; background: #f7f7f7; color: #bbb; }
	.schedule-grid td.cell-empty.cell-past:hover{ background: #f7f7f7; }
	.schedule-grid td.cell-multi{ padding: 0; text-align: left; }
	.schedule-grid .appt-block{ padding: 4px; cursor: pointer; line-height: 1.2; }
	.schedule-grid .appt-block-divider{ border-top: 1px solid #c9c9c9; }
	.schedule-grid .appt-block.cell-occupied{ background: #eaf3ff; }
	.schedule-grid .appt-block.cell-occupied .time{ font-weight: bold; display: block; }
	.schedule-grid .appt-block.cell-occupied .services{ color: #666; list-style: disc; margin: 0; padding-left: 16px; }
	.schedule-grid .appt-block.cell-occupied .services li{ line-height: 1.2; }
	.schedule-grid .appt-block.cell-occupied .btn-delete-appt{ margin-top: 4px; }
	.schedule-grid .appt-block.cell-locked{ background: #f2f2f2; color: #888; }
	.schedule-grid .appt-block.cell-locked .services{ color: #999; }
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
	/* #edit-reason-modal is only ever opened while #day-panel (z-index 2000) is
	   showing, so Bootstrap's default modal/backdrop z-index (1050/1040) leaves
	   it stacked behind the panel — push both above it. */
	body.panel-open .modal{ z-index: 2100; }
	body.panel-open .modal-backdrop{ z-index: 2050; }
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
	#deposit-section{ border: 1px solid #e6e9ed; border-radius: 4px; padding: 10px; margin-bottom: 15px; background: #f9f9f9; }
	#deposit-summary{ border: 1px solid #e6e9ed; border-radius: 4px; padding: 10px; margin-bottom: 15px; background: #f2f9f2; }
	#required-deposit-badge{ font-size: 100%; }
	.deposit-mismatch{ border-color: #e74c3c !important; }
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
				<?php elseif( isset($col['hour_map'][$hour_index]) ) :
					$bucket = $col['hour_map'][$hour_index];
				?>
				<td rowspan="<?php echo $bucket['rowspan']; ?>" class="cell-multi">
					<?php foreach( $bucket['entries'] as $i => $entry ) :
						$a = $entry['appt'];
						$is_invoiced = !empty( $a->is_invoiced );
						$has_deposit = $a->deposit_cordoba !== NULL;
					?>
					<div class="appt-block <?php echo $is_invoiced ? 'cell-locked' : 'cell-occupied'; ?><?php echo $i > 0 ? ' appt-block-divider' : ''; ?>"
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
						data-service-ids="<?php echo htmlspecialchars((string) $a->service_ids); ?>"
						data-invoiced="<?php echo $is_invoiced ? 1 : 0; ?>"
						data-has-deposit="<?php echo $has_deposit ? 1 : 0; ?>"
						data-deposit-cordoba="<?php echo $has_deposit ? htmlspecialchars((string) $a->deposit_cordoba) : ''; ?>"
						data-deposit-dolar="<?php echo $has_deposit ? htmlspecialchars((string) $a->deposit_dolar) : ''; ?>"
						data-deposit-date="<?php echo $has_deposit ? htmlspecialchars((string) $a->deposit_date_time) : ''; ?>">
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
						<?php if( $is_invoiced ) : ?>
						<span class="label label-default">Facturada</span>
						<?php endif; ?>
						<?php if( $is_admin && !$is_invoiced ) : ?>
						<button type="button" class="btn btn-danger btn-xs btn-delete-appt" data-id="<?php echo $a->id_appointment; ?>">Cancelar</button>
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</td>
				<?php else :
					$date_start = substr( $hour['start'], 0, 10 );
					$time_start = substr( $hour['start'], 11, 5 );
					$date_end = substr( $hour['end'], 0, 10 );
					$time_end = substr( $hour['end'], 11, 5 );
					$is_cell_past = strtotime( $hour['end'] ) <= time();
				?>
				<td class="cell-empty<?php echo $is_cell_past ? ' cell-past' : ''; ?>"
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
							<input type="checkbox" name="id_products[]" value="<?php echo $s->id_product; ?>" data-price="<?php echo $s->price_sell; ?>"> <?php echo $s->name; ?>
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

		<div id="deposit-section">
			<label>Depósito (transferencia): <span id="required-deposit-badge" class="label label-warning">C$0.00 ($0.00)</span></label>
			<div class="form-group">
				<label>Cuenta bancaria:</label>
				<select class="form-control" id="deposit-account">
					<?php foreach( $accounts as $account ) : ?>
					<option value="<?php echo $account->token_account; ?>" data-type="<?php echo $account->type; ?>"><?php echo $account->name_account; ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="row">
				<div class="col-xs-6">
					<label>Córdobas:</label>
					<input type="text" class="form-control" id="deposit-cordoba">
				</div>
				<div class="col-xs-6">
					<label>Dólares:</label>
					<input type="text" class="form-control" id="deposit-dolar">
				</div>
			</div>
			<span class="help-block" id="deposit-mismatch-notice" style="color:#e74c3c;"></span>
		</div>

		<div id="deposit-summary" class="hide">
			<strong>Depósito ya cobrado:</strong>
			<div id="deposit-summary-text"></div>
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
		<div class="form-group hide" id="schedule-date-group">
			<label>Fecha de la reserva:</label>
			<input type="date" class="form-control" id="schedule_date_input">
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
		<button type="button" class="btn btn-success hide" id="btn-continue-to-sale">Continuar a venta</button>
	</form>
</div>

<div class="modal fade" id="edit-reason-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title">Motivo del cambio</h4>
			</div>
			<div class="modal-body">
				<div class="form-group">
					<label>Describa por qué se está modificando esta reserva:</label>
					<textarea class="form-control" id="edit-reason-input" rows="3"></textarea>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
				<button type="button" class="btn btn-primary" id="edit-reason-save">Guardar</button>
			</div>
		</div>
	</div>
</div>

<script>
var baseUrl = '<?php echo base_url(); ?>';
var sellRate = <?php echo $sell_rate; ?>;
var requiredDepositCordobas = 0;
var todayStr = '<?php echo date('Y-m-d'); ?>';

function notify(text, type){
	new PNotify({
		title: 'Notificación',
		text: text,
		type: type,
		styling: 'bootstrap3',
		delay: 3500
	});
}

function numericVal(id){
	return parseFloat( ($(id).val() || '0').replace(',', '') ) || 0;
}

function updateRequiredDeposit(){

	var total = 0;

	$('input[name="id_products[]"]:checked').each(function(){
		total += parseFloat( $(this).data('price') ) || 0;
	});

	requiredDepositCordobas = Math.round( total * sellRate * 0.10 * 100 ) / 100;

	var requiredDepositDolars = sellRate > 0 ? requiredDepositCordobas / sellRate : 0;

	$('#required-deposit-badge').text('C$' + requiredDepositCordobas.toFixed(2) + ' ($' + requiredDepositDolars.toFixed(2) + ')');

	checkDepositMismatch();
}

function checkDepositMismatch(){

	var cordoba = numericVal('#deposit-cordoba');
	var dolar = numericVal('#deposit-dolar');
	var entered = cordoba + ( dolar * sellRate );
	var diff = Math.abs( entered - requiredDepositCordobas );

	if( diff > 0.01 ){
		$('#deposit-cordoba, #deposit-dolar').addClass('deposit-mismatch');
		$('#deposit-mismatch-notice').text('El depósito debe ser exactamente C$' + requiredDepositCordobas.toFixed(2) + ' (ingresado: C$' + entered.toFixed(2) + ')');
		return false;
	}

	$('#deposit-cordoba, #deposit-dolar').removeClass('deposit-mismatch');
	$('#deposit-mismatch-notice').text('');
	return true;
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

function resetPanelInputsState(){

	$('#form-appointment :input').prop('disabled', false);
	$('#btn-save-appointment').show();
	$('#btn-continue-to-sale').addClass('hide');
}

function openBooking(cell){

	resetPanelInputsState();

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

	$('#deposit-section').removeClass('hide');
	$('#deposit-summary').addClass('hide');
	$('#schedule-date-group').addClass('hide');
	$('#deposit-account').prop('selectedIndex', 0);
	$('#deposit-cordoba, #deposit-dolar').val('');
	updateRequiredDeposit();

	$('#day-panel, #day-panel-overlay').addClass('open');
	$('body').addClass('panel-open');
}

function openEditBooking(cell){

	resetPanelInputsState();

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

	$('#deposit-section').addClass('hide');
	$('#schedule-date-group').removeClass('hide');
	$('#schedule_date_input').val(cell.data('date-start'));
	$('#schedule_date').val(cell.data('date-start'));

	if( cell.data('has-deposit') ){
		$('#deposit-summary').removeClass('hide');
		$('#deposit-summary-text').text(
			'C$' + parseFloat(cell.data('deposit-cordoba') || 0).toFixed(2) +
			' / $' + parseFloat(cell.data('deposit-dolar') || 0).toFixed(2) +
			(cell.data('deposit-date') ? ' — ' + cell.data('deposit-date') : '')
		);
	}else{
		$('#deposit-summary').addClass('hide');
	}

	if( cell.data('has-deposit') && !cell.data('invoiced') ){
		$('#btn-continue-to-sale').removeClass('hide').data('id', cell.data('id'));
	}else{
		$('#btn-continue-to-sale').addClass('hide');
	}

	$('#day-panel, #day-panel-overlay').addClass('open');
	$('body').addClass('panel-open');
}

function openReadOnlyBooking(cell){

	openEditBooking(cell);

	$('#panel-title').text('Reserva facturada — ' + cell.data('employee-label') + ', ' + cell.data('time-start') + ' - ' + cell.data('time-end'));
	$('#form-appointment :input').prop('disabled', true);
	$('#btn-save-appointment').hide();
	$('#btn-continue-to-sale').addClass('hide');
}

function confirmDelete(id_appointment){

	(new PNotify({
		title: 'Confirmación',
		text: '¿Desea cancelar esta reserva? El depósito del 10% no será reembolsado.',
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

		if( $(this).hasClass('cell-past') ){
			notify('No se pueden reservar citas en el pasado', 'error');
			return;
		}

		openBooking( $(this) );
	});

	$('.appt-block.cell-occupied').on('click', function(){
		openEditBooking( $(this) );
	});

	$('.appt-block.cell-locked').on('click', function(){
		openReadOnlyBooking( $(this) );
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

	$(document).on('change', 'input[name="id_products[]"]', updateRequiredDeposit);
	$(document).on('keyup', '#deposit-cordoba, #deposit-dolar', checkDepositMismatch);

	$('#schedule_date_input').attr('min', todayStr);

	$('#schedule_date_input').on('change', function(){

		var newDate = $(this).val();

		if( !newDate ) return;

		if( newDate < todayStr ){
			notify('No se pueden reprogramar reservas a fechas pasadas', 'error');
			$(this).val( $('#schedule_date').val() );
			return;
		}

		$('#date_start').val(newDate);
		$('#date_end').val(newDate);
		$('#schedule_date').val(newDate);
	});

	$('#btn-continue-to-sale').on('click', function(){

		var id = $(this).data('id');

		if( id ){
			window.location.href = baseUrl + 'sales/register/' + id;
		}
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

	var pendingUpdate = null;

	function submitAppointment(url, payload){

		var btn = $('#btn-save-appointment').attr('disabled', 'true');

		$.post(url, payload, function(data){

			notify(data.message, data.type);
			btn.removeAttr('disabled');

			if( data.type === 'success' ){
				window.location.reload();
			}

		}, 'json').fail(function(){

			notify('Ha ocurrido un error guardando la reserva', 'error');
			btn.removeAttr('disabled');
		});
	}

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

		var id_appointment = $('#id_appointment').val();

		if( !id_appointment && !checkDepositMismatch() ){
			notify('El depósito debe ser exactamente el 10% del total de servicios', 'error');
			return false;
		}

		var start_datetime = $('#date_start').val() + ' ' + $('#start_time').val() + ':00';
		var end_datetime = $('#date_end').val() + ' ' + $('#end_time').val() + ':00';

		var startDt = new Date( $('#date_start').val() + 'T' + $('#start_time').val() + ':00' );

		if( isNaN(startDt.getTime()) || startDt < new Date() ){
			notify('No se pueden reservar citas en el pasado', 'error');
			return false;
		}

		var payload = {
			id_client: id_client,
			id_employee: id_employee,
			start_datetime: start_datetime,
			end_datetime: end_datetime,
			notes: $('#notes').val(),
			id_products: id_products,
			schedule_date: $('#schedule_date').val(),
			tk: $('#tk').val()
		};

		if( !id_appointment ){

			payload.account = $('#deposit-account').val();
			payload.deposit_cordoba = $('#deposit-cordoba').val();
			payload.deposit_dolar = $('#deposit-dolar').val();

			submitAppointment(baseUrl + 'appointments/register', payload);

			return false;
		}

		var updateUrl = baseUrl + 'appointments/update/' + id_appointment;
		var checkBtn = $('#btn-save-appointment').attr('disabled', 'true');

		$.post(updateUrl, $.extend({}, payload, { validate_only: 1 }), function(data){

			checkBtn.removeAttr('disabled');

			if( data.type !== 'success' ){
				notify(data.message, data.type);
				return;
			}

			pendingUpdate = { url: updateUrl, payload: payload };
			$('#edit-reason-input').val('');
			$('#edit-reason-modal').modal('show');

		}, 'json').fail(function(){

			checkBtn.removeAttr('disabled');
			notify('Ha ocurrido un error validando la reserva', 'error');
		});

		return false;
	});

	$('#edit-reason-save').on('click', function(){

		var reason = $.trim( $('#edit-reason-input').val() );

		if( !reason ){
			notify('Debe indicar el motivo del cambio', 'error');
			return;
		}

		if( !pendingUpdate ) return;

		pendingUpdate.payload.edit_reason = reason;
		$('#edit-reason-modal').modal('hide');

		submitAppointment(pendingUpdate.url, pendingUpdate.payload);

		pendingUpdate = null;
	});
});
</script>
