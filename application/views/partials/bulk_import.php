<?php
	// expects: $import_url, $template_url, $tk, $entity_label (e.g. "proveedores")
?>
<div class="bulk-import" style="margin-bottom:15px;">
	<a href="<?php echo $template_url; ?>" class="btn btn-default"><i class="fa fa-download"></i> Descargar plantilla Excel</a>
	<button type="button" id="bulk-upload-btn" class="btn btn-primary"><i class="fa fa-upload"></i> Subir datos desde Excel</button>
	<input type="file" id="bulk-file" accept=".xlsx" style="display:none;">
	<span class="text-muted" style="margin-left:8px;">Cargue varios <?php echo $entity_label; ?> a la vez usando la plantilla.</span>
</div>

<div class="modal fade" id="bulk-wait-modal" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
	<div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
			<div class="modal-body text-center" style="padding:30px;">
				<i class="fa fa-spinner fa-spin fa-3x"></i>
				<p style="margin-top:15px;margin-bottom:0;">Procesando archivo, por favor espere...</p>
			</div>
		</div>
	</div>
</div>

<div class="modal fade" id="bulk-result-modal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal">&times;</button>
				<h4 class="modal-title" id="bulk-result-title"></h4>
			</div>
			<div class="modal-body">
				<p id="bulk-result-message"></p>
				<div id="bulk-result-list" style="max-height:260px;overflow:auto;"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
			</div>
		</div>
	</div>
</div>

<script>
(function(){

	var importUrl = <?php echo json_encode($import_url); ?>;
	var token = <?php echo json_encode($tk); ?>;
	var reloadOnClose = false;

	var $file = $('#bulk-file');
	var $wait = $('#bulk-wait-modal');
	var $result = $('#bulk-result-modal');
	var waitShown = false;

	$wait.on('shown.bs.modal', function(){ waitShown = true; }).on('hidden.bs.modal', function(){ waitShown = false; });

	// hide the wait modal only once its show animation finished, then run cb after it is fully hidden
	function hideWait(cb){

		function doHide(){
			$wait.one('hidden.bs.modal', cb).modal('hide');
		}

		if( waitShown ){
			doHide();
		}else{
			$wait.one('shown.bs.modal', doHide);
		}
	}

	function showResult(title, message, rows, heading){

		$('#bulk-result-title').text(title);
		$('#bulk-result-message').text(message);

		var $list = $('#bulk-result-list').empty();

		if( rows && rows.length ){

			var $table = $('<table class="table table-condensed table-striped">');
			$table.append($('<thead>').append($('<tr>').append($('<th>').text('Fila'), $('<th>').text(heading))));

			var $body = $('<tbody>').appendTo($table);
			$.each(rows, function(i, r){
				$body.append($('<tr>').append($('<td>').text(r.row), $('<td>').text(r.reason)));
			});

			$list.append($table);
		}

		$result.modal('show');
	}

	$result.on('hidden.bs.modal', function(){
		if( reloadOnClose ){
			window.location.reload();
		}
	});

	var busy = false;
	var $uploadBtn = $('#bulk-upload-btn');

	function finish(){
		busy = false;
		$uploadBtn.prop('disabled', false);
	}

	$uploadBtn.on('click', function(){
		if( busy ){
			return;
		}
		$file.val('').trigger('click');
	});

	$file.on('change', function(){

		if( busy || !this.files.length ){
			return;
		}

		var file = this.files[0];

		// cheap checks before sending anything (the server repeats all of them)
		if( !/\.xlsx$/i.test(file.name) ){
			showResult('Archivo inválido', 'Use la plantilla de Excel en formato .xlsx');
			return;
		}

		if( file.size > 5 * 1024 * 1024 ){
			showResult('Archivo inválido', 'El archivo supera el tamaño máximo de 5 MB');
			return;
		}

		busy = true;
		$uploadBtn.prop('disabled', true);

		var formData = new FormData();
		formData.append('file', file);
		formData.append('tk', token);

		$wait.modal('show');

		$.ajax({
			url : importUrl,
			type : 'post',
			data : formData,
			processData : false,
			contentType : false,
			dataType : 'json',
			timeout : 300000
		}).done(function(data){

			if( data.tk ){
				token = data.tk;
			}

			// the wait modal must be fully hidden before another one is opened (Bootstrap 3 backdrop bug)
			hideWait(function(){

				finish();

				if( data.type === 'error' ){
					showResult('La carga falló', data.message, data.errors, 'Motivo');
					return;
				}

				var msg = data.message + ( data.skipped ? ' Filas duplicadas omitidas: ' + data.skipped + '.' : '' );
				reloadOnClose = data.inserted > 0;
				showResult(data.type === 'success' ? 'Carga completada' : 'Sin cambios', msg, data.duplicates, 'Detalle');
			});

		}).fail(function(xhr, status){

			// a 303 to the login page is followed by the browser and comes back as HTML, not JSON
			var expired = xhr.responseText && xhr.responseText.indexOf('name="password"') !== -1;
			var message = expired
				? 'Su sesión expiró. Inicie sesión nuevamente e intente de nuevo; no se guardó ningún dato.'
				: ( status === 'timeout'
					? 'El servidor tardó demasiado en responder. Revise la lista antes de volver a subir el archivo.'
					: 'Ha ocurrido un error procesando el archivo. Revise la lista antes de volver a subir el archivo.' );

			hideWait(function(){
				finish();
				showResult('La carga falló', message);
			});
		});
	});

})();
</script>
