    </div>
        <!-- /page content -->

<!-- footer content 
        <footer>
          Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sed nostrum iste, nobis, et fugit numquam aut accusantium, reprehenderit molestiae quas quasi? Repellendus fuga suscipit, qui iste, molestiae debitis! Quas, libero.
        </footer>
        /footer content -->
      </div>
    </div>
    
    <!-- Bootstrap -->
    <script src="<?php echo base_url("assets/gentelella/vendors/bootstrap/dist/js/bootstrap.min.js"); ?>"></script>
    <!-- FastClick -->
    <script src="<?php echo base_url("assets/gentelella/vendors/fastclick/lib/fastclick.js"); ?>"></script>
    <!-- NProgress -->
    <script src="<?php echo base_url("assets/gentelella/vendors/nprogress/nprogress.js"); ?>"></script>
    <!-- Chart.js -->
    <script src="<?php echo base_url("assets/gentelella/vendors/Chart.js/dist/Chart.min.js"); ?>"></script>

    <!-- PanelChart.js -->
    <?php if($_SERVER["PATH_INFO"] === "/panel"){ ?>
      <script src="<?php echo base_url("assets/js/PanelChart.js"); ?>"></script>
    <?php } ?>
    <!-- gauge.js -->
    <script src="<?php echo base_url("assets/gentelella/vendors/gauge.js/dist/gauge.min.js"); ?>"></script>
    <!-- bootstrap-progressbar -->
    <script src="<?php echo base_url("assets/gentelella/vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"); ?>"></script>
    <!-- iCheck -->
    <script src="<?php echo base_url("assets/gentelella/vendors/iCheck/icheck.min.js"); ?>"></script>
    <!-- Skycons -->
    <script src="<?php echo base_url("assets/gentelella/vendors/skycons/skycons.js"); ?>"></script>
    <!-- Flot -->
    <script src="<?php echo base_url("assets/gentelella/vendors/Flot/jquery.flot.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/Flot/jquery.flot.pie.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/Flot/jquery.flot.time.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/Flot/jquery.flot.stack.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/Flot/jquery.flot.resize.js"); ?>"></script>
    <!-- Flot plugins -->
    <script src="<?php echo base_url("assets/gentelella/vendors/flot.orderbars/js/jquery.flot.orderBars.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/flot-spline/js/jquery.flot.spline.min.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/flot.curvedlines/curvedLines.js"); ?>"></script>
    <!-- DateJS -->
    <script src="<?php echo base_url("assets/gentelella/vendors/DateJS/build/date.js"); ?>"></script>
    <!-- JQVMap -->
    <script src="<?php echo base_url("assets/gentelella/vendors/jqvmap/dist/jquery.vmap.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/jqvmap/dist/maps/jquery.vmap.world.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"); ?>"></script>
    <!-- bootstrap-daterangepicker -->
    <script src="<?php echo base_url("assets/gentelella/vendors/moment/min/moment.min.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/bootstrap-daterangepicker/daterangepicker.js"); ?>"></script>

    <!-- pnotify -->
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.buttons.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.confirm.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.animate.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.callbacks.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.history.js"); ?>"></script>
    <script src="<?php echo base_url("assets/gentelella/vendors/pnotify/dist/pnotify.reference.js"); ?>"></script>

    <!-- Custom Theme Scripts -->
    <script src="<?php echo base_url("assets/gentelella/build/js/custom.js"); ?>"></script>

    <?php if( isset($status) ) : ?>
    <script>

        new PNotify({
            title: 'Notificación',
            text: '<?php echo $status['message']; ?>',
            type : '<?php echo $status['class']; ?>',
            styling : 'bootstrap3',
            delay : <?php echo isset($status['delay']) ? $status['delay'] : 3500; ?>
        });
    </script>
    <?php endif; ?>
	
  </body>
</html>
