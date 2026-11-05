<div class="page-title" style="margin-bottom:10px;">
	<h3 style="display:inline-block;">Plano de mesas</h3>
	<?php if( $is_admin ) : ?>
	<a href="<?php echo base_url('floor/editor'); ?>" class="btn btn-default pull-right"><i class="fa fa-pencil"></i> Editar plano</a>
	<?php endif; ?>
</div>
<div class="clearfix"></div>

<?php if( empty($areas) ) : ?>
<p class="alert alert-info">No hay áreas configuradas.</p>
<?php else : ?>

<div class="area-tabs" id="area-tabs">
	<?php foreach( $areas as $i => $a ) : ?>
	<button type="button" class="btn btn-default <?php echo $i === 0 ? 'active' : ''; ?>" data-area="<?php echo (int) $a->id_area; ?>"><?php echo htmlspecialchars($a->name); ?></button>
	<?php endforeach; ?>
</div>

<div class="floor-legend" style="margin-bottom:8px;">
	<span><i style="background:#1ABB9C"></i>Libre</span>
	<span><i style="background:#E74C3C"></i>Ocupada</span>
	<span><i style="background:#F39C12"></i>Reservada</span>
</div>

<div class="floor-wrap"><div class="floor-canvas" id="canvas"></div></div>
<p class="text-muted" id="empty-note" style="display:none; margin-top:10px;">Esta área aún no tiene mesas<?php echo $is_admin ? '. Use «Editar plano» para agregarlas.' : '.'; ?></p>

<!-- table modal -->
<div class="modal fade" id="table-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title" id="tm-title"></h4>
			</div>
			<div class="modal-body">
				<div id="tm-free">
					<div class="form-group">
						<label for="tm-waiter">Mesero</label>
						<select id="tm-waiter" class="form-control">
							<option value="">---</option>
							<?php foreach( $employees as $e ) : ?>
							<option value="<?php echo (int) $e->id_employee; ?>"><?php echo htmlspecialchars($e->name); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="form-group">
						<label for="tm-guests">Comensales</label>
						<input type="number" id="tm-guests" class="form-control" min="1" max="99" value="2">
					</div>
				</div>
				<div id="tm-occupied" style="display:none;">
					<p><strong>Mesero:</strong> <span id="tm-o-waiter"></span></p>
					<p><strong>Comensales:</strong> <span id="tm-o-guests"></span></p>
					<p><strong>Abierta desde:</strong> <span id="tm-o-since"></span></p>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
				<button type="button" class="btn btn-success" id="tm-open">Abrir mesa</button>
				<button type="button" class="btn btn-warning" id="tm-release" style="display:none;">Liberar mesa</button>
			</div>
		</div>
	</div>
</div>

<script>
(function(){

	var TK = <?php echo json_encode($tk); ?>;
	var EMP = <?php echo json_encode(array()); ?>;
	var areaId = <?php echo (int) $areas[0]->id_area; ?>;
	var tables = {};
	var current = null;
	var timer = null;

	function notify(type, text){
		new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 2500 });
	}

	function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }

	function render(list){
		var $c = $('#canvas').empty();
		tables = {};
		$.each(list, function(_, t){
			tables[t.id_table] = t;
			var $t = $('<div class="floor-table"></div>')
				.addClass(t.state).addClass(t.shape === 'round' ? 'round' : '')
				.css({ left: t.x + '%', top: t.y + '%', width: t.w + '%', height: t.h + '%' })
				.attr('data-id', t.id_table)
				.html('<div class="t-label">' + esc(t.label) + '</div><div class="t-sub">' +
					(t.state === 'occupied' ? esc(t.waiter || '') : t.seats + ' <i class="fa fa-user"></i>') + '</div>');
			$c.append($t);
		});
		$('#empty-note').toggle(list.length === 0);
	}

	function load(){
		$.getJSON('<?php echo base_url('floor/state'); ?>/' + areaId, function(r){
			if( r.type === 'success' ){ render(r.tables); }
		});
	}

	function startPolling(){
		clearInterval(timer);
		timer = setInterval(function(){
			if( !document.hidden && !$('#table-modal').hasClass('in') ){ load(); }
		}, 10000);
	}

	$('#area-tabs').on('click', 'button', function(){
		areaId = $(this).data('area');
		$('#area-tabs button').removeClass('active');
		$(this).addClass('active');
		load();
	});

	$('#canvas').on('click', '.floor-table', function(){
		var t = tables[$(this).data('id')];
		if( !t ){ return; }
		current = t;
		$('#tm-title').text('Mesa ' + t.label + ' · ' + t.seats + ' asientos');
		if( t.state === 'occupied' ){
			$('#tm-free, #tm-open').hide();
			$('#tm-occupied, #tm-release').show();
			$('#tm-o-waiter').text(t.waiter || '—');
			$('#tm-o-guests').text(t.guests);
			$('#tm-o-since').text(t.opened_at);
		} else {
			$('#tm-free, #tm-open').show();
			$('#tm-occupied, #tm-release').hide();
			$('#tm-guests').val(Math.min(2, t.seats));
		}
		$('#table-modal').modal('show');
	});

	function post(url, data, done){
		data.tk = TK;
		$.post('<?php echo base_url('floor'); ?>/' + url, data, function(r){
			notify(r.type === 'success' ? 'success' : 'error', r.message);
			if( r.type === 'success' ){ $('#table-modal').modal('hide'); done && done(r); }
			load();
		}, 'json').fail(function(){ notify('error', 'Error de comunicación con el servidor'); });
	}

	$('#tm-open').on('click', function(){
		post('open_tab', { id_table: current.id_table, id_employee: $('#tm-waiter').val(), guests: $('#tm-guests').val() });
	});

	$('#tm-release').on('click', function(){
		post('close_tab', { id_tab: current.id_tab });
	});

	load();
	startPolling();
})();
</script>
<?php endif; ?>
