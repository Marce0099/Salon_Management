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

                      <?php if($this->controller->get_userID() == 1){ ?>
                      <li><a href="<?php echo base_url('sale_goals'); ?>">Meta Mensual</a></li>
                      <?php } ?>
                      <!--<li><a href="<?php //echo base_url('sales/returns'); ?>">Devoluciones</a></li>-->
                    </ul>
                  </li>
                  <li><a><i class="fa fa-list"></i> Inventario <span class="fa fa-chevron-down"></span></a>
                    <ul class="nav child_menu">
                      <li><a href="<?php echo base_url('products'); ?>">Productos</a></li>
                      <li><a href="<?php echo base_url('products/register'); ?>">Registrar producto</a></li>
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
                    </ul>
                  </li>
                  <!--<li><a href="<?php //echo base_url('dollar_exchange'); ?>"><i class="fa fa-cog"></i> Tipo de cambio</a></li>-->
                </ul>
              </div>

            </div>
            <!-- /sidebar menu -->
          </div>
        </div>

        <!-- top navigation -->
        <div class="top_nav">
          <div class="nav_menu">
            <nav>
              <div class="nav toggle">
                <a id="menu_toggle"><i class="fa fa-bars"></i></a>
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
        
        <!-- page content -->
        <div class="right_col" role="main">