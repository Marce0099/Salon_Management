<?php
	$e = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
	$qs = function($extra = array()) use ($f){ return http_build_query(array_filter(array_merge($f, $extra), function($v){ return $v !== '' && $v !== 0 && $v !== NULL; })); };
	$badge = array('ok' => 'success', 'denied' => 'warning', 'error' => 'danger');
	$result_txt = array('ok' => 'Correcto', 'denied' => 'Rechazado', 'error' => 'Error');
	$pretty = function($json){ if( $json === NULL || $json === '' ){ return NULL; } $d = json_decode($json, TRUE); return $d === NULL ? $json : json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
?>
<style>
	.audit-row{ cursor:pointer; }
	.audit-row:hover td{ background:#f5f7fa; }
	.audit-detail td{ background:#fafafa; }
	.audit-detail pre{ max-height:260px; overflow:auto; white-space:pre-wrap; word-break:break-word; font-size:12px; margin:0; }
	.audit-meta{ font-size:12px; color:#666; }
	.audit-meta span{ margin-right:14px; white-space:nowrap; }
	.audit-summary{ min-width:220px; }
</style>

<h3 class="section-title"><?php echo $e($title); ?></h3>
<?php if( $timeline ) : ?>
<p class="text-muted">Todos los movimientos registrados alrededor de esta cuenta, del más antiguo al más reciente. <a href="<?php echo base_url('audit'); ?>">Volver a la auditoría</a></p>
<?php else : ?>
<p class="text-muted">Quién hizo qué, cuándo y desde qué dispositivo. Los registros no se pueden editar ni borrar desde la aplicación. Horas en hora local.</p>

<div class="panel panel-default">
	<div class="panel-heading">Filtros</div>
	<div class="panel-body">
		<form action="<?php echo base_url('audit'); ?>" method="get" autocomplete="off">
			<div class="row">
				<div class="col-md-3 form-group">
					<label for="a-user">Usuario</label>
					<select name="id_admin" id="a-user" class="form-control">
						<option value="">Todos</option>
						<?php foreach( $actors as $a ) : ?>
						<option value="<?php echo (int)$a->id_admin; ?>" <?php if( (int)$a->id_admin === $f['id_admin'] ) echo 'selected'; ?>><?php echo $e($a->admin_name); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3 form-group">
					<label for="a-action">Acción</label>
					<input list="a-actions" name="action" id="a-action" class="form-control" placeholder="ej. bill. o order_item.void" value="<?php echo $e($f['action']); ?>">
					<datalist id="a-actions"><?php foreach( $actions as $ac ) : ?><option value="<?php echo $e($ac); ?>"><?php endforeach; ?></datalist>
				</div>
				<div class="col-md-2 form-group">
					<label for="a-result">Resultado</label>
					<select name="result" id="a-result" class="form-control">
						<option value="">Todos</option>
						<?php foreach( $result_txt as $k => $t ) : ?>
						<option value="<?php echo $k; ?>" <?php if( $f['result'] === $k ) echo 'selected'; ?>><?php echo $t; ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-2 form-group">
					<label for="a-from">Desde</label>
					<input type="date" name="from" id="a-from" class="form-control" value="<?php echo $e($f['from']); ?>">
				</div>
				<div class="col-md-2 form-group">
					<label for="a-to">Hasta</label>
					<input type="date" name="to" id="a-to" class="form-control" value="<?php echo $e($f['to']); ?>">
				</div>
			</div>
			<div class="row">
				<div class="col-md-3 form-group"><label for="a-q">Texto del resumen</label><input type="text" name="q" id="a-q" class="form-control" value="<?php echo $e($f['q']); ?>"></div>
				<div class="col-md-2 form-group"><label for="a-tab">Mesa (cuenta #)</label><input type="number" min="1" name="id_tab" id="a-tab" class="form-control" value="<?php echo $f['id_tab'] ?: ''; ?>"></div>
				<div class="col-md-2 form-group"><label for="a-bill">Cuenta #</label><input type="number" min="1" name="id_bill" id="a-bill" class="form-control" value="<?php echo $f['id_bill'] ?: ''; ?>"></div>
				<div class="col-md-2 form-group"><label for="a-sale">Venta #</label><input type="number" min="1" name="id_sale" id="a-sale" class="form-control" value="<?php echo $f['id_sale'] ?: ''; ?>"></div>
				<div class="col-md-3 form-group"><label for="a-req">ID de solicitud</label><input type="text" name="request_id" id="a-req" class="form-control" value="<?php echo $e($f['request_id']); ?>"></div>
			</div>
			<button type="submit" class="btn btn-primary">Buscar</button>
			<a href="<?php echo base_url('audit'); ?>" class="btn btn-default">Reiniciar</a>
			<a href="<?php echo base_url('audit/export').'?'.$qs(); ?>" class="btn btn-default"><i class="fa fa-download"></i> Exportar CSV</a>
		</form>
	</div>
</div>
<?php endif; ?>

<div class="table-responsive">
	<table class="table table-bordered table-condensed" id="audit-table">
		<thead>
			<tr><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Resumen</th><th>Resultado</th><th>Dispositivo</th></tr>
		</thead>
		<tbody>
		<?php if( !$rows ) : ?>
			<tr><td colspan="6" class="text-center text-muted">No hay registros para estos filtros.</td></tr>
		<?php endif; ?>
		<?php foreach( $rows as $r ) :
			$before = $pretty($r->before_json); $after = $pretty($r->after_json); ?>
			<tr class="audit-row" data-id="<?php echo (int)$r->id; ?>">
				<td class="text-nowrap"><?php echo $e(Audit_mdl::fmt($r->occurred_at)); ?></td>
				<td><?php echo $r->admin_name !== NULL ? $e($r->admin_name).' <small class="text-muted">('.$e($r->role).')</small>' : '<span class="text-muted">—</span>'; ?></td>
				<td><code><?php echo $e($r->action); ?></code></td>
				<td class="audit-summary"><?php echo $e($r->summary); ?></td>
				<td><span class="label label-<?php echo $badge[$r->result]; ?>"><?php echo $result_txt[$r->result]; ?></span></td>
				<td class="text-nowrap"><?php echo $e($r->terminal_id !== NULL ? $r->terminal_id : $r->ip); ?></td>
			</tr>
			<tr class="audit-detail" id="audit-detail-<?php echo (int)$r->id; ?>" style="display:none">
				<td colspan="6">
					<div class="audit-meta">
						<span><b>#</b> <?php echo (int)$r->id; ?></span>
						<span><b>Entidad:</b> <?php echo $e($r->entity_type); ?> <?php echo $e($r->entity_id); ?></span>
						<?php if( $r->id_tab ) : ?><span><b>Mesa:</b> <a href="<?php echo base_url('audit/timeline/tab/'.(int)$r->id_tab); ?>">#<?php echo (int)$r->id_tab; ?> (línea de tiempo)</a></span><?php endif; ?>
						<?php if( $r->id_bill ) : ?><span><b>Cuenta:</b> <a href="<?php echo base_url('audit/timeline/bill/'.(int)$r->id_bill); ?>">#<?php echo (int)$r->id_bill; ?></a></span><?php endif; ?>
						<?php if( $r->id_sale ) : ?><span><b>Venta:</b> <a href="<?php echo base_url('audit/timeline/sale/'.(int)$r->id_sale); ?>">#<?php echo (int)$r->id_sale; ?></a></span><?php endif; ?>
						<span><b>Solicitud:</b> <a href="<?php echo base_url('audit').'?request_id='.urlencode($r->request_id); ?>"><?php echo $e($r->request_id); ?></a></span>
						<span><b>Pantalla:</b> <?php echo $e($r->request); ?></span>
						<span><b>IP:</b> <?php echo $e($r->ip); ?></span>
						<span title="<?php echo $e($r->user_agent); ?>"><b>Navegador:</b> <?php echo $e(mb_substr((string)$r->user_agent, 0, 60, 'UTF-8')); ?></span>
					</div>
					<?php if( $before !== NULL || $after !== NULL ) : ?>
					<div class="row" style="margin-top:8px">
						<?php if( $before !== NULL ) : ?><div class="col-sm-6"><b>Antes</b><pre><?php echo $e($before); ?></pre></div><?php endif; ?>
						<?php if( $after !== NULL ) : ?><div class="col-sm-6"><b>Después</b><pre><?php echo $e($after); ?></pre></div><?php endif; ?>
					</div>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php if( !$timeline && $next ) : ?>
<p><a class="btn btn-default" href="<?php echo base_url('audit').'?'.$qs(array('after' => $next)); ?>">Ver registros anteriores &raquo;</a></p>
<?php endif; ?>

<script>
	document.getElementById('audit-table').addEventListener('click', function(ev){
		if( ev.target.closest('a') ){ return; }
		var tr = ev.target.closest('tr.audit-row');
		if( !tr ){ return; }
		var d = document.getElementById('audit-detail-' + tr.getAttribute('data-id'));
		if( d ){ d.style.display = d.style.display === 'none' ? '' : 'none'; }
	});
</script>
