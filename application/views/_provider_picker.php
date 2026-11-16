<?php
/*
 * Provider picker: readonly text input that opens a modal with a searchable list.
 * Params: $pp_hidden_id (id of the hidden input holding the provider id),
 *         $pp_hidden_name (optional name attribute of that hidden input),
 *         $pp_default_first (bool, preselect the first provider),
 *         $pp_create_url (optional: endpoint that creates a provider from a name; shows "+ Nuevo proveedor" in the modal),
 *         $pp_tk_selector (selector of the page's form token input, sent as tk to $pp_create_url and refreshed from the reply),
 *         $providers (active providers, objects with id_provider/provider).
 */
$pp_hidden_name = isset($pp_hidden_name) ? $pp_hidden_name : '';
$pp_default_first = !empty($pp_default_first);
$pp_create_url = isset($pp_create_url) ? $pp_create_url : '';
$pp_tk_selector = isset($pp_tk_selector) ? $pp_tk_selector : '';
$pp_def_id = ($pp_default_first && !empty($providers)) ? (int)$providers[0]->id_provider : '';
$pp_def_name = ($pp_default_first && !empty($providers)) ? $providers[0]->provider : '';
?>
<input type="text" id="<?php echo $pp_hidden_id; ?>_name" class="form-control provider-picker-input" readonly placeholder="Seleccionar proveedor" style="cursor:pointer;background:#fff;" data-target="<?php echo $pp_hidden_id; ?>" data-default-name="<?php echo htmlspecialchars($pp_def_name); ?>" value="<?php echo htmlspecialchars($pp_def_name); ?>">
<input type="hidden" id="<?php echo $pp_hidden_id; ?>" <?php if($pp_hidden_name) echo 'name="'.$pp_hidden_name.'"'; ?> data-default="<?php echo $pp_def_id; ?>" value="<?php echo $pp_def_id; ?>">

<style>
	#provider-picker-results tr { cursor:pointer; }
	#provider-picker-results tr.picker-selected td { background-color:#337ab7 !important; color:#fff; }
</style>

<div class="modal fade" id="provider-picker-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal">&times;</button>
			<h4 class="modal-title">Seleccionar Proveedor</h4>
		</div>
		<div class="modal-body">
			<input type="text" id="provider-picker-search" class="form-control" placeholder="Buscar por nombre" autocomplete="off" style="margin-bottom:10px;">
			<?php if($pp_create_url) : ?>
			<div id="provider-picker-new" style="display:none;margin-bottom:10px;">
				<div class="input-group">
					<input type="text" id="provider-picker-new-name" class="form-control" maxlength="150" placeholder="Nombre del nuevo proveedor" autocomplete="off">
					<span class="input-group-btn">
						<button type="button" class="btn btn-success" id="provider-picker-new-save">Guardar</button>
						<button type="button" class="btn btn-default" id="provider-picker-new-cancel">Cancelar</button>
					</span>
				</div>
				<small class="text-danger" id="provider-picker-new-error"></small>
			</div>
			<?php endif; ?>
			<div style="max-height:350px;overflow-y:auto;">
				<table class="table table-bordered table-hover table-condensed">
					<thead><tr><th>Proveedor</th></tr></thead>
					<tbody id="provider-picker-results">
						<tr data-id="" class="pp-none"><td><em>Sin proveedor</em></td></tr>
						<?php foreach($providers as $p) : ?>
						<tr data-id="<?php echo (int)$p->id_provider; ?>"><td><?php echo htmlspecialchars($p->provider); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<div class="modal-footer">
			<?php if($pp_create_url) : ?><button type="button" class="btn btn-info pull-left" id="provider-picker-new-btn">+ Nuevo proveedor</button><?php endif; ?>
			<button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
			<button type="button" class="btn btn-primary" id="provider-picker-ok" disabled>OK</button>
		</div>
	</div></div>
</div>

