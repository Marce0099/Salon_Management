<h3 class="section-title">Impresoras
	<a href="<?php echo base_url('printers/register'); ?>" class="btn btn-success btn-sm pull-right">Nueva impresora</a>
	<a href="<?php echo base_url('printers/queue'); ?>" class="btn btn-default btn-sm pull-right" style="margin-right:6px;">Cola de impresión<?php if( $counts['failed'] ) : ?> <span class="label label-danger"><?php echo (int) $counts['failed']; ?></span><?php endif; ?></a>
</h3>
<p class="text-muted" style="font-size:13px;">Cada impresora recibe los tickets de su tipo: <b>Cocina</b> (comandas) o <b>Recibos</b> (factura al cliente). Una impresora de red (Ethernet/Wi-Fi) solo necesita su dirección IP; una USB en el equipo del servidor debe estar <b>compartida</b> en Windows (propiedades de la impresora &gt; Compartir) y se conecta por su nombre de recurso compartido.</p>

<?php if( !empty($receipt_printers) ) : ?>
<div class="well well-sm" style="margin-top:10px;">
	<b>Esta computadora imprime los recibos en:</b>
	<select id="station" class="form-control input-sm" style="display:inline-block; width:auto; margin:0 6px;">
		<option value="">Predeterminada (<?php echo htmlspecialchars($receipt_printers[0]->name); ?>)</option>
		<?php foreach( $receipt_printers as $rp ) : ?>
		<option value="<?php echo (int) $rp->id_printer; ?>" <?php echo $station === (int) $rp->id_printer ? 'selected' : ''; ?>><?php echo htmlspecialchars($rp->name); ?></option>
		<?php endforeach; ?>
	</select>
	<small class="text-muted">Se recuerda en este navegador: cada caja elige su propia impresora de recibos.</small>
</div>
<?php endif; ?>

<label style="font-weight:normal;"><input type="checkbox" onclick="location.href='<?php echo base_url('printers'); ?>' + (this.checked ? '?inactive=1' : '')" <?php echo $show_inactive ? 'checked' : ''; ?>> Ver desactivadas</label>

