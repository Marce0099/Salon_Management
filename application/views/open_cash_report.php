<?php 
    $bordes = 'border: 1px solid black;padding: 6px;text-align: center';

    function formatter_date($date){
        $_date = new DateTime( $date );
        $formatter = new IntlDateFormatter(
            'es_ES', // Spanish (Spain)
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            null,
            null,
            "dd 'de' MMMM 'del' yyyy"
        );
        return $formatter->format($_date);
    }
?>
<h3 class="section-title">Reporte de caja abierto</h3>

<p>Se ha abierto caja con un monto de <span style="font-weight:bold;">C$<?php echo $open_quantity; ?></span> y <span style="font-weight:bold;">$<?php echo $open_quantity_dollar; ?></span> el dia de hoy (<?php echo formatter_date(date("Y-m-d")); ?>)</p>
