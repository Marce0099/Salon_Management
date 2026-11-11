<?php
$is_edit = isset($ingredient);
$num = function($x){ return $x === NULL ? '' : rtrim(rtrim(number_format((float) $x, 6, '.', ''), '0'), '.'); };
$ingredient = isset($ingredient) ? $ingredient : NULL;
$is_prepared = $ingredient && $ingredient->batch_yield !== NULL;
$comp_json = array();
foreach( $components as $c ){ $comp_json[] = array('id' => (int) $c->id_component, 'qty' => rtrim(rtrim(number_format((float) $c->quantity, 3, '.', ''), '0'), '.')); }
$raw_costs = array();
foreach( $raw_choices as $r ){ $raw_costs[(int) $r->id_ingredient] = $r->cost_unit !== NULL ? (float) $r->cost_unit : NULL; }
$raw_json = array();
foreach( $raw_choices as $r ){ $raw_json[] = array('id' => (int) $r->id_ingredient, 'name' => $r->name, 'unit' => $r->unit); }
$e = function($f) use ($ingredient){ return isset($ingredient->$f) && $ingredient->$f !== NULL ? htmlspecialchars((string) $ingredient->$f) : ''; };
?>
<h4 class="section-title"><?php echo $is_edit ? 'Editar ingrediente' : 'Nuevo ingrediente'; ?></h4>

<div class="col-md-5">
	<form id="form" autocomplete="off" onsubmit="return false;" spellcheck="false">
		<div class="form-group"><label>Nombre</label><input type="text" name="name" id="name" maxlength="100" class="form-control" value="<?php echo $is_edit ? $e('name') : ''; ?>"></div>
		<div class="form-group">
			<label>Unidad base</label>
			<select name="unit" class="form-control">
				<?php foreach( $units as $k => $label ) : ?><option value="<?php echo $k; ?>" <?php echo $is_edit && $ingredient->unit === $k ? 'selected' : ''; ?>><?php echo $label; ?></option><?php endforeach; ?>
			</select>
			<small class="text-muted">Todo se descuenta en esta unidad (ej. gramos).</small>
		</div>
		<?php if( !$is_edit ) : ?>
		<div class="form-group"><label>Existencia inicial</label><input type="text" inputmode="decimal" name="stock" class="form-control" placeholder="0"></div>
		<?php else : ?>
		<p class="text-muted">La existencia se cambia con "Recibir" o "Merma / conteo" para dejar historial.</p>
		<?php endif; ?>
		<div class="form-group"><label>Alerta de existencia baja (opcional)</label><input type="text" inputmode="decimal" name="alert_threshold" class="form-control" value="<?php echo $is_edit && $ingredient->alert_threshold > 0 ? $num($ingredient->alert_threshold) : ''; ?>"></div>
		<div class="form-group"><label>Costo por unidad base ($, opcional)</label><input type="text" inputmode="decimal" name="cost_unit" class="form-control" value="<?php echo $is_edit ? $num($ingredient->cost_unit) : ''; ?>"><small class="text-muted">Se actualiza solo al recibir con costo total.</small></div>
		<fieldset style="border:1px solid #ddd; padding:10px; margin-bottom:15px;">
			<legend style="font-size:14px; width:auto; border:0; margin:0;">Presentación de compra (opcional)</legend>
			<div class="form-group"><label>Nombre (ej. Saco 25 lb)</label><input type="text" name="pack_name" maxlength="60" class="form-control" value="<?php echo $is_edit ? $e('pack_name') : ''; ?>"></div>
			<div class="form-group"><label>Contiene (en unidad base)</label><input type="text" inputmode="decimal" name="pack_size" class="form-control" value="<?php echo $is_edit ? $num($ingredient->pack_size) : ''; ?>"></div>
		</fieldset>
		<fieldset style="border:1px solid #ddd; padding:10px; margin-bottom:15px;">
			<legend style="font-size:14px; width:auto; border:0; margin:0;">Preparación en cocina (salsas, masas, marinados)</legend>
			<div class="checkbox"><label><input type="checkbox" id="prepared" name="prepared" value="1" <?php echo $is_prepared ? 'checked' : ''; ?>> Este ingrediente se prepara en lotes a partir de otros</label></div>
			<div id="prep-box" style="display:none;">
				<p class="text-muted" style="font-size:12px;">Los platillos descuentan la existencia de este ingrediente. Al "Preparar lote" se descuentan los componentes y se suma el rendimiento. Los componentes deben ser ingredientes simples.</p>
				<div class="form-group"><label>Rendimiento de un lote (en unidad base)</label><input type="text" inputmode="decimal" name="batch_yield" id="batch_yield" class="form-control" value="<?php echo $is_prepared ? $num($ingredient->batch_yield) : ''; ?>"></div>
				<div class="table-responsive"><table class="table table-condensed" id="comp-table" style="min-width:440px;">
					<thead><tr><th>Componente</th><th style="width:130px;">Cantidad por lote</th><th style="width:50px;">Unid.</th><th class="text-right" style="width:90px;">Costo</th><th style="width:40px;"></th></tr></thead>
					<tbody></tbody>
					<tfoot>
						<tr><td colspan="3" class="text-right"><strong>Costo por lote</strong></td><td class="text-right"><strong id="batch-cost">$ 0.00</strong></td><td></td></tr>
						<tr><td colspan="3" class="text-right"><strong>Costo por unidad base</strong></td><td class="text-right"><strong id="unit-cost">—</strong></td><td></td></tr>
					</tfoot>
				</table></div>
				<p id="comp-warn" class="text-warning" style="display:none;"><i class="fa fa-exclamation-triangle"></i> Algún componente no tiene costo: el costo es parcial.</p>
				<button type="button" class="btn btn-default btn-sm" id="add-comp"><i class="fa fa-plus"></i> Agregar componente</button>
				<input type="hidden" name="components" id="components" value="[]">
			</div>
		</fieldset>
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<button class="btn btn-primary" id="btn-submit">Guardar</button>
		<a href="<?php echo base_url('ingredients'); ?>" class="btn btn-default">Cancelar</a>
	</form>
