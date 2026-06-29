
<h3 class="section-title">Listado de metas de ventas mensuales</h3>

<div class="clearfix">
	<a href="<?php echo base_url('sale_goals/register'); ?>" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> Registrar Meta</a>
</div>

<div class="panel panel-default">
	<div class="panel-heading">Búsqueda
	</div>
	<div class="panel-body">
		<form action="<?php echo base_url('/sale_goals'); ?>" class="form-horizontal" autocomplete="off" spellcheck="false">

			<div class="col-md-4">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Mes</label>

							<?php $mgv = Easysearch::getValue('month_goal'); ?>
							<select name="month_goal" class="form-control">
								<option value="" <?php echo $mgv==''?'selected':'' ?>>----</option>
								
								<?php foreach( $this->sale_goals_mdl->getMonths() as $key => $value ): ?>
									<option value="<?php echo $key; ?>" <?php echo $mgv==$key?'selected':'' ?>>
										<?php echo $value; ?>
									</option>
								<?php endforeach; ?>
								
							</select>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="">Año</label>
							<input type="text" name="year_goal" class="form-control" value="<?php echo Easysearch::getValue('year_goal'); ?>">
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3" style="margin-top:24px;">
				<button type="submit" class="btn btn-default">Buscar</button>
				<a href="<?php echo base_url('/sale_goals') ?>" class="btn btn-default">Reiniciar</a>
			</div>
		</form>
	</div>
</div>

<?php if( empty($sale_goal) ) : ?>
	<?php if( isset($get) && !empty($get) ) : ?>
	<p class="alert alert-info">No se encontraron metas con los criterios de búsqueda ingresados</p>
	<?php else : ?>
	<p class="alert alert-info">No hay metas registradas</p>
	<?php endif; ?>
<?php else : ?>

<table class="table table-hover table-striped jambo_table table-bordered">
	<thead>
		
		<tr>
			<th>Mes</th>
			<th>Año</th>
			<th>Meta</th>
			<th>Ventas</th>
			<!--<th></th>-->
		</tr>
	</thead>
	<tbody>
		<?php foreach( $sale_goal as $r ) : ?>
		<tr>
			<td><?php echo $this->sale_goals_mdl->getMonthName($r->month_goal); ?></td>
			<td><?php echo $r->year_goal; ?></td>
			<td><?php echo number_format($r->amount, 0); ?></td>
			<td><?php echo $r->Ganancias != null? number_format($r->Ganancias, 2) : 'No registrado'; ?></td>
			<!--<td>
			<a href="<?php //echo base_url("sale_goals/details/{$r->id_goal}"); ?>"><i class="fa fa-gear"></i> Accion</a>
			</td>-->
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php if( !empty($pagination) ) : ?>
<div class="panel-footer">
	<?php echo $pagination; ?>
</div>
<?php endif; ?>

<?php endif; ?>
