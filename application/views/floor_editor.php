<div class="page-title" style="margin-bottom:10px;">
	<h3 style="display:inline-block;">Editar plano de mesas</h3>
	<a href="<?php echo base_url('floor'); ?>" class="btn btn-default pull-right"><i class="fa fa-arrow-left"></i> Ver plano</a>
</div>
<div class="clearfix"></div>

<div class="area-tabs" id="area-tabs"></div>
<div style="margin-bottom:10px;">
	<button type="button" class="btn btn-default btn-sm" id="area-add"><i class="fa fa-plus"></i> Área</button>
	<button type="button" class="btn btn-default btn-sm" id="area-rename"><i class="fa fa-pencil"></i> Renombrar área</button>
	<button type="button" class="btn btn-default btn-sm" id="area-del"><i class="fa fa-trash"></i> Eliminar área</button>
	<button type="button" class="btn btn-success btn-sm" id="table-add"><i class="fa fa-plus"></i> Agregar mesa</button>
</div>

<div class="row">
	<div class="col-md-9">
		<div class="floor-wrap"><div class="floor-canvas editing" id="canvas"></div></div>
		<p class="text-muted" style="margin-top:8px;">Arrastre una mesa para moverla y use el cuadro naranja para cambiar su tamaño. Los cambios se guardan al soltar.</p>
	</div>
	<div class="col-md-3">
		<div class="x_panel" id="props" style="display:none;">
			<div class="x_title"><h2 style="font-size:16px;">Mesa seleccionada</h2><div class="clearfix"></div></div>
			<div class="form-group"><label>Etiqueta</label><input type="text" id="p-label" class="form-control" maxlength="30"></div>
			<div class="form-group"><label>Asientos</label><input type="number" id="p-seats" class="form-control" min="1" max="50"></div>
			<div class="form-group"><label>Forma</label>
				<select id="p-shape" class="form-control"><option value="square">Cuadrada / rectangular</option><option value="round">Redonda</option></select>
			</div>
			<button type="button" class="btn btn-primary btn-block" id="p-save">Guardar</button>
			<button type="button" class="btn btn-danger btn-block" id="p-del">Eliminar mesa</button>
		</div>
	</div>
</div>

