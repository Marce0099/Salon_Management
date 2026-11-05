<div class="page-title" style="margin-bottom:10px;">
	<h3 style="display:inline-block;">Comanda #<?php echo (int) $order->id_order; ?></h3>
	<span class="pull-right">
		<a href="<?php echo base_url('kitchen/tab/'.(int) $order->id_tab); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> Mesa</a>
		<button type="button" class="btn btn-primary" onclick="window.print();"><i class="fa fa-print"></i> Imprimir</button>
	</span>
</div>
<div class="clearfix"></div>

<div style="max-width:420px; background:#fff; border:1px dashed #999; padding:14px; font-family:monospace;">
	<div style="text-align:center; font-size:22px; font-weight:bold;">COCINA</div>
	<div style="text-align:center;">Pedido #<?php echo (int) $order->id_order; ?></div>
	<hr>
	<div><strong>Mesa: <?php echo htmlspecialchars($tab->table_label); ?></strong> (<?php echo htmlspecialchars($tab->area_name); ?>)</div>
	<div>Mesero: <?php echo htmlspecialchars((string) $tab->waiter); ?></div>
	<div>Hora: <?php echo date('d/m/Y h:i A', strtotime($order->created_at)); ?></div>
	<hr>
	<?php $course = NULL; foreach( $items as $i ) : if( !$i->prints_kitchen ) { continue; } ?>
		<?php if( $course !== (int) $i->course ) : $course = (int) $i->course; ?><div style="font-weight:bold; margin-top:6px;">-- Tiempo <?php echo $course; ?> --</div><?php endif; ?>
		<div style="font-size:18px; <?php echo $i->status === 'void' ? 'text-decoration:line-through;' : ''; ?>"><?php echo (int) $i->quantity; ?> x <?php echo htmlspecialchars($i->product_name); ?></div>
		<?php if( $i->notes ) : ?><div style="padding-left:16px;">&gt;&gt; <?php echo htmlspecialchars($i->notes); ?></div><?php endif; ?>
	<?php endforeach; ?>
</div>
