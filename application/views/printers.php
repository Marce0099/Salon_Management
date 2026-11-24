<h3 class="section-title">Impresoras
	<a href="<?php echo base_url('printers/register'); ?>" class="btn btn-success btn-sm pull-right">Nueva impresora</a>
	<a href="<?php echo base_url('printers/queue'); ?>" class="btn btn-default btn-sm pull-right" style="margin-right:6px;">Cola de impresión<?php if( $counts['failed'] ) : ?> <span class="label label-danger"><?php echo (int) $counts['failed']; ?></span><?php endif; ?></a>
</h3>
<p class="text-muted" style="font-size:13px;">Cada impresora recibe los tickets de su tipo: <b>Cocina</b> (comandas) o <b>Recibos</b> (factura al cliente). Una impresora de red (Ethernet/Wi-Fi) solo necesita su dirección IP; una USB se conecta por el nombre con que aparece en Windows en el equipo del servidor.</p>

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
			<td><?php echo $p->role === 'kitchen' ? 'Cocina (comandas)' : 'Recibos (factura)'; ?></td>
			<td><?php echo $p->conn_type === 'network' ? 'Red: '.htmlspecialchars($p->address).':'.(int) $p->port : 'Windows: '.htmlspecialchars($p->address); ?></td>
			<td>
				<?php if( $p->last_status === 'printed' ) : ?><span class="label label-success">Impreso</span>
				<?php elseif( $p->last_status === 'failed' ) : ?><span class="label label-danger">Falló</span>
				<?php elseif( $p->last_status ) : ?><span class="label label-default"><?php echo htmlspecialchars($p->last_status); ?></span>
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
<input type="hidden" id="tk" value="<?php echo $tk; ?>">
<script>
	$('.js-test').on('click', function(){
		var b = $(this).prop('disabled', true);
		$.post('<?php echo base_url('printers/test'); ?>', { tk: $('#tk').val(), id_printer: b.data('id') }, function(r){
			new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 5000 });
			setTimeout(function(){ location.reload(); }, 1800);
		}, 'json').fail(function(){ new PNotify({ title: 'Notificación', text: 'Error de conexión', type: 'error', styling: 'bootstrap3' }); b.prop('disabled', false); });
	});
</script>
