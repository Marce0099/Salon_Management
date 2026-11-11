<?php
$price = (float) $product->price_sell;
$n = function($x){ return rtrim(rtrim(number_format((float) $x, 3, '.', ''), '0'), '.'); };
$ing_json = array();
foreach( $ingredients as $i ){ $ing_json[] = array('id' => (int) $i->id_ingredient, 'name' => $i->name, 'unit' => $i->unit, 'cost' => $i->eff_cost !== NULL ? (float) $i->eff_cost : NULL); }
$rec_json = array();
foreach( $recipe as $r ){ $rec_json[] = array('id' => (int) $r->id_ingredient, 'qty' => $n($r->quantity)); }
?>
<h3 class="section-title">Receta: <?php echo htmlspecialchars($product->name); ?></h3>
<p>Precio de venta: <strong>$ <?php echo number_format($price, 2); ?></strong> · <a href="<?php echo base_url('services'); ?>">Volver a platillos</a></p>

<?php if( empty($ingredients) ) : ?>
<p class="alert alert-info">Primero registre ingredientes en <a href="<?php echo base_url('ingredients/register'); ?>">Ingredientes</a>.</p>
<?php else : ?>
<p class="text-muted">Cantidad de cada ingrediente que consume <strong>una porción</strong>. Al enviar el platillo a cocina se descuenta del inventario; si no alcanza, el platillo aparece "Agotado".</p>

<div class="table-responsive">
<table class="table table-striped" id="recipe-table" style="max-width:720px;">
	<thead><tr><th>Ingrediente</th><th style="width:160px;">Cantidad por porción</th><th style="width:60px;">Unid.</th><th class="text-right" style="width:100px;">Costo</th><th style="width:50px;"></th></tr></thead>
	<tbody></tbody>
	<tfoot>
		<tr><td colspan="3" class="text-right"><strong>Costo por porción</strong></td><td class="text-right"><strong id="total-cost">$ 0.00</strong></td><td></td></tr>
		<tr><td colspan="3" class="text-right"><strong>Margen</strong></td><td class="text-right"><strong id="margin">—</strong></td><td></td></tr>
	</tfoot>
</table>
</div>
<p id="cost-warn" class="text-warning" style="display:none;"><i class="fa fa-exclamation-triangle"></i> Algún ingrediente no tiene costo definido: el costo mostrado es parcial.</p>

<button type="button" class="btn btn-default" id="add-row"><i class="fa fa-plus"></i> Agregar ingrediente</button>
<button type="button" class="btn btn-primary" id="save-recipe">Guardar receta</button>

<input type="hidden" id="rc-tk" value="<?php echo $tk; ?>">
<script>
(function(){
	var ING = <?php echo json_encode($ing_json); ?>, REC = <?php echo json_encode($rec_json); ?>, PRICE = <?php echo json_encode($price); ?>;
	function byId(id){ for( var i = 0; i < ING.length; i++ ){ if( ING[i].id == id ){ return ING[i]; } } return null; }
	function notify(type, text){ new PNotify({ title: 'Notificación', text: text, type: type, styling: 'bootstrap3', delay: 3500 }); }
	function esc(s){ return $('<div>').text(s).html(); }

	function addRow(id, qty){
		var opts = '<option value="">— elegir —</option>';
		ING.forEach(function(i){ opts += '<option value="' + i.id + '"' + (i.id == id ? ' selected' : '') + '>' + esc(i.name) + '</option>'; });
		var tr = $('<tr><td><select class="form-control r-ing">' + opts + '</select></td><td><input type="text" inputmode="decimal" class="form-control r-qty" value="' + (qty || '') + '"></td><td class="r-unit"></td><td class="text-right r-cost"></td><td><button type="button" class="btn btn-danger btn-xs r-del">&times;</button></td></tr>');
		$('#recipe-table tbody').append(tr);
		recalc();
	}
	function recalc(){
		var total = 0, missing = false;
		$('#recipe-table tbody tr').each(function(){
			var tr = $(this), ing = byId(tr.find('.r-ing').val()), q = parseFloat(String(tr.find('.r-qty').val()).replace(',', '.')) || 0;
			tr.find('.r-unit').text(ing ? ing.unit : '');
			if( ing && ing.cost === null ){ missing = true; tr.find('.r-cost').text('—'); }
			else if( ing ){ var c = ing.cost * q; total += c; tr.find('.r-cost').text('$ ' + c.toFixed(4).replace(/0+$/, '').replace(/\.$/, '.00').replace(/(\.\d)$/, '$10')); }
			else { tr.find('.r-cost').text(''); }
		});
		$('#total-cost').text('$ ' + total.toFixed(2));
		$('#cost-warn').toggle(missing);
		$('#margin').text(PRICE > 0 && total > 0 ? ((PRICE - total) / PRICE * 100).toFixed(1) + ' %  ($ ' + (PRICE - total).toFixed(2) + ')' : '—');
	}
	$('#add-row').on('click', function(){ addRow('', ''); });
	$('#recipe-table').on('change keyup', '.r-ing, .r-qty', recalc).on('click', '.r-del', function(){ $(this).closest('tr').remove(); recalc(); });
	REC.forEach(function(r){ addRow(r.id, r.qty); });
	if( !REC.length ){ addRow('', ''); }

	$('#save-recipe').on('click', function(){
		var btn = $(this), items = [], bad = false;
		$('#recipe-table tbody tr').each(function(){
			var id = $(this).find('.r-ing').val(), q = $(this).find('.r-qty').val();
			if( !id && !String(q).trim() ){ return; }
			if( !id || !String(q).trim() ){ bad = true; return; }
			items.push({ id_ingredient: id, quantity: q });
		});
		if( bad ){ notify('error', 'Complete ingrediente y cantidad en cada fila'); return; }
		btn.prop('disabled', true);
		$.ajax({ url: '<?php echo base_url('ingredients/save_recipe'); ?>', type: 'post', dataType: 'json',
			data: { tk: $('#rc-tk').val(), id_product: <?php echo (int) $product->id_product; ?>, items: JSON.stringify(items) },
			success: function(r){ if( r.tk ){ $('#rc-tk').val(r.tk); } notify(r.type, r.message); btn.prop('disabled', false); },
			error: function(){ notify('error', 'Ha ocurrido un error guardando los datos'); btn.prop('disabled', false); }
		});
	});
})();
</script>
<?php endif; ?>
