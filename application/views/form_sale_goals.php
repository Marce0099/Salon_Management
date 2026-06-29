<h4 class="section-title">Formulario de meta de ventas</h4>

<form action="<?php echo base_url('sale_goals/register'); ?>" method="post" class="form-horizontal" autocomplete="off" id="form">
    <div class="form-group">
        <label for="" class="control-label col-md-3 col-sm-3 col-xs-12">Mes</label>

        <div class="col-md-6 col-sm-6 col-xs-12">
            <select name="month" class="form-control">
                <option value="" disabled selected>Seleccione mes</option>
                
                <?php foreach( $this->sale_goals_mdl->getMonths() as $key => $value ): ?>
                    <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                <?php endforeach; ?>
                
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="" class="control-label col-md-3 col-sm-3 col-xs-12">Año</label>
        <div class="col-md-6 col-sm-6 col-xs-12">
            <input type="number" name="year" class="form-control" placeholder="Ingrese año" min="2000" step="1">
        </div>
    </div>

    <div class="form-group">
        <label for="" class="control-label col-md-3 col-sm-3 col-xs-12">Meta</label>
        <div class="col-md-6 col-sm-6 col-xs-12">
            <input type="number" name="goal" class="form-control" placeholder="Ingrese meta"">
        </div>
    </div>

    <div class="col-md-offset-3 col-sm-offset-3 col-md-3 col-sm-3">
        <button class="btn btn-success" id="btn-submit" type="submit">Registrar Meta</button>
    </div>
</form>