(function(){

    const baseUrl = window.SALES_REPORT_BASE_URL;

    const colorPalette = [
        '#2a78d6', '#eb6834', '#1baf7a', '#eda100',
        '#e87ba4', '#008300', '#4a3aa7', '#e34948'
    ];
    const gridColor = '#e1e0d9';
    const axisColor = '#898781';

    let rows = [];
    let charts = {};

    function safeChart(canvas, config){
        try {
            return new Chart(canvas, config);
        } catch(e){
            console.error('SalesReport: failed to render', canvas.closest('.graph') && canvas.closest('.graph').id, e);
            return null;
        }
    }

    function baseScales(extra){
        return Object.assign({
            xAxes: [{ gridLines: { color: gridColor, zeroLineColor: axisColor }, ticks: { fontColor: axisColor } }],
            yAxes: [{ gridLines: { color: gridColor, zeroLineColor: axisColor }, ticks: { fontColor: axisColor, beginAtZero: true } }]
        }, extra || {});
    }

    function renderLegend(container, items){
        if( !container ) return;
        container.innerHTML = '';
        for( const item of items ){
            const chip = document.createElement('span');
            chip.className = 'graph_legend_item';
            chip.innerHTML = `<span class="graph_legend_swatch" style="background:${item.color}"></span>${item.label}`;
            container.appendChild(chip);
        }
    }

    function money(n){
        return 'C$' + Number(n || 0).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function initDatePickers(){

        const locale = {
            format: 'DD-MM-YYYY',
            daysOfWeek: ["Do","Lu","Ma","Mi","Ju","Vi","Sa"],
            monthNames: ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"]
        };

        $('.dtpicker').daterangepicker({
            locale: locale,
            maxDate: moment().format('DD-MM-YYYY'),
            singleDatePicker: true,
            showDropdowns: true
        });

        $('.dtpicker').val('');
    }

    function getSelectedRange(){

        const startPicker = $('#date_start').data('daterangepicker');
        const endPicker = $('#date_end').data('daterangepicker');

        const dateStart = $('#date_start').val().trim();
        const dateEnd = $('#date_end').val().trim();

        if( !dateStart || !dateEnd ){
            return { error: 'Selecciona la fecha desde y la fecha hasta.' };
        }

        if( startPicker && endPicker && endPicker.startDate.isBefore(startPicker.startDate, 'day') ){
            return { error: 'La fecha hasta no puede ser anterior a la fecha desde.' };
        }

        return { dateStart, dateEnd };
    }

    async function fetchReport(dateStart, dateEnd){

        const body = new URLSearchParams();
        body.set('date_start', dateStart);
        body.set('date_end', dateEnd);

        const res = await fetch(`${baseUrl}sales_report/data`, { method: 'POST', body });
        return res.json();
    }

    function aggregateByDay(){

        const byDay = {};
        rows.forEach(r => {
            const day = r.date_time.substring(0, 10);
            if( !byDay[day] ) byDay[day] = { venta: 0, ganancia_real: 0 };
            byDay[day].venta += r.venta;
            byDay[day].ganancia_real += r.ganancia_real;
        });

        const days = Object.keys(byDay).sort();
        return {
            labels: days.map(d => moment(d, 'YYYY-MM-DD').format('DD/MM')),
            venta: days.map(d => byDay[d].venta),
            ganancia_real: days.map(d => byDay[d].ganancia_real)
        };
    }

    function aggregateBy(field, valueField, limit){

        const grouped = {};
        rows.forEach(r => {
            const key = r[field] || 'Sin asignar';
            grouped[key] = (grouped[key] || 0) + r[valueField];
        });

        const sorted = Object.entries(grouped).sort((a, b) => b[1] - a[1]).slice(0, limit);

        return {
            labels: sorted.map(e => e[0]),
            values: sorted.map(e => e[1])
        };
    }

    function renderKpis(){

        const totalVenta = rows.reduce((s, r) => s + r.venta, 0);
        const totalUtilidad = rows.reduce((s, r) => s + r.utilidad_bruta, 0);
        const totalComision = rows.reduce((s, r) => s + r.comision, 0);
        const totalGanancia = rows.reduce((s, r) => s + r.ganancia_real, 0);
        const numVentas = new Set(rows.map(r => r.id_sale)).size;
        const ticketPromedio = numVentas > 0 ? totalVenta / numVentas : 0;

        document.getElementById('kpi-total-venta').textContent = money(totalVenta);
        document.getElementById('kpi-total-utilidad').textContent = money(totalUtilidad);
        document.getElementById('kpi-total-comision').textContent = money(totalComision);
        document.getElementById('kpi-total-ganancia').textContent = money(totalGanancia);
        document.getElementById('kpi-num-ventas').textContent = numVentas;
        document.getElementById('kpi-ticket-promedio').textContent = money(ticketPromedio);
    }

    function destroyCharts(){
        Object.values(charts).forEach(c => { if(c) c.destroy(); });
        charts = {};
    }

    function renderCharts(){

        destroyCharts();

        const trend = aggregateByDay();
        charts.trend = safeChart(document.querySelector('#chartTrend canvas'), {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [
                    {
                        label: 'Venta',
                        data: trend.venta,
                        borderColor: colorPalette[0],
                        backgroundColor: colorPalette[0] + '33',
                        pointBackgroundColor: colorPalette[0],
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true
                    },
                    {
                        label: 'Ganancia real',
                        data: trend.ganancia_real,
                        borderColor: colorPalette[1],
                        backgroundColor: colorPalette[1] + '33',
                        pointBackgroundColor: colorPalette[1],
                        borderWidth: 2,
                        pointRadius: 3,
                        fill: true
                    }
                ]
            },
            options: { legend: { display: false }, scales: baseScales() }
        });
        renderLegend(document.querySelector('#chartTrend .graph_legend'), [
            { label: 'Venta', color: colorPalette[0] },
            { label: 'Ganancia real', color: colorPalette[1] }
        ]);

        const byEmployee = aggregateBy('employee', 'ganancia_real', 10);
        charts.byEmployee = safeChart(document.querySelector('#chartByEmployee canvas'), {
            type: 'bar',
            data: {
                labels: byEmployee.labels,
                datasets: [{ label: 'Ganancia real', data: byEmployee.values, backgroundColor: colorPalette[0] }]
            },
            options: {
                legend: { display: false },
                scales: baseScales({
                    xAxes: [{ gridLines: { color: gridColor, zeroLineColor: axisColor }, ticks: { fontColor: axisColor, maxRotation: 60, minRotation: 40 } }]
                })
            }
        });

        const byProduct = aggregateBy('product', 'ganancia_real', 10);
        charts.byProduct = safeChart(document.querySelector('#chartTopProducts canvas'), {
            type: 'bar',
            data: {
                labels: byProduct.labels,
                datasets: [{ label: 'Ganancia real', data: byProduct.values, backgroundColor: colorPalette[2] }]
            },
            options: {
                legend: { display: false },
                scales: baseScales({
                    xAxes: [{ gridLines: { color: gridColor, zeroLineColor: axisColor }, ticks: { fontColor: axisColor, maxRotation: 60, minRotation: 40 } }]
                })
            }
        });
    }

    function renderTable(){

        const tbody = document.getElementById('sales-report-table-body');
        const fragment = document.createDocumentFragment();

        rows.forEach(r => {
            const tr = document.createElement('tr');
            [
                r.id_sale,
                moment(r.date_time).format('DD/MM/YYYY HH:mm'),
                r.client,
                r.product,
                r.is_service ? 'Servicio' : 'Producto',
                r.quantity,
                money(r.price),
                r.discount + '%',
                r.employee || '-',
                money(r.venta),
                money(r.utilidad_bruta),
                money(r.comision),
                money(r.ganancia_real)
            ].forEach(val => {
                const td = document.createElement('td');
                td.textContent = val;
                tr.appendChild(td);
            });
            fragment.appendChild(tr);
        });

        tbody.innerHTML = '';
        tbody.appendChild(fragment);
    }

    async function generarReporte(){

        const range = getSelectedRange();
        if( range.error ){
            new PNotify({ title: 'Notificación', text: range.error, type: 'error', styling: 'bootstrap3', delay: 3500 });
            return;
        }

        $('#reportLoadingModal').modal('show');
        document.getElementById('btnDescargarExcel').disabled = true;

        try {
            rows = await fetchReport(range.dateStart, range.dateEnd);
        } catch(e){
            $('#reportLoadingModal').modal('hide');
            new PNotify({ title: 'Notificación', text: 'Ocurrió un error al generar el reporte.', type: 'error', styling: 'bootstrap3', delay: 3500 });
            console.error('SalesReport: fetch failed', e);
            return;
        }

        $('#reportLoadingModal').modal('hide');

        if( !rows || rows.length === 0 ){
            document.getElementById('sales-report-dashboard').style.display = 'none';
            document.getElementById('sales-report-empty').style.display = 'block';
            document.getElementById('btnDescargarExcel').disabled = true;
            return;
        }

        document.getElementById('sales-report-empty').style.display = 'none';
        document.getElementById('sales-report-dashboard').style.display = 'block';

        renderKpis();
        renderCharts();
        renderTable();

        document.getElementById('btnDescargarExcel').disabled = false;
        document.getElementById('btnDescargarExcel').dataset.dateStart = range.dateStart;
        document.getElementById('btnDescargarExcel').dataset.dateEnd = range.dateEnd;
    }

    function descargarExcel(){

        const btn = document.getElementById('btnDescargarExcel');
        const dateStart = btn.dataset.dateStart;
        const dateEnd = btn.dataset.dateEnd;

        if( !dateStart || !dateEnd ) return;

        window.location.href = `${baseUrl}sales_report/export?date_start=${encodeURIComponent(dateStart)}&date_end=${encodeURIComponent(dateEnd)}`;
    }

    document.addEventListener('DOMContentLoaded', function(){

        initDatePickers();

        document.getElementById('btnGenerarReporte').addEventListener('click', generarReporte);
        document.getElementById('btnDescargarExcel').addEventListener('click', descargarExcel);
    });

}());
