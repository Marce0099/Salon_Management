(function(){

    const system = {
        path : window.location.pathname,
        // Validated categorical palette (fixed order — see dataviz skill).
        // Never cycled past 8, never reassigned by rank/filter.
        colorPalette : [
            '#2a78d6', // 1 blue
            '#eb6834', // 2 orange
            '#1baf7a', // 3 aqua
            '#eda100', // 4 yellow
            '#e87ba4', // 5 magenta
            '#008300', // 6 green
            '#4a3aa7', // 7 violet
            '#e34948'  // 8 red
        ],
        gridColor : '#e1e0d9',
        axisColor : '#898781'
    };
    const chart = {
        showSalesGraph : {filter: '', source : []},
        showGoalsGraph : {filter: '', source : []},
        showTopSoldGraph : {filter: '', source : []},
        showPaymentMethodGraph : {filter: '', source : []},
        showSalesByEmployeeGraph : {filter: '', source : []},
        showNewClientsGraph : {filter: '', source : []}
    };

    document.addEventListener("DOMContentLoaded", async e => {

        console.log('init_PanelChart');
        // Loaded sequentially: concurrent requests contend on the app's
        // DB-backed session lock (GET_LOCK) and can 500 under load.
        await showSalesGraph();
        await showPaymentMethodGraph();
        await showGoalsGraph();
        await showSalesByEmployeeGraph();
        await showTopSoldGraph();
        await showNewClientsGraph();

    });

    /**
     * Construct a Chart.js instance defensively — a malformed chart (or any
     * other draw-time bug) must not take out the rest of the dashboard.
     *
     * Multi-dataset charts must set `legend: {display: false}` and call
     * renderLegend() instead: this vendored Chart.js 2.1.4's built-in legend
     * crashes mid-draw for any chart with more than one dataset (confirmed
     * with both 2 and 4 series, regardless of label length), and because all
     * charts on the page share one animation loop, that crash silently kills
     * every chart created after it too.
     */
    function safeChart(canvas, config){
        try {
            return new Chart(canvas, config);
        } catch(e){
            console.error('PanelChart: failed to render', canvas.closest('.graph') && canvas.closest('.graph').id, e);
            return null;
        }
    }

    /**
     * Obtener el nombre del mes en funcion al numero de mes (1-12)
     * @param {number|string} month
     * @returns {string}
     */
    function getMonthNameFromNumber(month){
        const date = new Date(2000, Number(month) - 1, 1);
        return date.toLocaleDateString('es-ES', { month: 'long' });
    }

    /**
     * Small helper: shared axis/grid styling for a Chart.js v2 config.
     */
    function baseScales(extra){
        return Object.assign({
            xAxes: [{ gridLines: { color: system.gridColor, zeroLineColor: system.axisColor }, ticks: { fontColor: system.axisColor } }],
            yAxes: [{ gridLines: { color: system.gridColor, zeroLineColor: system.axisColor }, ticks: { fontColor: system.axisColor, beginAtZero: true } }]
        }, extra || {});
    }

    async function fetchGraph(graph){
        const req = await fetch(`${system.path}/${graph}`, { method: 'POST', body: '' });
        return req.json();
    }

    /**
     * Load sales' graph (data and show) — trailing 12 months, single series.
     */
    async function showSalesGraph(){
        const graph = "showSalesGraph";
        const canvas = document.querySelector(`#${graph} canvas`);

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        // res is ordered Año DESC, Mes DESC — take the newest 12 and put them back in chronological order.
        const trailing = [...res].slice(0, 12).reverse();

        const dato = {
            labels: trailing.map(row => `${getMonthNameFromNumber(row['Mes'])} ${row['Año']}`),
            datasets: [{
                label: 'Ventas',
                data: trailing.map(row => row['Ventas']),
                borderColor: system.colorPalette[0],
                backgroundColor: system.colorPalette[0] + '33',
                pointBackgroundColor: system.colorPalette[0],
                borderWidth: 2,
                pointRadius: 3,
                fill: true
            }]
        };

        safeChart(canvas, {
            type: 'line',
            data: dato,
            options: {
                legend: { display: false },
                scales: baseScales()
            }
        });
    }

    /**
     * Load Top sold products/services graph — single series, one hue for every bar.
     */
    async function showTopSoldGraph(){
        const graph = "showTopSoldGraph";
        const canvas = document.querySelector(`#${graph} canvas`);

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        const dato = {
            labels: res.map(row => row.name),
            datasets: [{
                label: 'Unidades vendidas',
                data: res.map(row => row.units_sold),
                backgroundColor: system.colorPalette[0]
            }]
        };

        safeChart(canvas, {
            // Chart.js 2.1.4 (vendored) predates 'horizontalBar' (added 2.2.0) — vertical bar with angled labels instead.
            type: 'bar',
            data: dato,
            options: {
                legend: { display: false },
                scales: baseScales({
                    xAxes: [{ gridLines: { color: system.gridColor, zeroLineColor: system.axisColor }, ticks: { fontColor: system.axisColor, maxRotation: 60, minRotation: 40 } }]
                })
            }
        });
    }

    /**
     * Load Goals vs actual sales' graph (data and show)
     */
    async function showGoalsGraph(){
        const graph = "showGoalsGraph";
        const canvas = document.querySelector(`#${graph} canvas`);
        if( !canvas ) return; // admin-only graph, not rendered for workers

        const dato = {
            labels: [],
            datasets : [
                { label: 'Ventas', data: [], backgroundColor: system.colorPalette[0] },
                { label: 'Meta', data: [], backgroundColor: system.colorPalette[1] }
            ]
        };
        const grafico = safeChart(canvas, {
            type: 'bar',
            data : dato,
            options: {
                // Hand-rolled legend (see showPaymentMethodGraph) — this build's
                // native multi-dataset legend crashes the whole render loop,
                // taking every chart after it in the page down with it.
                legend: { display: false },
                scales: baseScales()
            }
        });

        renderLegend(document.querySelector(`#${graph} .graph_legend`), [
            { label: 'Ventas', color: system.colorPalette[0] },
            { label: 'Meta', color: system.colorPalette[1] }
        ]);

        const uniqueValue = (arr) => [...new Set(arr)];

        const applyFilter = (arr) => {
            const filter = arr.filter(g => g['Año'] === chart.showGoalsGraph.filter);
            const arrange = filter.reverse();

            dato.labels = arrange.map(g => getMonthNameFromNumber( g['Mes'] ));
            dato.datasets[0]['data'] = arrange.map(g => g['Ventas']);
            dato.datasets[1]['data'] = arrange.map(g => g['Meta']);

            if( grafico ) grafico.update();
        };

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        let years = [];
        res.forEach(g => years.push(g["Año"]));
        years = uniqueValue(years);
        chart[graph]['filter'] = years[0];

        const graphFilterBtn = document.querySelector(`#${graph} select`);
        graphFilterBtn.addEventListener("change", e => {
            const selected = e.target.selectedIndex;
            chart.showGoalsGraph.filter = years[selected];

            applyFilter([...chart.showGoalsGraph.source]);
        });

        for(let year of years){
            const option = document.createElement(`OPTION`);
            option.value = year;
            option.textContent = year;

            graphFilterBtn.append(option);
        }

        applyFilter([...chart.showGoalsGraph.source]);
    }

    /**
     * Revenue by payment method — trailing 12 months, stacked bar, one fixed
     * color slot per payment method (identity, never reassigned by rank).
     */
    async function showPaymentMethodGraph(){
        const graph = "showPaymentMethodGraph";
        const canvas = document.querySelector(`#${graph} canvas`);

        const methodMeta = {
            cash:     { label: 'Efectivo',       color: system.colorPalette[0] },
            pos:      { label: 'POS',            color: system.colorPalette[1] },
            transfer: { label: 'Transferencia',  color: system.colorPalette[2] },
            cupon:    { label: 'Cupón',          color: system.colorPalette[3] }
        };

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        const monthKey = row => `${row['Año']}-${String(row['Mes']).padStart(2,'0')}`;
        const months = [...new Set(res.map(monthKey))].sort();

        const datasets = Object.keys(methodMeta).map(method => {
            const rowsForMethod = res.filter(r => r['Metodo'] === method);
            const byMonth = Object.fromEntries(rowsForMethod.map(r => [monthKey(r), Number(r['Ingresos'])]));

            return {
                label: methodMeta[method].label,
                backgroundColor: methodMeta[method].color,
                borderColor: '#fcfcfb',
                borderWidth: 2,
                data: months.map(m => byMonth[m] || 0)
            };
        });

        const dato = {
            labels: months.map(m => {
                const [y, mo] = m.split('-');
                return `${getMonthNameFromNumber(mo)} ${y}`;
            }),
            datasets
        };

        safeChart(canvas, {
            type: 'bar',
            data: dato,
            options: {
                // Not stacked: the 'stacked' scale option shipped in Chart.js
                // 2.2.0, the same release as 'horizontalBar' — this vendored
                // build predates it (see showTopSoldGraph/
                // showSalesByEmployeeGraph above), so grouped bars are used
                // instead rather than risk the same kind of version gap.
                //
                // Legend built by hand below (see safeChart) rather than
                // `legend: {display:true}` — this build's built-in legend
                // crashes for any multi-dataset chart.
                legend: { display: false },
                scales: baseScales()
            }
        });

        renderLegend(document.querySelector(`#${graph} .graph_legend`), Object.values(methodMeta));
    }

    /**
     * Hand-rolled legend chips — avoids this Chart.js build's built-in legend
     * layout, which crashes once its labels wrap past a single row.
     */
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

    /**
     * Net revenue by employee — single series, one hue for every bar.
     */
    async function showSalesByEmployeeGraph(){
        const graph = "showSalesByEmployeeGraph";
        const canvas = document.querySelector(`#${graph} canvas`);
        if( !canvas ) return; // admin-only graph, not rendered for workers

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        const dato = {
            labels: res.map(row => row.name),
            datasets: [{
                label: 'Ventas',
                data: res.map(row => row['Ventas']),
                backgroundColor: system.colorPalette[0]
            }]
        };

        safeChart(canvas, {
            // Chart.js 2.1.4 (vendored) predates 'horizontalBar' (added 2.2.0) — vertical bar with angled labels instead.
            type: 'bar',
            data: dato,
            options: {
                legend: { display: false },
                scales: baseScales({
                    xAxes: [{ gridLines: { color: system.gridColor, zeroLineColor: system.axisColor }, ticks: { fontColor: system.axisColor, maxRotation: 60, minRotation: 40 } }]
                })
            }
        });
    }

    /**
     * New clients per month — trailing 12 months, single series.
     */
    async function showNewClientsGraph(){
        const graph = "showNewClientsGraph";
        const canvas = document.querySelector(`#${graph} canvas`);

        const res = await fetchGraph(graph);
        chart[graph]['source'] = res;

        // res is ordered Año DESC, Mes DESC — take the newest 12 and put them back in chronological order.
        const trailing = [...res].slice(0, 12).reverse();

        const dato = {
            labels: trailing.map(row => `${getMonthNameFromNumber(row['Mes'])} ${row['Año']}`),
            datasets: [{
                label: 'Clientes nuevos',
                data: trailing.map(row => row['Clientes']),
                backgroundColor: system.colorPalette[0]
            }]
        };

        safeChart(canvas, {
            type: 'bar',
            data: dato,
            options: {
                legend: { display: false },
                scales: baseScales()
            }
        });
    }

}())