<?php if( empty($printers) ) : ?>
<p class="alert alert-info" style="margin-top:10px;">No hay impresoras <?php echo $show_inactive ? 'desactivadas' : 'registradas'; ?>. Sin una impresora de tipo Cocina o Recibos, los tickets quedan en la cola como "No impreso".</p>
<?php else : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Nombre</th><th>Se usa para</th><th>Conexión</th><th>Último trabajo</th><th>Acciones</th></tr></thead>
	<tbody>
	<?php foreach( $printers as $p ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($p->name); ?></td>
			<td><?php echo $p->role === 'kitchen' ? 'Cocina (comandas)' : 'Recibos (factura)'; ?>
				<?php if( $p->role === 'kitchen' ) : ?><br><small class="text-muted"><?php echo $p->categories ? htmlspecialchars($p->categories) : ''; ?><?php echo (int) $p->id_printer === $default_kitchen ? ($p->categories ? ' + ' : '').'el resto (predeterminada)' : ''; ?></small><?php endif; ?></td>
			<td><?php if( $p->conn_type === 'network' ) : ?>Red: <?php echo htmlspecialchars($p->address).':'.(int) $p->port; ?>
				<?php elseif( $p->conn_type === 'agent' ) : ?>Estación <b><?php echo htmlspecialchars((string) $p->agent_name); ?></b> <?php echo (int) $p->agent_online ? '<span class="label label-success">en línea</span>' : '<span class="label label-warning">desconectada</span>'; ?><br><small class="text-muted"><?php echo htmlspecialchars($p->address); ?></small>
				<?php else : ?>Windows: <?php echo htmlspecialchars($p->address); ?><?php endif; ?></td>
			<td>
				<?php if( $p->last_status === 'printed' ) : ?><span class="label label-success">Impreso</span>
				<?php elseif( $p->last_status === 'failed' ) : ?><span class="label label-danger">Falló</span>
				<?php elseif( $p->last_status === 'pending' ) : ?><span class="label label-warning">En espera</span>
				<?php elseif( $p->last_status === 'printing' ) : ?><span class="label label-default">Imprimiendo</span>
				<?php elseif( $p->last_status === 'cancelled' ) : ?><span class="label label-default">Cancelado</span>
				<?php else : ?><span class="text-muted">—</span><?php endif; ?>
				<?php if( (int) $p->n_failed ) : ?> <a href="<?php echo base_url('printers/queue?status=failed'); ?>"><small><?php echo (int) $p->n_failed; ?> sin imprimir</small></a><?php endif; ?>
			</td>
			<td>
				<?php if( $p->active ) : ?>
				<button type="button" class="btn btn-primary btn-xs js-test" data-id="<?php echo (int) $p->id_printer; ?>"><i class="fa fa-print"></i> Probar</button>
				<a href="<?php echo base_url('printers/edit/'.$p->id_printer); ?>" class="btn btn-default btn-xs">Editar</a>
				<a href="<?php echo base_url('printers/delete/'.$p->id_printer); ?>" class="btn btn-danger btn-xs confirm-action" data-msg="¿Desactivar esta impresora? Los tickets de su tipo dejarán de imprimirse hasta que haya otra activa.">Desactivar</a>
				<?php else : ?>
				<a href="<?php echo base_url('printers/restore/'.$p->id_printer); ?>" class="btn btn-default btn-xs">Reactivar</a>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<h4 class="section-title" style="margin-top:30px;">Estaciones de impresión <small>(impresoras USB conectadas a otra PC)</small></h4>
<p class="text-muted" style="font-size:13px;">Una impresora USB solo la ve la PC donde está conectada. En esa PC se instala el programa <b>PosPrintAgent</b> (carpeta <code>agent/</code>): consulta este servidor cada pocos segundos y imprime lo que le toca, sin compartir impresoras ni abrir puertos. Cree una estación por cada PC y luego registre la impresora con conexión "Estación".</p>

<?php if( !empty($agents) ) : ?>
<div class="table-responsive">
<table class="table table-striped jambo_table">
	<thead><tr><th>Estación</th><th>Estado</th><th>Impresoras</th><th>Última conexión</th><th>Acciones</th></tr></thead>
	<tbody>
	<?php foreach( $agents as $a ) : ?>
		<tr>
			<td><?php echo htmlspecialchars($a->name); ?></td>
			<td><?php if( !$a->active ) : ?><span class="label label-default">Desactivada</span><?php elseif( (int) $a->online ) : ?><span class="label label-success">En línea</span><?php else : ?><span class="label label-warning">Desconectada</span><?php endif; ?></td>
			<td><?php echo (int) $a->n_printers; ?></td>
			<td><?php echo $a->last_seen_at ? date('d/m/Y h:i:s A', strtotime($a->last_seen_at)).' <small class="text-muted">'.htmlspecialchars((string) $a->last_ip).($a->version ? ' · v'.htmlspecialchars($a->version) : '').'</small>' : '<span class="text-muted">nunca</span>'; ?></td>
			<td>
				<?php if( $a->active ) : ?><button type="button" class="btn btn-default btn-xs js-agent" data-act="agent_rotate" data-id="<?php echo (int) $a->id_agent; ?>" data-confirm="¿Generar un token nuevo? El actual deja de funcionar y tendrá que ponerlo en la PC de la estación.">Nuevo token</button><?php endif; ?>
				<button type="button" class="btn btn-<?php echo $a->active ? 'danger' : 'default'; ?> btn-xs js-agent" data-act="agent_toggle" data-id="<?php echo (int) $a->id_agent; ?>"><?php echo $a->active ? 'Desactivar' : 'Reactivar'; ?></button>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div>
<?php endif; ?>

<div class="form-inline" style="margin-bottom:10px;">
	<input type="text" id="agent_name" maxlength="60" class="form-control" placeholder="Nombre de la estación (ej. Cocina)" style="width:260px;">
	<button type="button" class="btn btn-success" id="btn-agent-create">Nueva estación</button>
</div>

<div id="agent-token-box" class="alert alert-warning" style="display:none;">
	<b>Token de la estación <span id="agent-token-name"></span></b> (se muestra una sola vez):
	<pre id="agent-token" style="margin:8px 0; user-select:all;"></pre>
	En la PC de la estación, abra PowerShell en la carpeta del agente y ejecute (use la IP de este servidor, no "localhost"):
	<pre id="agent-cmd" style="margin:8px 0; user-select:all;"></pre>
	<small>Para que arranque solo al iniciar sesión, agregue <code>-Install</code> al comando (no requiere permisos de administrador).</small>
</div>

<input type="hidden" id="tk" value="<?php echo $tk; ?>">
<script>
	function agentPost(act, data, done){
		data.tk = $('#tk').val();
		$.post('<?php echo base_url('printers'); ?>/' + act, data, function(r){
			if( r.tk ){ $('#tk').val(r.tk); }
			new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 5000 });
			done(r);
		}, 'json').fail(function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); });
	}
	function showToken(r){
		$('#agent-token-name').text(r.name);
		$('#agent-token').text(r.token);
		$('#agent-cmd').text('powershell -ExecutionPolicy Bypass -File .\\PosPrintAgent.ps1 -Server ' + r.server + ' -Token ' + r.token);
		$('#agent-token-box').show()[0].scrollIntoView();
	}
	$('#btn-agent-create').on('click', function(){
		agentPost('agent_create', { name: $('#agent_name').val() }, function(r){ if( r.type === 'success' ){ showToken(r); $('#agent_name').val(''); } });
	});
	$('.js-agent').on('click', function(){
		var b = $(this), act = b.data('act');
		if( b.data('confirm') && !confirm(b.data('confirm')) ){ return; }
		agentPost(act, { id_agent: b.data('id') }, function(r){
			if( r.type !== 'success' ){ return; }
			if( r.token ){ showToken(r); } else { setTimeout(function(){ location.reload(); }, 900); }
		});
	});
</script>
<script>
	$('#station').on('change', function(){
		$.post('<?php echo base_url('printers/set_station'); ?>', { tk: $('#tk').val(), id_printer: $(this).val() }, function(r){
			new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3500 });
		}, 'json').fail(function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); });
	});
	$('.js-test').on('click', function(){
		var b = $(this).prop('disabled', true);
		$.post('<?php echo base_url('printers/test'); ?>', { tk: $('#tk').val(), id_printer: b.data('id') }, function(r){
			new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 5000 });
			setTimeout(function(){ location.reload(); }, 1800);
		}, 'json').fail(function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); });
	});
</script>
