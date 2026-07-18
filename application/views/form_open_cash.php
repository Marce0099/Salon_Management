<h4 class="section-title">Apertura de caja</h4>

<form action="<?php echo base_url('cash/open') ?>" method="post" class="form-horizontal col-md-3" autocomplete="off" spellcheck="false" id="form">
	<input type="hidden" name="tk" value="<?php echo $tk; ?>">
	<div class="form-group">
		<div>
			<label for="quantity">Monto de apertura (Córdobas):
       		 </label>
		</div>
		<div style="position: relative;">
            <input type="text" data-parsley-type="number" data-parsley-min="0" name="quantity" id="quantity" class="form-control has-feedback-left" required="true">
            <span class="form-control-feedback left" style="left: 3px;">C$</span>
        </div>
	</div>
	<div class="form-group">
		<div>
			<label for="quantity_dollar">Monto de apertura (Dólares):
       		 </label>
		</div>
		<div style="position: relative;">
            <input type="text" data-parsley-type="number" data-parsley-min="0" name="quantity_dollar" id="quantity_dollar" class="form-control has-feedback-left" required="true">
            <span class="form-control-feedback left" style="left: 3px;">$</span>
        </div>
	</div>
	<div class="form-group">
		<button class="btn btn-primary" id="btn-submit">Abrir caja</button>
	</div>
</form>

<script>
	$('#form').parsley();
</script>