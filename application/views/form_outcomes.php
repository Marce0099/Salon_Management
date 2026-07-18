<h4 class="section-title">Formulario de Egresos</h4>

<form action="<?php echo base_url('cash/register_outcome') ?>" method="post" class="form-horizontal col-md-3" autocomplete="off" spellcheck="false" id="form">
	<input type="hidden" name="tk" value="<?php echo $tk; ?>">
	<div class="form-group">
		<div>
			<label for="quantity">Monto:
       		 </label>
		</div>
		<div style="position: relative;">
            <input type="text" data-parsley-type="number" data-parsley-min="1" name="quantity" id="quantity" class="form-control has-feedback-left" required="true">
            <span class="form-control-feedback left" style="left: 3px;">C$</span>
        </div>
	</div>
	<div class="form-group">
		<label for="description">Descripción:</label>
		<textarea name="description" id="description" class="form-control" required="true" style="height: 100px;"></textarea>
	</div>
	<div class="form-group">
		<button class="btn btn-primary" id="btn-submit">Registrar egreso</button>
	</div>
</form>

<script>
	$('#form').parsley();
</script>