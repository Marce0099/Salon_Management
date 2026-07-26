<!DOCTYPE html>
<html lang="es">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Conceptos - Sistema de Facturación</title>

    <!-- Bootstrap -->
    <link href="<?php echo base_url('assets/gentelella/vendors/bootstrap/dist/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo base_url('assets/gentelella/vendors/font-awesome/css/font-awesome.min.css'); ?>" rel="stylesheet">
    <!-- NProgress -->
    <link href="<?php echo base_url('assets/gentelella/vendors/nprogress/nprogress.css'); ?>" rel="stylesheet">
    <!-- iCheck -->
    <link href="<?php echo base_url('assets/gentelella/vendors/iCheck/skins/flat/green.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url('assets/gentelella/vendors/iCheck/skins/flat/red.css'); ?>" rel="stylesheet">
	 <!-- animate -->
    <link href="<?php echo base_url('assets/gentelella/vendors/animate.css/animate.min.css'); ?>" rel="stylesheet">

    <!-- bootstrap-progressbar -->
    <link href="<?php echo base_url('assets/gentelella/vendors/bootstrap-progressbar/css/bootstrap-progressbar-3.3.4.min.css'); ?>" rel="stylesheet">
    <!-- JQVMap -->
    <link href="<?php echo base_url('assets/gentelella/vendors/jqvmap/dist/jqvmap.min.css'); ?>" rel="stylesheet"/>
    <!-- bootstrap-daterangepicker -->
    <link href="<?php echo base_url('assets/gentelella/vendors/bootstrap-daterangepicker/daterangepicker.css'); ?>" rel="stylesheet">

    <!-- pnotify -->
    <link href="<?php echo base_url('assets/gentelella/vendors/pnotify/dist/pnotify.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url('assets/gentelella/vendors/pnotify/dist/pnotify.buttons.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url('assets/gentelella/vendors/pnotify/dist/pnotify.brighttheme.css'); ?>" rel="stylesheet">

    <!-- Custom Theme Style -->
    <link href="<?php echo base_url('assets/gentelella/build/css/custom.css'); ?>" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/admin.css'); ?>" rel="stylesheet">
    <style>
        #low-stock-bell i{
            font-size: 18px;
            display: inline-block;
            margin-top: 4px;
        }
        .low-stock-badge{
            position: absolute;
            top: -6px;
            right: -10px;
            background: #e08e0b;
            color: #fff;
            border-radius: 10px;
            font-size: 11px;
            line-height: 1;
            padding: 2px 5px;
            font-weight: bold;
            z-index: 2;
        }
    </style>
    <?php if($_SERVER["PATH_INFO"] == "/panel") { ?>
    <link href="<?php echo base_url('assets/css/chart.css'); ?>" rel="stylesheet">
    <?php } ?>
    <!-- jQuery -->
    <script src="<?php echo base_url("assets/gentelella/vendors/jquery/dist/jquery.min.js"); ?>"></script>
    
    <script src="<?php echo base_url("assets/js/numeraljs.min.js"); ?>"></script>
    <script src="<?php echo base_url("assets/js/bootbox.min.js"); ?>"></script>

    <!-- validation -->
    <script src="<?php echo base_url("assets/gentelella/vendors/parsleyjs/dist/parsley.min.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/parsleyjs/src/i18n/es.js"); ?>"></script>
  </head>

  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <div class="col-md-3 left_col menu_fixed">
          <span id="sidebar-scroll-up" class="sidebar-scroll-arrow sidebar-scroll-up"><i class="fa fa-chevron-up"></i></span>
          <div class="left_col scroll-view">
            <div class="navbar nav_title" style="border: 0;">
              <a href="<?php echo base_url(); ?>" class="site_title"><img src="<?php echo base_url('assets/img/logo.png'); ?>" alt=""></a>
            </div>

            <div class="clearfix"></div>

            <br />

            <!-- sidebar menu -->
            <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
              <div class="menu_section">
                <ul class="nav side-menu">
                  <li><a><i class="fa fa-dollar"></i> Ventas <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('sales/historial'); ?>">Historial</a></li>
                      <li><a href="<?php echo base_url('sales/register'); ?>">Registrar</a></li>

                      <?php if($this->controller->is_admin()){ ?>
                      <li><a href="<?php echo base_url('sale_goals'); ?>">Meta Mensual</a></li>
                      <?php } ?>
                      <!--<li><a href="<?php //echo base_url('sales/returns'); ?>">Devoluciones</a></li>-->
                    </ul>
                  </li>
                  <li><a><i class="fa fa-list"></i> Inventario <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('products'); ?>">Productos</a></li>
                      <?php if($this->controller->is_admin()){ ?>
                      <li><a href="<?php echo base_url('products/register'); ?>">Registrar producto</a></li>
                      <?php } ?>
                      <li><a href="<?php echo base_url('products/internalUse'); ?>">Uso interno</a></li>
                      <li><a href="<?php echo base_url('products/internal_use_list'); ?>">Historial de uso interno</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-cut"></i> Servicios <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('services'); ?>">Lista</a></li>
                      <?php if($this->controller->is_admin()){ ?>
                      <li><a href="<?php echo base_url('products/register?is_service=1'); ?>">Crear Servicio</a></li>
                      <?php } ?>
                      <li><a href="<?php echo base_url('service_categories'); ?>">Categorias</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-truck"></i> Proveedores <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('providers'); ?>">Listado</a></li>
                      <li><a href="<?php echo base_url('providers/register'); ?>">Registrar</a></li>
                      <li><a href="<?php echo base_url('invoices'); ?>">Facturas</a></li>
                      <li><a href="<?php echo base_url('invoices/register'); ?>">Registrar factura</a></li>
                      <!--<li><a href="<?php //echo base_url('orders'); ?>">Pedidos</a></li>-->
                      <li><a href="<?php echo base_url('returns'); ?>">Devoluciones</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-money"></i> Caja <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('cash/state'); ?>">Estado</a></li>
                      <li><a href="<?php echo base_url('cash/open'); ?>">Abrir caja</a></li>
                      <li><a href="<?php echo base_url('cash/close'); ?>">Cerrar caja</a></li>
                      <li><a href="<?php echo base_url('cash/register_outcome'); ?>">Registrar egreso</a></li>
                      <li><a href="<?php echo base_url('cash/historial'); ?>">Historial</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-users"></i> Vendedores <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('employees'); ?>">Listado</a></li>
                      <li><a href="<?php echo base_url('employees/register'); ?>">Agregar</a></li>
                      <li><a href="<?php echo base_url('employees/commissions'); ?>">Comisiones</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-users"></i> Clientes <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('clients'); ?>">Listado</a></li>
                      <li><a href="<?php echo base_url('clients/register'); ?>">Agregar</a></li>
                    </ul>
                  </li>
                  <li><a><i class="fa fa-cog"></i> Ajustes <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('dollar_exchange'); ?>">Tipo de cambio</a></li>
                      <li><a href="<?php echo base_url('bank_account'); ?>">Cuentas Bancarias</a></li>
                      <?php if($this->controller->is_admin()){ ?>
                      <li><a href="<?php echo base_url('admins/list'); ?>">Administradores</a></li>
                      <li><a href="<?php echo base_url('loyalty_points'); ?>">Programa de puntos</a></li>
                      <li><a href="<?php echo base_url('settings'); ?>">Configuración general</a></li>
                      <?php } ?>
                    </ul>
                  </li>
                  <!--<li><a href="<?php //echo base_url('dollar_exchange'); ?>"><i class="fa fa-cog"></i> Tipo de cambio</a></li>-->
                  <li><a href="<?php echo base_url('appointments'); ?>"><i class="fa fa-calendar"></i> Reservas</a></li>
                </ul>
              </div>

            </div>
            <!-- /sidebar menu -->
          </div>
          <span id="sidebar-scroll-down" class="sidebar-scroll-arrow sidebar-scroll-down"><i class="fa fa-chevron-down"></i></span>
        </div>

        <!-- top navigation -->
        <div class="top_nav">
          <div class="nav_menu">
            <nav>
              <div class="nav toggle">
                <a id="menu_toggle"><i class="fa fa-bars"></i></a>
              </div>

              <div class="nav toggle">
                <a href="javascript:;" id="low-stock-bell">
                  <span style="position:relative; display:inline-block;">
                    <i class="fa fa-bell"></i>
                    <span id="low-stock-badge" class="low-stock-badge hide">0</span>
                  </span>
                </a>
              </div>

              <ul class="nav navbar-nav navbar-right">
                <li class="">
                  <a href="javascript:;" class="user-profile dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                    <i class="fa fa-user"></i> <?php echo $this->session->userdata('admin_name'); ?>
                    <span class=" fa fa-angle-down"></span>
                  </a>
                  <ul class="dropdown-menu dropdown-usermenu pull-right">
                    <li><a href="<?php echo base_url('login/out'); ?>"><i class="fa fa-sign-out pull-right"></i> Salir</a></li>
                  </ul>
                </li>
              </ul>
            </nav>
          </div>
        </div>
        <!-- /top navigation -->

        <div class="modal fade" id="low-stock-modal" tabindex="-1" role="dialog">
          <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">Productos con stock bajo</h4>
              </div>
              <div class="modal-body">
                <div style="max-height:400px;overflow-y:auto;">
                  <table class="table table-bordered table-hover table-condensed">
                    <thead>
                      <tr><th>Producto</th><th>Stock actual</th><th>Umbral</th></tr>
                    </thead>
                    <tbody id="low-stock-results"></tbody>
                  </table>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
              </div>
            </div>
          </div>
        </div>

        <!-- page content -->
        <div class="right_col" role="main">