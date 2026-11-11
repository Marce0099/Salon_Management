<?php
$is_edit = isset($modifier);
$n = function($x){ return $x === NULL ? '' : rtrim(rtrim(number_format((float) $x, 3, '.', ''), '0'), '.'); };
$ing_json = array();
foreach( $ingredients as $i ){ $ing_json[] = array('id' => (int) $i->id_ingredient, 'name' => $i->name, 'unit' => $i->unit); }
$rows_json = array();
foreach( $rows as $r ){ $rows_json[] = array('id' => (int) $r->id_ingredient, 'qty' => $n($r->quantity)); }
?>
<h4 class="section-title"><?php echo $is_edit ? 'Editar modificador' : 'Nuevo modificador'; ?></h4>

<div class="col-md-6">
	<form id="form" autocomplete="off" onsubmit="return false;" spellcheck="false">
		<div class="form-group"><label>Nombre (lo ve el mesero)</label><input type="text" name="name" maxlength="60" class="form-control" placeholder="Sin queso" value="<?php echo $is_edit ? htmlspecialchars($modifier->name) : ''; ?>"></div>
		<div class="form-group"><label>Texto en la comanda (opcional)</label><input type="text" name="kitchen_text" maxlength="60" class="form-control" placeholder="SIN QUESO" value="<?php echo $is_edit ? htmlspecialchars($modifier->kitchen_text) : ''; ?>"><small class="text-muted">Si lo deja vacío se usa el nombre en mayúsculas.</small></div>

		<label>Ingredientes que quita (opcional)</label>
		<p class="text-muted" style="font-size:12px;">Deje la cantidad vacía para quitar <strong>toda</strong> la cantidad que lleva el platillo; escriba una cantidad para quitar solo esa parte. Nunca se quita más de lo que lleva la receta, y si el platillo no usa ese ingrediente no pasa nada. Sin ingredientes = solo texto para cocina.</p>
		<div class="table-responsive"><table class="table table-condensed" id="mod-table" style="min-width:400px;">
			<thead><tr><th>Ingrediente</th><th style="width:140px;">Cantidad a quitar</th><th style="width:50px;">Unid.</th><th style="width:40px;"></th></tr></thead>
			<tbody></tbody>
		</table></div>
		<button type="button" class="btn btn-default btn-sm" id="add-ing"><i class="fa fa-plus"></i> Agregar ingrediente</button>
		<input type="hidden" name="items" id="items" value="[]">
		<input type="hidden" name="tk" value="<?php echo $tk; ?>">
		<div style="margin-top:15px;">
			<button class="btn btn-primary" id="btn-submit">Guardar</button>
			<a href="<?php echo base_url('modifiers'); ?>" class="btn btn-default">Cancelar</a>
		</div>
	</form>
</div>

<script>
	var ING = <?php echo json_encode($ing_json); ?>, ROWS = <?php echo json_encode($rows_json); ?>;
	function esc(s){ return $('<div>').text(s).html(); }
	function byId(id){ for( var i = 0; i < ING.length; i++ ){ if( ING[i].id == id ){ return ING[i]; } } return null; }
	function addRow(id, qty){
		var opts = '<option value="">— elegir —</option>';
		ING.forEach(function(i){ opts += '<option value="' + i.id + '"' + (i.id == id ? ' selected' : '') + '>' + esc(i.name) + '</option>'; });
		$('#mod-table tbody').append('<tr><td><select class="form-control m-ing" style="min-width:150px;">' + opts + '</select></td><td><input type="text" inputmode="decimal" class="form-control m-qty" placeholder="todo" value="' + (qty || '') + '"></td><td class="m-unit"></td><td><button type="button" class="btn btn-danger btn-xs m-del">&times;</button></td></tr>');
		sync();
	}
	function sync(){
		var list = [];
		$('#mod-table tbody tr').each(function(){
			var tr = $(this), id = tr.find('.m-ing').val(), ing = byId(id);
			tr.find('.m-unit').text(ing ? ing.unit : '');
			if( id ){ list.push({ id_ingredient: id, quantity: tr.find('.m-qty').val() }); }
		});
		$('#items').val(JSON.stringify(list));
	}
	$('#add-ing').on('click', function(){ addRow('', ''); });
	$('#mod-table').on('change input', '.m-ing, .m-qty', sync).on('click', '.m-del', function(){ $(this).closest('tr').remove(); sync(); });
	ROWS.forEach(function(r){ addRow(r.id, r.qty); });

	$('#btn-submit').on('click', function(e){
		e.preventDefault(); sync();
		var btn = $(this); btn.prop('disabled', true);
		$.ajax({
			url: '<?php echo base_url('modifiers/save'.($is_edit ? '/'.$modifier->id_modifier : '')); ?>', type: 'post', data: $('#form').serializeArray(), dataType: 'json',
			success: function(r){
				new PNotify({ title: 'Notificación', text: r.message, type: r.type, styling: 'bootstrap3', delay: 3500 });
				if( r.tk ){ $('input[name="tk"]').val(r.tk); }
				if( r.type === 'success' ){ setTimeout(function(){ window.location = r.redirect; }, 700); } else { btn.prop('disabled', false); }
			},
			error: function(){ new PNotify({ title: 'Notificación', text: 'Ha ocurrido un error guardando los datos', type: 'error', styling: 'bootstrap3' }); btn.prop('disabled', false); }
		});
	});
</script>
