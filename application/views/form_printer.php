<?php $is_edit = isset($printer); $type = $is_edit ? $printer->conn_type : 'network'; ?>
<h4 class="section-title"><?php echo $is_edit ? 'Editar impresora' : 'Nueva impresora'; ?></h4>
<div class="row">
<div class="col-md-5 col-xs-12">
	<form id="form" autocomplete="off" onsubmit="return false;" spellcheck="false">
		<div class="form-group"><label>Nombre</label><input type="text" name="name" maxlength="60" class="form-control" placeholder="Cocina principal" value="<?php echo $is_edit ? htmlspecialchars($printer->name) : ''; ?>"></div>
		<div class="form-group"><label>Se usa para</label>
			<select name="role" class="form-control">
				<option value="kitchen" <?php echo $is_edit && $printer->role === 'kitchen' ? 'selected' : ''; ?>>Cocina (comandas)</option>
				<option value="receipt" <?php echo $is_edit && $printer->role === 'receipt' ? 'selected' : ''; ?>>Recibos (factura al cliente)</option>
			</select>
		</div>
		<div class="form-group"><label>Conexión</label>
			<select name="conn_type" id="conn_type" class="form-control">
				<option value="network" <?php echo $type === 'network' ? 'selected' : ''; ?>>Red (Ethernet / Wi-Fi) — por IP</option>
				<option value="windows" <?php echo $type === 'windows' ? 'selected' : ''; ?>>Windows (USB u otra instalada en el servidor) — por nombre</option>
			</select>
		</div>
		<div class="form-group"><label id="lbl-address">Dirección IP</label><input type="text" name="address" maxlength="150" class="form-control" value="<?php echo $is_edit ? htmlspecialchars($printer->address) : ''; ?>">
			<small class="text-muted" id="hint-address"></small></div>
		<div class="form-group" id="grp-port"><label>Puerto</label><input type="text" inputmode="numeric" name="port" maxlength="5" class="form-control" value="<?php echo $is_edit ? (int) $printer->port : '9100'; ?>"><small class="text-muted">Casi todas las impresoras térmicas usan 9100.</small></div>
		<input type="hidden" name="tk" id="tk" value="<?php echo $tk; ?>">
		<button class="btn btn-primary" id="btn-submit">Guardar</button>
		<a href="<?php echo base_url('printers'); ?>" class="btn btn-default">Cancelar</a>
	</form>
</div>
</div>
<script>
	function syncType(){
		var net = $('#conn_type').val() === 'network';
		$('#grp-port').toggle(net);
		$('#lbl-address').text(net ? 'Dirección IP o nombre de red' : 'Nombre de la impresora en Windows');
		$('#hint-address').text(net ? 'Ej. 192.168.1.50 (imprima la hoja de configuración de la impresora para ver su IP).' : 'Tal como aparece en Windows (Impresoras y escáneres), ej. COCINA. Para una impresora de otro equipo: smb://equipo/impresora');
	}
	$('#conn_type').on('change', syncType); syncType();

	$('#btn-submit').on('click', function(e){
		e.preventDefault();
		var b = $(this).prop('disabled', true);
		$.ajax({ url: '<?php echo base_url('printers/save/'.($is_edit ? (int) $printer->id_printer : 0)); ?>', type: 'post', dataType: 'json', data: $('#form').serialize(),
			success: function(r){
				if( r.tk ){ $('#tk').val(r.tk); }
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3000 });
				if( r.type === 'success' && r.redirect ){ setTimeout(function(){ location.href = r.redirect; }, 500); } else { b.prop('disabled', false); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); }
		});
	});
</script>