<script>
$(function(){
	var $modal = $('#provider-picker-modal').appendTo('body'),
	    $rows = $('#provider-picker-results'),
	    $ok = $('#provider-picker-ok'),
	    target = null, sel = null;

	function apply(){
		if(!sel || !target){ return; }
		var id = String(sel.data('id') || '');
		$('#'+target).val(id).trigger('change');
		$('#'+target+'_name').val(id === '' ? '' : sel.text());
		$modal.modal('hide');
	}

	$(document).on('click focusin', '.provider-picker-input', function(){
		if($modal.hasClass('in') || $modal.data('opening')){ return; }
		target = $(this).data('target');
		sel = null; $ok.prop('disabled', true);
		$rows.find('tr').removeClass('picker-selected').show();
		$('#provider-picker-search').val('');
		var cur = String($('#'+target).val() || '');
		$rows.find('tr').each(function(){
			if(cur !== '' && String($(this).data('id')) === cur){ $(this).addClass('picker-selected'); sel = $(this); $ok.prop('disabled', false); }
		});
		$modal.data('opening', true).modal('show');
	});
	$modal.on('shown.bs.modal', function(){ $modal.data('opening', false); $('#provider-picker-search').focus(); });
	$modal.on('hidden.bs.modal', function(){ $modal.data('opening', false); $('#'+target+'_name').blur(); });

	$('#provider-picker-search').on('input', function(){
		var q = $.trim($(this).val()).toLowerCase();
		$rows.find('tr').each(function(){
			var t = $(this).text().toLowerCase();
			$(this).toggle(q === '' || t.indexOf(q) !== -1);
		});
	});
	$rows.on('click', 'tr', function(){
		$rows.find('tr').removeClass('picker-selected');
		sel = $(this).addClass('picker-selected');
		$ok.prop('disabled', false);
	});
	$rows.on('dblclick', 'tr', apply);
	$ok.on('click', apply);

	<?php if($pp_create_url) : ?>
	var createUrl = <?php echo json_encode($pp_create_url); ?>, tkSel = <?php echo json_encode($pp_tk_selector); ?>,
	    $newBox = $('#provider-picker-new'), $newName = $('#provider-picker-new-name'), $newErr = $('#provider-picker-new-error'), $newSave = $('#provider-picker-new-save');
	function newClose(){ $newBox.hide(); $newName.val(''); $newErr.text(''); $('#provider-picker-new-btn').show(); }
	$('#provider-picker-new-btn').on('click', function(){ $(this).hide(); $newBox.show(); $newName.focus(); });
	$('#provider-picker-new-cancel').on('click', newClose);
	$modal.on('hidden.bs.modal', newClose);
	$newName.on('keydown', function(e){ if(e.which === 13){ e.preventDefault(); $newSave.click(); } });
	$newSave.on('click', function(){
		var name = $.trim($newName.val());
		if(name === ''){ $newErr.text('Escriba el nombre del proveedor'); return; }
		$newSave.prop('disabled', true); $newErr.text('');
		$.ajax({ url: createUrl, type: 'post', dataType: 'json', data: { tk: tkSel ? $(tkSel).val() : '', name: name },
			success: function(res){
				if(res.tk && tkSel){ $(tkSel).val(res.tk); }
				$newSave.prop('disabled', false);
				if(res.type !== 'success'){ $newErr.text(res.message); return; }
				var $tr = $('<tr>').attr('data-id', res.provider.id).append($('<td>').text(res.provider.name)).appendTo($rows);
				$rows.find('tr').removeClass('picker-selected');
				sel = $tr.addClass('picker-selected');
				apply();
			},
			error: function(){ $newSave.prop('disabled', false); $newErr.text('Ha ocurrido un error creando el proveedor'); }
		});
	});
	<?php endif; ?>

	window.providerPickerReset = function(hiddenId){
		var $h = $('#'+hiddenId), $n = $('#'+hiddenId+'_name');
		$h.val($h.data('default')); $n.val($n.data('default-name'));
	};
});
</script>
