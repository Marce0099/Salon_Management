<?php $is_edit = isset($storage); ?>
<h4 class="section-title"><?php echo $is_edit ? 'Editar almacén' : 'Nuevo almacén'; ?></h4>
<div class="row">
<div class="col-md-5 col-xs-12">
	<form id="form" autocomplete="off" onsubmit="return false;" spellcheck="false">
		<div class="form-group"><label>Nombre</label><input type="text" name="name" maxlength="60" class="form-control" placeholder="Bodega A" value="<?php echo $is_edit ? htmlspecialchars($storage->name) : ''; ?>"></div>
		<div class="form-group"><label>Descripción (opcional)</label><input type="text" name="description" maxlength="150" class="form-control" placeholder="Refrigerador de cervezas, detrás de la barra" value="<?php echo $is_edit ? htmlspecialchars((string) $storage->description) : ''; ?>"></div>
		<div class="form-group"><label>Orden (menor aparece primero)</label><input type="text" inputmode="numeric" name="sort_order" maxlength="4" class="form-control" value="<?php echo $is_edit ? (int) $storage->sort_order : '0'; ?>"></div>
		<input type="hidden" name="tk" id="tk" value="<?php echo $tk; ?>">
		<button class="btn btn-primary" id="btn-submit">Guardar</button>
		<a href="<?php echo base_url('storages'); ?>" class="btn btn-default">Cancelar</a>
	</form>
</div>
</div>
<script>
	$('#btn-submit').on('click', function(e){
		e.preventDefault();
		var b = $(this).prop('disabled', true);
		$.ajax({ url: '<?php echo base_url('storages/save/'.($is_edit ? (int) $storage->id_storage : 0)); ?>', type: 'post', dataType: 'json', data: $('#form').serialize(),
			success: function(r){
				if( r.tk ){ $('#tk').val(r.tk); }
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3000 });
				if( r.type === 'success' && r.redirect ){ setTimeout(function(){ location.href = r.redirect; }, 500); } else { b.prop('disabled', false); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); }
		});
	});
</script>
