  <div class="row">

    <div class="col-md-4">
      <a href="<?php echo base_url('cash/state'); ?>" style="text-decoration:none; color:inherit;">
        <div class="panel panel-default kpi-tile">
          <div class="panel-heading"><i class="fa fa-money"></i> Caja</div>
          <div class="panel-body">
            <?php if( $open_cash ) : ?>
              <div class="kpi-value text-success">Abierta</div>
              <div class="kpi-sub">
                Ventas hoy: <?php echo $cash_movements['sales_num_total']; ?>
                (C$<?php echo number_format($cash_movements['sales_total_cordobas'],2); ?> |
                 $<?php echo number_format($cash_movements['sales_total_dollar'],2); ?>)
              </div>
            <?php else : ?>
              <div class="kpi-value text-danger">Cerrada</div>
              <div class="kpi-sub">No hay caja abierta hoy</div>
            <?php endif; ?>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="javascript:;" id="panel-low-stock-tile" style="text-decoration:none; color:inherit;">
        <div class="panel panel-default kpi-tile">
          <div class="panel-heading"><i class="fa fa-bell"></i> Stock bajo</div>
          <div class="panel-body">
            <div class="kpi-value <?php echo $low_stock_count > 0 ? 'text-warning' : 'text-success'; ?>"><?php echo $low_stock_count; ?></div>
            <div class="kpi-sub">Producto(s) por debajo del umbral de alerta</div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a href="<?php echo base_url('appointments'); ?>" style="text-decoration:none; color:inherit;">
        <div class="panel panel-default kpi-tile">
          <div class="panel-heading"><i class="fa fa-calendar"></i> Próximas reservas</div>
          <div class="panel-body">
            <div class="kpi-value"><?php echo $upcoming_appointments; ?></div>
            <div class="kpi-sub">En los próximos 7 días</div>
          </div>
        </div>
      </a>
    </div>

  </div>

  <div class="row">
    <div class="col-xs-12">
      <div class="text-center">

        <div class="graph" id="showSalesGraph">
          <h3 class="graph_tittle">Tendencia de ventas ($)</h3>
          <canvas></canvas>
          <select></select>
        </div> <!-- .graph -->

        <div class="graph" id="showGoalsGraph">
          <h3 class="graph_tittle">Meta vs. ventas reales</h3>
          <canvas></canvas>
          <select></select>
        </div> <!-- .graph -->

        <div class="graph" id="showTopSoldGraph">
          <h3 class="graph_tittle">Top 10 productos y servicios más vendidos</h3>
          <canvas></canvas>
        </div> <!-- .graph -->

      </div>
    </div>
  </div>

  <script>
    document.getElementById('panel-low-stock-tile').addEventListener('click', function(){
      document.getElementById('low-stock-bell').click();
    });
  </script>