</div>

<script>
	var RAW = <?php echo json_encode($raw_json); ?>, COMP = <?php echo json_encode($comp_json); ?>;
	var COSTS = <?php echo json_encode($raw_costs); ?>;
	function esc(s){ return $('<div>').text(s).html(); }
	function rawById(id){ for( var i = 0; i < RAW.length; i++ ){ if( RAW[i].id == id ){ return RAW[i]; } } return null; }
	function addComp(id, qty){
		var opts = '<option value="">— elegir —</option>';
		RAW.forEach(function(i){ opts += '<option value="' + i.id + '"' + (i.id == id ? ' selected' : '') + '>' + esc(i.name) + '</option>'; });
		$('#comp-table tbody').append('<tr><td><select class="form-control c-ing" style="min-width:150px;">' + opts + '</select></td><td><input type="text" inputmode="decimal" class="form-control c-qty" value="' + (qty || '') + '"></td><td class="c-unit"></td><td class="text-right c-cost"></td><td><button type="button" class="btn btn-danger btn-xs c-del">&times;</button></td></tr>');
		recalcComp();
	}
	function recalcComp(){
		var total = 0, missing = false, list = [];
		$('#comp-table tbody tr').each(function(){
			var tr = $(this), id = tr.find('.c-ing').val(), ing = rawById(id), q = parseFloat(String(tr.find('.c-qty').val()).replace(',', '.')) || 0;
			tr.find('.c-unit').text(ing ? ing.unit : '');
			if( ing ){
				var cost = COSTS[id];
				if( cost === null || cost === undefined ){ missing = true; tr.find('.c-cost').text('—'); } else { total += cost * q; tr.find('.c-cost').text('$ ' + (cost * q).toFixed(4)); }
				list.push({ id_ingredient: id, quantity: tr.find('.c-qty').val() });
			} else { tr.find('.c-cost').text(''); }
		});
		$('#components').val(JSON.stringify(list));
		$('#batch-cost').text('$ ' + total.toFixed(2));
		var y = parseFloat(String($('#batch_yield').val()).replace(',', '.')) || 0;
		$('#unit-cost').text(y > 0 && total > 0 ? '$ ' + (total / y).toFixed(6).replace(/0+$/, '') : '—');
		$('#comp-warn').toggle(missing);
	}
	$('#prepared').on('change', function(){ $('#prep-box').toggle(this.checked); }).trigger('change');
	$('#add-comp').on('click', function(){ addComp('', ''); });
	$('#comp-table').on('change input', '.c-ing, .c-qty', recalcComp).on('click', '.c-del', function(){ $(this).closest('tr').remove(); recalcComp(); });
	$('#batch_yield').on('input', recalcComp);
	COMP.forEach(function(c){ addComp(c.id, c.qty); });

	$('#btn-submit').on('click', function(e){
		e.preventDefault();
		if( $('#prepared').is(':checked') ){ recalcComp(); }
		var btn = $(this); btn.prop('disabled', true);
		$.ajax({
			url: '<?php echo base_url('ingredients/save'.($is_edit ? '/'.$ingredient->id_ingredient : '')); ?>', type: 'post', data: $('#form').serializeArray(), dataType: 'json',
			success: function(r){
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3500 });
				if( r.tk ){ $('input[name="tk"]').val(r.tk); }
				if( r.type === 'success' ){ setTimeout(function(){ window.location = r.redirect; }, 700); } else { btn.prop('disabled', false); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Ha ocurrido un error guardando los datos', type: 'error', styling: 'bootstrap3' }); btn.prop('disabled', false); }
		});
	});
</script>
