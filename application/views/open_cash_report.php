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
<h3 class="section-title">Cumpleañeros <?php echo htmlspecialchars($company_name); ?></h3>

<table style="border-collapse: collapse;">
    <thead>
        <tr>
            <th style="<?php echo $bordes; ?>">Cedula</th>
            <th style="<?php echo $bordes; ?>">Nombre</th>
            <th style="<?php echo $bordes; ?>">Celular</th>
            <th style="<?php echo $bordes; ?>">Correo</th>
            <th style="<?php echo $bordes; ?>">Fecha Registro</th>
            <th style="<?php echo $bordes; ?>">Membresia</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($birth AS $b): ?>
            <tr>
                <td style="<?php echo $bordes; ?>"><?php echo $b->id_card_client; ?></td>
                <td style="<?php echo $bordes; ?>"><?php echo $b->name_client . " " . $b->surname_client; ?></td>
                <td style="<?php echo $bordes; ?>"><?php echo $b->phone_client; ?></td>
                <td style="<?php echo $bordes; ?>"><?php echo $b->email_client; ?></td>
                <td style="<?php echo $bordes; ?>"><?php echo  formatter_date($b->registered_client); ?></td>
                <td style="<?php echo $bordes; ?>"><?php echo $b->is_vip_client?'VIP':'Normal'; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>