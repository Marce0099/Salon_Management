<?php
$is_edit = isset($ingredient);
$num = function($x){ return $x === NULL ? '' : rtrim(rtrim(number_format((float) $x, 6, '.', ''), '0'), '.'); };
$ingredient = isset($ingredient) ? $ingredient : NULL;
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
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<button class="btn btn-primary" id="btn-submit">Guardar</button>
		<a href="<?php echo base_url('ingredients'); ?>" class="btn btn-default">Cancelar</a>
	</form>
</div>

<script>
	$('#btn-submit').on('click', function(e){
		e.preventDefault();
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
