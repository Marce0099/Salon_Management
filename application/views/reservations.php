<div class="page-title" style="margin-bottom:10px;">
	<h3 style="display:inline-block;">Reservas</h3>
	<button type="button" class="btn btn-success pull-right" id="btn-new"><i class="fa fa-plus"></i> Nueva reserva</button>
</div>
<div class="clearfix"></div>

<form method="get" action="<?php echo base_url('reservations'); ?>" class="form-inline" style="margin-bottom:12px;">
	<label for="f-date">Fecha:</label>
	<input type="date" name="date" id="f-date" class="form-control" value="<?php echo htmlspecialchars($date); ?>">
	<button type="submit" class="btn btn-default">Ver</button>
	<a href="<?php echo base_url('reservations'); ?>" class="btn btn-default">Hoy</a>
</form>

<?php
$labels = array('active' => 'Activa', 'seated' => 'Sentada', 'cancelled' => 'Cancelada', 'no_show' => 'No llegó');
$classes = array('active' => 'warning', 'seated' => 'success', 'cancelled' => 'default', 'no_show' => 'danger');
?>

<?php if( empty($reservations) ) : ?>
<p class="alert alert-info">No hay reservas para esta fecha.</p>
<?php else : ?>
<table class="table table-striped table-hover">
	<thead><tr><th>Hora</th><th>Mesa</th><th>Cliente</th><th>Teléfono</th><th>Personas</th><th>Notas</th><th>Estado</th><th></th></tr></thead>
	<tbody>
	<?php foreach( $reservations as $r ) : ?>
		<tr>
			<td><?php echo date('H:i', strtotime($r->start_at)); ?> - <?php echo date('H:i', strtotime($r->end_at)); ?></td>
			<td><?php echo htmlspecialchars($r->table_label); ?> <small class="text-muted">(<?php echo htmlspecialchars($r->area_name); ?>)</small></td>
			<td><?php echo htmlspecialchars($r->name); ?></td>
			<td><?php echo htmlspecialchars((string) $r->phone); ?></td>
			<td><?php echo (int) $r->party_size; ?></td>
			<td><?php echo htmlspecialchars((string) $r->notes); ?></td>
			<td><span class="label label-<?php echo $classes[$r->status]; ?>"><?php echo $labels[$r->status]; ?></span></td>
			<td>
				<?php if( $r->status === 'active' ) : ?>
				<button type="button" class="btn btn-xs btn-success js-seat" data-id="<?php echo (int) $r->id_reservation; ?>">Sentar</button>
				<button type="button" class="btn btn-xs btn-default js-noshow" data-id="<?php echo (int) $r->id_reservation; ?>">No llegó</button>
				<button type="button" class="btn btn-xs btn-danger js-cancel" data-id="<?php echo (int) $r->id_reservation; ?>">Cancelar</button>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<div class="modal fade" id="res-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Nueva reserva</h4></div>
		<div class="modal-body">
			<div class="form-group"><label for="r-table">Mesa</label>
				<select id="r-table" class="form-control">
					<option value="">---</option>
					<?php foreach( $tables as $t ) : ?>
					<option value="<?php echo (int) $t->id_table; ?>" <?php echo $preselect_table === (int) $t->id_table ? 'selected' : ''; ?>><?php echo htmlspecialchars($t->label.' · '.$t->area_name.' · '.$t->seats.' asientos'); ?></option>
					<?php endforeach; ?>
				</select></div>
			<div class="form-group"><label for="r-name">Nombre del cliente</label><input type="text" id="r-name" class="form-control" maxlength="120"></div>
			<div class="form-group"><label for="r-phone">Teléfono</label><input type="tel" id="r-phone" class="form-control" maxlength="30"></div>
			<div class="row">
				<div class="col-xs-6"><div class="form-group"><label for="r-date">Fecha</label><input type="date" id="r-date" class="form-control" value="<?php echo htmlspecialchars($date); ?>"></div></div>
				<div class="col-xs-6"><div class="form-group"><label for="r-time">Hora</label><input type="time" id="r-time" class="form-control" value="19:00"></div></div>
			</div>
			<div class="row">
				<div class="col-xs-6"><div class="form-group"><label for="r-party">Personas</label><input type="number" id="r-party" class="form-control" min="1" max="99" value="2"></div></div>
				<div class="col-xs-6"><div class="form-group"><label for="r-dur">Duración (min)</label><input type="number" id="r-dur" class="form-control" min="15" max="480" step="15" value="<?php echo (int) $default_minutes; ?>"></div></div>
			</div>
			<div class="form-group"><label for="r-notes">Notas</label><input type="text" id="r-notes" class="form-control" maxlength="255"></div>
		</div>
		<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button><button type="button" class="btn btn-success" id="r-save">Guardar reserva</button></div>
	</div></div>
</div>

<div class="modal fade" id="seat-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document"><div class="modal-content">
		<div class="modal-header"><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button><h4 class="modal-title">Sentar reserva</h4></div>
		<div class="modal-body"><div class="form-group"><label for="s-waiter">Mesero</label>
			<select id="s-waiter" class="form-control"><option value="">---</option>
			<?php foreach( $employees as $e ) : ?><option value="<?php echo (int) $e->id_employee; ?>"><?php echo htmlspecialchars($e->name); ?></option><?php endforeach; ?>
			</select></div></div>
		<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button><button type="button" class="btn btn-success" id="s-ok">Abrir mesa</button></div>
	</div></div>
</div>

<script>
(function(){
	var TK = <?php echo json_encode($tk); ?>;
	var BASE = '<?php echo base_url('reservations'); ?>/';
	var seatId = 0;

	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 2500 }); }

	function post(url, data){
		data.tk = TK;
		$.post(BASE + url, data, function(r){
			notify(r.type === 'success' ? 'success' : 'error', r.message);
			if( r.type === 'success' ){ setTimeout(function(){ location.href = BASE + '?date=' + encodeURIComponent($('#f-date').val()); }, 700); }
		}, 'json').fail(function(){ notify('error', 'Error de comunicación con el servidor'); });
	}

	$('#btn-new').on('click', function(){ $('#res-modal').modal('show'); });
	<?php if( $preselect_table ) : ?>$(window).on('load', function(){ $('#res-modal').modal('show'); });<?php endif; ?>

	$('#r-save').on('click', function(){
		post('save', { id_table: $('#r-table').val(), name: $('#r-name').val(), phone: $('#r-phone').val(), party_size: $('#r-party').val(),
			date: $('#r-date').val(), time: $('#r-time').val(), duration: $('#r-dur').val(), notes: $('#r-notes').val() });
	});

	$('.js-cancel').on('click', function(){
		var id = $(this).data('id');
		bootbox.confirm('¿Cancelar esta reserva?', function(ok){ if( ok ){ post('cancel', { id_reservation: id }); } });
	});
	$('.js-noshow').on('click', function(){ post('no_show', { id_reservation: $(this).data('id') }); });

	$('.js-seat').on('click', function(){ seatId = $(this).data('id'); $('#seat-modal').modal('show'); });
	$('#s-ok').on('click', function(){ post('seat', { id_reservation: seatId, id_employee: $('#s-waiter').val() }); });
})();
</script>
