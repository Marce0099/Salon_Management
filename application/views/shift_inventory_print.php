<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Inventario - <?php echo htmlspecialchars($shift->label); ?></title>
<style>
	*{box-sizing:border-box;}
	body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:18px;background:#eee;font-size:13px;}
	.sheet{max-width:820px;margin:0 auto;background:#fff;padding:24px 28px;}
	.bar{max-width:820px;margin:0 auto 10px;text-align:right;}
	.bar button,.bar a{font-size:14px;padding:7px 14px;border:1px solid #888;background:#fff;border-radius:4px;cursor:pointer;text-decoration:none;color:#111;}
	h1{margin:0;font-size:22px;} .sub{color:#555;margin:2px 0 10px;}
	.meta{display:flex;flex-wrap:wrap;gap:6px 28px;border-top:2px solid #111;border-bottom:1px solid #999;padding:8px 0;margin-bottom:14px;}
	.meta div span{color:#666;display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;}
	.draft{display:inline-block;border:2px solid #c00;color:#c00;padding:2px 8px;font-weight:bold;margin-left:8px;font-size:12px;}
	section{margin-bottom:18px;page-break-inside:avoid;}
	section h2{font-size:16px;margin:0 0 4px;padding:4px 8px;background:#222;color:#fff;display:flex;justify-content:space-between;}
	table{width:100%;border-collapse:collapse;} th,td{border-bottom:1px solid #ccc;padding:4px 8px;text-align:left;}
	th{font-size:11px;text-transform:uppercase;color:#555;border-bottom:2px solid #555;} .r{text-align:right;} tfoot td{font-weight:bold;border-top:2px solid #555;border-bottom:0;}
	.note{font-size:11px;color:#666;} .empty{font-style:italic;color:#555;padding:6px 8px;}
	.who{font-size:11px;color:#555;margin:2px 0 6px;}
	.sign{display:flex;flex-wrap:wrap;gap:30px;margin-top:34px;} .sign div{flex:1 1 200px;border-top:1px solid #111;padding-top:4px;font-size:12px;}
	.totals{page-break-before:auto;}
	@media print{ body{background:#fff;padding:0;} .sheet{padding:0;max-width:none;} .bar{display:none;} @page{size:A4;margin:14mm;} section{break-inside:avoid;} }
</style>
</head>
<body>
<div class="bar"><a href="javascript:history.back()">Volver</a> <button onclick="window.print()">Imprimir</button></div>
<div class="sheet">
	<h1><?php echo htmlspecialchars((string) $company); ?></h1>
	<div class="sub">Inventario de turno <?php if( $shift->status !== 'done' ) : ?><span class="draft"><?php echo $shift->status === 'void' ? 'ANULADO' : 'BORRADOR - EN CURSO'; ?></span><?php endif; ?></div>
	<div class="meta">
		<div><span>Turno</span><?php echo htmlspecialchars($shift->label); ?> (#<?php echo (int) $shift->id_shift; ?>)</div>
		<div><span>Abierto</span><?php echo date('d/m/Y h:i A', strtotime($shift->opened_at)); ?> · <?php echo htmlspecialchars((string) $shift->opened_by_name); ?></div>
		<div><span>Cerrado</span><?php echo $shift->closed_at ? date('d/m/Y h:i A', strtotime($shift->closed_at)).' · '.htmlspecialchars((string) $shift->closed_by_name) : '—'; ?></div>
		<div><span>Impreso</span><?php echo date('d/m/Y h:i A'); ?></div>
	</div>

	<?php $grand = 0; foreach( $storages as $s ) : $ls = isset($lines[(int) $s->id_shift_storage]) ? $lines[(int) $s->id_shift_storage] : array(); $grand += (int) $s->total_units; ?>
	<section>
		<h2><span><?php echo htmlspecialchars($s->storage_name); ?></span><span><?php echo (int) $s->total_units; ?> u</span></h2>
		<div class="who"><?php echo $s->status === 'counted' ? 'Inventariado por '.htmlspecialchars((string) $s->counted_by_name).' · '.date('d/m/Y h:i A', strtotime($s->counted_at)) : 'SIN INVENTARIAR'; ?><?php echo $s->notes ? ' · Obs.: '.htmlspecialchars($s->notes) : ''; ?></div>
		<?php if( $ls ) : ?>
		<table>
			<thead><tr><th>Producto</th><th>Presentación</th><th class="r">Cant.</th><th class="r">Unidades</th></tr></thead>
			<tbody>
			<?php foreach( $ls as $l ) : ?>
				<tr><td><?php echo htmlspecialchars($l->product_name); ?><?php echo $l->note ? ' <span class="note">— '.htmlspecialchars($l->note).'</span>' : ''; ?></td>
				<td><?php echo $l->pack_name ? htmlspecialchars($l->pack_name).' (x'.(int) $l->pack_units.')' : 'Unidad'; ?></td>
				<td class="r"><?php echo (int) $l->qty; ?></td><td class="r"><?php echo (int) $l->base_units; ?></td></tr>
			<?php endforeach; ?>
			</tbody>
			<tfoot><tr><td colspan="3" class="r">Total almacén</td><td class="r"><?php echo (int) $s->total_units; ?></td></tr></tfoot>
		</table>
		<?php else : ?>
		<div class="empty"><?php echo $s->status === 'counted' ? 'Almacén vacío' : '—'; ?></div>
		<?php endif; ?>
	</section>
	<?php endforeach; ?>

	<?php if( !empty($totals) ) : ?>
	<section class="totals">
		<h2><span>Resumen por producto</span><span><?php echo $grand; ?> u</span></h2>
		<table>
			<thead><tr><th>Producto</th><th class="r">Contado</th><th class="r">Sistema</th><th class="r">Diferencia</th></tr></thead>
			<tbody>
			<?php foreach( $totals as $t ) : $diff = $t->system_stock === NULL ? NULL : (int) $t->counted - (int) $t->system_stock; ?>
				<tr><td><?php echo htmlspecialchars($t->product_name); ?></td><td class="r"><strong><?php echo (int) $t->counted; ?></strong></td>
				<td class="r"><?php echo $t->system_stock === NULL ? '—' : (int) $t->system_stock; ?></td>
				<td class="r"><?php echo $diff === NULL ? '—' : ($diff > 0 ? '+' : '').$diff; ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<div class="note" style="margin-top:4px;">"Sistema" = stock registrado al imprimir; solo referencia.</div>
	</section>
	<?php endif; ?>

	<div class="sign">
		<?php $names = array(); foreach( $storages as $s ){ if( $s->counted_by_name ){ $names[$s->counted_by_name] = TRUE; } } foreach( array_keys($names) as $nm ) : ?>
		<div><?php echo htmlspecialchars($nm); ?><br><span class="note">Contó</span></div>
		<?php endforeach; ?>
		<div><?php echo htmlspecialchars((string) $shift->closed_by_name); ?><br><span class="note">Cerró el turno</span></div>
	</div>
</div>
</body>
</html>
