<?php if( !function_exists('ing_n') ){ function ing_n($v){ return rtrim(rtrim(number_format((float) $v, 3, '.', ','), '0'), '.'); } } ?>
<h3 class="section-title">Ingredientes</h3>

<?php if( !empty($low) ) : ?>
<div class="alert alert-warning">
	<strong><i class="fa fa-exclamation-triangle"></i> Existencia baja:</strong>
	<?php foreach( $low as $i => $l ) : ?><?php echo ($i ? ', ' : '').htmlspecialchars($l->name).' ('.ing_n($l->stock).' '.$l->unit.')'; ?><?php endforeach; ?>
</div>
<?php endif; ?>

<form action="<?php echo base_url('ingredients'); ?>" autocomplete="off" style="margin-bottom:15px;">
	<div class="row">
		<div class="col-sm-4 col-xs-12">
			<input type="text" name="name" class="form-control" placeholder="Buscar ingrediente" value="<?php echo htmlspecialchars($q); ?>">
		</div>
		<div class="col-sm-3 col-xs-12" style="padding-top:6px;">
			<label><input type="checkbox" name="inactive" value="1" <?php echo $show_inactive ? 'checked' : ''; ?>> Ver desactivados</label>
		</div>
		<div class="col-sm-5 col-xs-12 text-right">
			<button type="submit" class="btn btn-default">Buscar</button>
			<a href="<?php echo base_url('ingredients/register'); ?>" class="btn btn-success">Nuevo ingrediente</a>
		</div>
	</div>
</form>

<?php if( empty($ingredients) ) : ?>
<p class="alert alert-info">No hay ingredientes <?php echo $show_inactive ? 'desactivados' : 'registrados'; ?></p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-hover table-striped jambo_table">
	<thead>
		<tr><th>Ingrediente</th><th class="text-right">Existencia</th><th class="text-right">Alerta</th><th class="text-right">Costo / unidad</th><th>Presentación</th><th>Acciones</th></tr>
	</thead>
	<tbody>
		<?php foreach( $ingredients as $i ) : $is_low = $i->alert_threshold > 0 && $i->stock <= $i->alert_threshold; ?>
		<tr class="ing-row" data-id="<?php echo $i->id_ingredient; ?>" data-name="<?php echo htmlspecialchars($i->name); ?>" data-unit="<?php echo $i->unit; ?>" data-pack="<?php echo htmlspecialchars((string) $i->pack_name); ?>" data-packsize="<?php echo $i->pack_size !== NULL ? ing_n($i->pack_size) : ''; ?>">
			<td><?php echo htmlspecialchars($i->name); ?></td>
			<td class="text-right"><span class="<?php echo $is_low ? 'text-danger' : ''; ?>"><strong><?php echo ing_n($i->stock).' '.$i->unit; ?></strong></span><?php if( $is_low ) : ?> <span class="label label-danger">Bajo</span><?php endif; ?></td>
			<td class="text-right"><?php echo $i->alert_threshold > 0 ? ing_n($i->alert_threshold).' '.$i->unit : '—'; ?></td>
			<td class="text-right"><?php echo $i->cost_unit !== NULL ? '$ '.rtrim(rtrim(number_format((float) $i->cost_unit, 6, '.', ''), '0'), '.') : '—'; ?></td>
			<td><?php echo $i->pack_name ? htmlspecialchars($i->pack_name).' = '.ing_n($i->pack_size).' '.$i->unit : '—'; ?></td>
			<td>
				<?php if( $i->active ) : ?>
				<button type="button" class="btn btn-success btn-xs btn-receive">Recibir</button>
				<button type="button" class="btn btn-warning btn-xs btn-adjust">Merma / conteo</button>
				<a href="<?php echo base_url('ingredients/movements/'.$i->id_ingredient); ?>" class="btn btn-default btn-xs">Historial</a>
				<a href="<?php echo base_url('ingredients/edit/'.$i->id_ingredient); ?>" class="btn btn-default btn-xs">Editar</a>
				<a href="<?php echo base_url('ingredients/delete/'.$i->id_ingredient); ?>" class="btn btn-danger btn-xs confirm-action" data-msg="¿Desactivar este ingrediente?">Desactivar</a>
				<?php else : ?>
				<a href="<?php echo base_url('ingredients/restore/'.$i->id_ingredient); ?>" class="btn btn-default btn-xs">Reactivar</a>
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<!-- Receive modal -->
<div class="modal fade" id="modal-receive" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Recibir: <span class="ing-name"></span></h4></div>
	<div class="modal-body">
		<div class="form-group" id="rc-mode-wrap">
			<label>Unidad de entrada</label>
			<select id="rc-mode" class="form-control"><option value="base">Unidad base</option><option value="pack"></option></select>
		</div>
		<div class="form-group"><label>Cantidad (<span id="rc-unit"></span>)</label><input type="text" inputmode="decimal" id="rc-qty" class="form-control"></div>
		<div class="form-group"><label>Costo total pagado ($, opcional)</label><input type="text" inputmode="decimal" id="rc-cost" class="form-control"></div>
		<div class="form-group"><label>Nota (opcional)</label><input type="text" id="rc-reason" maxlength="255" class="form-control"></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-success" id="rc-save">Guardar entrada</button></div>
