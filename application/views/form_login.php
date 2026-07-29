<!DOCTYPE html>
<html lang="es">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <!-- Meta, title, CSS, favicons, etc. -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo htmlspecialchars($company_name); ?> - Sistema de Facturación</title>

    <!-- Bootstrap -->
    <link href="<?php echo base_url("assets/gentelella/vendors/bootstrap/dist/css/bootstrap.min.css"); ?>" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="<?php echo base_url("assets/gentelella/vendors/font-awesome/css/font-awesome.min.css"); ?>" rel="stylesheet">
    <!-- NProgress -->
    <link href="<?php echo base_url("assets/gentelella/vendors/nprogress/nprogress.css"); ?>" rel="stylesheet">

    <!-- Custom Theme Style -->
    <link rel="stylesheet" href="<?php echo base_url("assets/css/admin.css"); ?>">
  </head>
  <body class="login">
      <div class="container">
        <div class="row">
          <div class="col-md-4 col-md-offset-4 col-sm-8 col-sm-offset-2">
            <form action="<?php echo base_url("login/check"); ?>" class="form form-login" method="post">
              <div class="form-header text-center">
                <img class="img-responsive" src="<?php echo resolveLogoUrl('logo_login', 'logo_white.png'); ?>" alt="">
              </div>
              <div class="form-body">
                <?php if( isset($error) ) : ?>
              <p class="alert alert-danger">Acceso incorrecto</p>
              <?php endif; ?>
                <div class="form-group">
                  <label for="user">Usuario:</label>
                  <input type="text" name="username" class="form-control" autocomplete="off" spellcheck="false">
                </div>
                <div class="form-group">
                  <label for="password">Contraseña:</label>
                  <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group text-center">
                  <button type="submit" class="btn btn-primary">Entrar</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
  </body>
</html>
