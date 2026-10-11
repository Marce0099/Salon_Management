  <div class="row">

    <div class="col-md-3">
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

    <div class="col-md-3">
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

    <div class="col-md-3">
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

    <?php if( $is_admin ) : ?>
    <div class="col-md-3">
      <a href="<?php echo base_url('sale_goals'); ?>" style="text-decoration:none; color:inherit;">
        <div class="panel panel-default kpi-tile">
          <div class="panel-heading"><i class="fa fa-bullseye"></i> Meta del mes</div>
          <div class="panel-body">
            <?php if( $goal_progress['amount'] === NULL ) : ?>
              <div class="kpi-value text-muted">—</div>
              <div class="kpi-sub">Sin meta definida para este mes</div>
            <?php else :
              $pct = $goal_progress['percent'];
              $pct_class = $pct >= 100 ? 'text-success' : ( $pct >= 70 ? 'text-warning' : 'text-danger' );
            ?>
              <div class="kpi-value <?php echo $pct_class; ?>"><?php echo number_format($pct,1); ?>%</div>
              <div class="kpi-sub">
                $<?php echo number_format($goal_progress['sales'],2); ?> de
                $<?php echo number_format($goal_progress['amount'],2); ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </a>
    </div>
    <?php endif; ?>

  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="graph" id="showSalesGraph">
        <h3 class="graph_tittle">Tendencia de ventas ($)</h3>
        <canvas></canvas>
      </div> <!-- .graph -->
    </div>
    <div class="col-md-6">
      <div class="graph" id="showPaymentMethodGraph">
        <h3 class="graph_tittle">Ingresos por método de pago</h3>
        <canvas></canvas>
        <div class="graph_legend"></div>
      </div> <!-- .graph -->
    </div>

    <?php if( $is_admin ) : ?>
    <div class="col-md-6">
      <div class="graph" id="showGoalsGraph">
        <h3 class="graph_tittle">Meta vs. ventas reales</h3>
        <canvas></canvas>
        <select></select>
        <div class="graph_legend"></div>
      </div> <!-- .graph -->
    </div>
    <div class="col-md-6">
      <div class="graph" id="showSalesByEmployeeGraph">
        <h3 class="graph_tittle">Ventas por vendedor</h3>
        <canvas></canvas>
      </div> <!-- .graph -->
    </div>

    <?php endif; ?>

    <div class="col-md-6">
      <div class="graph" id="showTopSoldGraph">
        <h3 class="graph_tittle">Top 10 productos y servicios más vendidos</h3>
        <canvas></canvas>
      </div> <!-- .graph -->
    </div>
    <div class="col-md-6">
      <div class="graph" id="showNewClientsGraph">
        <h3 class="graph_tittle">Clientes nuevos por mes</h3>
        <canvas></canvas>
      </div> <!-- .graph -->
    </div>
  </div>

  <script>
    document.getElementById('panel-low-stock-tile').addEventListener('click', function(){
      document.getElementById('low-stock-bell').click();
    });
  </script>