</div></div></div>

<!-- Adjust modal -->
<div class="modal fade" id="modal-adjust" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
	<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title">Merma / conteo: <span class="ing-name"></span></h4></div>
	<div class="modal-body">
		<div class="form-group">
			<label>Acción</label>
			<select id="aj-kind" class="form-control"><option value="waste">Merma (descontar)</option><option value="count">Conteo físico (fijar existencia)</option></select>
		</div>
		<div class="form-group"><label><span id="aj-label">Cantidad a descontar</span> (<span id="aj-unit"></span>)</label><input type="text" inputmode="decimal" id="aj-qty" class="form-control"></div>
		<div class="form-group"><label>Motivo <span id="aj-req">(obligatorio)</span></label><input type="text" id="aj-reason" maxlength="255" class="form-control"></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button type="button" class="btn btn-warning" id="aj-save">Guardar</button></div>
</div></div></div>

<input type="hidden" id="ing-tk" value="<?php echo $tk; ?>">
<script>
(function(){
	var cur = null;
	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 3500 }); }
	function send(url, data, btn){
		data.tk = $('#ing-tk').val(); data.id_ingredient = cur.id;
		btn.prop('disabled', true);
		$.ajax({ url: url, type: 'post', data: data, dataType: 'json',
			success: function(r){
				if( r.tk ){ $('#ing-tk').val(r.tk); }
				notify(r.type, r.message);
				if( r.type === 'success' ){ setTimeout(function(){ location.reload(); }, 900); } else { btn.prop('disabled', false); }
			},
			error: function(){ notify('error', 'Ha ocurrido un error guardando los datos'); btn.prop('disabled', false); }
		});
	}
	function rowData(tr){ return { id: tr.data('id'), name: tr.data('name'), unit: tr.data('unit'), pack: tr.data('pack'), packsize: tr.data('packsize') }; }

	$('.btn-receive').on('click', function(){
		cur = rowData($(this).closest('tr'));
		$('#modal-receive .ing-name').text(cur.name); $('#rc-unit').text(cur.unit);
		$('#rc-qty, #rc-cost, #rc-reason').val('');
		var sel = $('#rc-mode'); sel.val('base');
		if( cur.pack ){ sel.find('option[value=pack]').text(cur.pack + ' (= ' + cur.packsize + ' ' + cur.unit + ')'); $('#rc-mode-wrap').show(); }
		else { $('#rc-mode-wrap').hide(); }
		$('#rc-save').prop('disabled', false);
		$('#modal-receive').modal('show');
	});
	$('#rc-mode').on('change', function(){ $('#rc-unit').text($(this).val() === 'pack' ? cur.pack : cur.unit); });
	$('#rc-save').on('click', function(){
		send('<?php echo base_url('ingredients/receive'); ?>', { mode: $('#rc-mode').val(), quantity: $('#rc-qty').val(), total_cost: $('#rc-cost').val(), reason: $('#rc-reason').val() }, $(this));
	});

	$('.btn-adjust').on('click', function(){
		cur = rowData($(this).closest('tr'));
		$('#modal-adjust .ing-name').text(cur.name); $('#aj-unit').text(cur.unit);
		$('#aj-kind').val('waste').trigger('change'); $('#aj-qty, #aj-reason').val('');
		$('#aj-save').prop('disabled', false);
		$('#modal-adjust').modal('show');
	});
	$('#aj-kind').on('change', function(){
		var w = $(this).val() === 'waste';
		$('#aj-label').text(w ? 'Cantidad a descontar' : 'Existencia contada'); $('#aj-req').text(w ? '(obligatorio)' : '(opcional)');
	});
	$('#aj-save').on('click', function(){
		send('<?php echo base_url('ingredients/adjust'); ?>', { kind: $('#aj-kind').val(), quantity: $('#aj-qty').val(), reason: $('#aj-reason').val() }, $(this));
	});
})();
</script>