<script>
(function(){

	var TK = <?php echo json_encode($tk); ?>;
	var areas = <?php echo json_encode(array_map(function($a){ return array('id' => (int) $a->id_area, 'name' => $a->name); }, $areas)); ?>;
	var areaId = areas.length ? areas[0].id : 0;
	var tables = {};
	var selected = null;
	var $canvas = $('#canvas');

	function notify(type, text){
		new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 2500 });
	}
	function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }

	function post(url, data, ok){
		data.tk = TK;
		return $.post('<?php echo base_url('floor'); ?>/' + url, data, function(r){
			if( r.type !== 'success' ){ notify('error', r.message); return; }
			ok && ok(r);
		}, 'json').fail(function(){ notify('error', 'Error de comunicación con el servidor'); });
	}

	function renderAreas(){
		var $t = $('#area-tabs').empty();
		$.each(areas, function(_, a){
			$t.append($('<button type="button" class="btn btn-default"></button>').text(a.name).attr('data-area', a.id).toggleClass('active', a.id === areaId));
		});
	}

	function draw(t){
		var $el = $canvas.find('[data-id="' + t.id_table + '"]');
		if( !$el.length ){
			$el = $('<div class="floor-table"><div class="t-label"></div><div class="t-sub"></div><div class="floor-handle"></div></div>').attr('data-id', t.id_table).appendTo($canvas);
		}
		$el.toggleClass('round', t.shape === 'round').toggleClass('selected', selected === t.id_table)
			.css({ left: t.x + '%', top: t.y + '%', width: t.w + '%', height: t.h + '%' });
		$el.find('.t-label').text(t.label);
		$el.find('.t-sub').html(t.seats + ' <i class="fa fa-user"></i>');
	}

	function load(){
		$canvas.empty(); tables = {}; select(null);
		if( !areaId ){ return; }
		$.getJSON('<?php echo base_url('floor/state'); ?>/' + areaId, function(r){
			$.each(r.tables, function(_, t){
				t.x = parseFloat(t.x); t.y = parseFloat(t.y); t.w = parseFloat(t.w); t.h = parseFloat(t.h);
				tables[t.id_table] = t; draw(t);
			});
		});
	}

	function select(id){
		selected = id;
		$canvas.find('.floor-table').removeClass('selected');
		if( id === null ){ $('#props').hide(); return; }
		var t = tables[id];
		$canvas.find('[data-id="' + id + '"]').addClass('selected');
		$('#p-label').val(t.label); $('#p-seats').val(t.seats); $('#p-shape').val(t.shape);
		$('#props').show();
	}

	function persistGeometry(t){
		post('table_save', { id_table: t.id_table, x: t.x, y: t.y, w: t.w, h: t.h });
	}

	/* ---- drag + resize (pointer events: mouse and touch) ---- */
	var drag = null;

	$canvas.on('pointerdown', '.floor-table', function(e){
		var $el = $(this), id = $el.data('id'), t = tables[id];
		if( !t ){ return; }
		select(id);
		var rect = $canvas[0].getBoundingClientRect();
		drag = {
			mode: $(e.target).hasClass('floor-handle') ? 'resize' : 'move',
			id: id, sx: e.clientX, sy: e.clientY, ox: t.x, oy: t.y, ow: t.w, oh: t.h,
			cw: rect.width, ch: rect.height, moved: false
		};
		this.setPointerCapture && this.setPointerCapture(e.originalEvent.pointerId);
		e.preventDefault();
	});

	$canvas.on('pointermove', '.floor-table', function(e){
		if( !drag ){ return; }
		var t = tables[drag.id];
		var dx = (e.clientX - drag.sx) / drag.cw * 100, dy = (e.clientY - drag.sy) / drag.ch * 100;
		if( Math.abs(dx) + Math.abs(dy) > 0.3 ){ drag.moved = true; }
		if( drag.mode === 'move' ){
			t.x = Math.round(Math.max(0, Math.min(100 - t.w, drag.ox + dx)) * 10) / 10;
			t.y = Math.round(Math.max(0, Math.min(100 - t.h, drag.oy + dy)) * 10) / 10;
		} else {
			t.w = Math.round(Math.max(5, Math.min(100 - t.x, drag.ow + dx)) * 10) / 10;
			t.h = Math.round(Math.max(5, Math.min(100 - t.y, drag.oh + dy)) * 10) / 10;
		}
		draw(t);
	});

	$canvas.on('pointerup pointercancel', '.floor-table', function(){
		if( drag && drag.moved ){ persistGeometry(tables[drag.id]); }
		drag = null;
	});

	$canvas.on('pointerdown', function(e){ if( e.target === $canvas[0] ){ select(null); } });

	/* ---- areas ---- */
	$('#area-tabs').on('click', 'button', function(){ areaId = $(this).data('area'); renderAreas(); load(); });

	$('#area-add').on('click', function(){
		bootbox.prompt('Nombre del área (ej. Terraza)', function(name){
			if( !name ){ return; }
			post('area_save', { name: name }, function(r){ areas.push({ id: r.id_area, name: r.name }); areaId = r.id_area; renderAreas(); load(); });
		});
	});

	$('#area-rename').on('click', function(){
		var cur = areas.filter(function(a){ return a.id === areaId; })[0];
		if( !cur ){ return; }
		bootbox.prompt({ title: 'Nuevo nombre del área', value: cur.name, callback: function(name){
			if( !name ){ return; }
			post('area_save', { id_area: areaId, name: name }, function(r){ cur.name = r.name; renderAreas(); });
		}});
	});

	$('#area-del').on('click', function(){
		bootbox.confirm('¿Eliminar esta área y todas sus mesas?', function(ok){
			if( !ok ){ return; }
			post('area_delete', { id_area: areaId }, function(){
				areas = areas.filter(function(a){ return a.id !== areaId; });
				areaId = areas.length ? areas[0].id : 0; renderAreas(); load();
			});
		});
	});

	/* ---- tables ---- */
	$('#table-add').on('click', function(){
		var n = Object.keys(tables).length + 1, label = 'M' + n;
		while( $.map(tables, function(t){ return t.label === label ? 1 : null; }).length ){ n++; label = 'M' + n; }
		var k = Object.keys(tables).length, px = 4 + (k % 6) * 15, py = 4 + (Math.floor(k / 6) % 5) * 17;
		post('table_save', { id_area: areaId, label: label, seats: 4, x: px, y: py, w: 12, h: 12 }, function(r){
			var t = { id_table: r.id_table, label: label, seats: 4, x: px, y: py, w: 12, h: 12, shape: 'square' };
			tables[t.id_table] = t; draw(t); select(t.id_table);
		});
	});

	$('#p-save').on('click', function(){
		var t = tables[selected]; if( !t ){ return; }
		post('table_save', { id_table: t.id_table, label: $('#p-label').val(), seats: $('#p-seats').val(), shape: $('#p-shape').val() }, function(){
			t.label = $.trim($('#p-label').val()); t.seats = parseInt($('#p-seats').val(), 10); t.shape = $('#p-shape').val();
			draw(t); notify('success', 'Mesa guardada');
		});
	});

	$('#p-del').on('click', function(){
		var t = tables[selected]; if( !t ){ return; }
		bootbox.confirm('¿Eliminar la mesa ' + esc(t.label) + '?', function(ok){
			if( !ok ){ return; }
			post('table_delete', { id_table: t.id_table }, function(){
				$canvas.find('[data-id="' + t.id_table + '"]').remove(); delete tables[t.id_table]; select(null);
			});
		});
	});

	renderAreas(); load();
})();
</script>
