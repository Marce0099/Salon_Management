(function(){

    const system = {
        path : window.location.pathname,
        colorPalette : [
            'rgb(255, 99, 132)',
            'rgb(255, 159, 64)',
            'rgb(255, 205, 86)',
            'rgb(75, 192, 192)',
            'rgb(54, 162, 235)',
            'rgb(153, 102, 255)',
            'rgb(201, 203, 207)',
            'rgb(23, 252, 15)',
            'rgb(135, 36, 242)',
            'rgb(237, 210, 208)',
            'rgb(45, 216, 133)',
            'rgb(239, 160, 189)',
        ]
    };
    const chart = {
        showSalesGraph : {filter: '', source : []},
        showGoalsGraph : {filter: '', source : []},
        showTopSoldGraph : {filter: '', source : []}
    };

    document.addEventListener("DOMContentLoaded", async e => {

        console.log('init_PanelChart');
        // Loaded sequentially: concurrent requests contend on the app's
        // DB-backed session lock (GET_LOCK) and can 500 under load.
        await showSalesGraph();
        await showGoalsGraph();
        await showTopSoldGraph();

    });

    /**
     * Obtener el nombre del mes en funcion a la fecha brindada
     * @param {string} dateS - Fecha a usar para extraer el nombre del mes
     * @returns {string}
     */
    function getMonthName(dateS){
        const [year, month, day] = dateS.split("-").map(Number); 
        const date = new Date(year, month - 1, day);
        return date.toLocaleDateString('es-ES', { month: 'long' });
    }

    /**
     * Load sales' graph (data and show)
     */
    async function showSalesGraph(){
        // Local variables
        const graph = "showSalesGraph";
        const sales = document.querySelector(`#${graph} canvas`);
        
        const dato = {
            labels: [],
            datasets : [{
                label: 'Ventas',
                data: [],
                backgroundColor : []
            }]
        };
        const grafico =  new Chart(sales, {
            type: 'bar',
            data : dato
        });

        // Local functions
        const uniqueValue = (arr) => [...new Set(arr)];

        const applyFilter = (arr) => {
            const filter = arr.filter(sale => sale['Año'] === chart.showSalesGraph.filter);
            const arrange = filter.reverse();

            const count = arrange.length
            const background = system.colorPalette.slice(0, count);

            dato.labels = arrange.map(sale => getMonthName( sale['Fecha'] ));
            dato.datasets[0]['data'] = arrange.map(sale => sale['Ventas']);
            dato.datasets[0]['backgroundColor'] = background

            grafico.update();
        };

        // Calling API
        const req = await fetch(`${system.path}/${graph}`, {
            method: 'POST',
            body : ''
        });
        const res = await req.json();
        chart[graph]['source'] = res;

        
        // Filling up select's options
        let years = [];
        res.forEach(sale => years.push(sale["Año"]));
        years = uniqueValue(years);
        chart[graph]['filter'] = years[0];
        
        const graphFilterBtn = document.querySelector(`#${graph} select`);
        graphFilterBtn.addEventListener("change", e => {
            const selected = e.target.selectedIndex;
            chart.showSalesGraph.filter = years[selected];

            applyFilter([...chart.showSalesGraph.source]);
        });

        for(let year of years){
            const option = document.createElement(`OPTION`);
            option.value = year;
            option.textContent = year;
           
            graphFilterBtn.append(option);
        }

        //Creating graph
        applyFilter([...chart.showSalesGraph.source]);
    }

    /**
     * Load Top sold products/services graph (data and show)
     */
    async function showTopSoldGraph(){
        // Local variables
        const graph = "showTopSoldGraph";
        const topSold = document.querySelector(`#${graph} canvas`).getContext("2d");

        const dato = {
            labels: [],
            datasets : [{
                label: 'Unidades vendidas',
                showInLegend: true,
                data: [],
                backgroundColor : []
            }]
        };

        const grafico =  new Chart(topSold, {
            type: 'pie',
            data : dato
        });

        // Calling API
        const req = await fetch(`${system.path}/${graph}`, {
            method: 'POST',
            body : ''
        });
        const res = await req.json();
        chart[graph]['source'] = res;

        //Creating graph
        dato.labels = chart[graph]['source'].map(freq => freq.name);
        dato.datasets[0].data = chart[graph]['source'].map(freq => freq.units_sold);

        const count = chart[graph]['source'].length;
        dato.datasets[0].backgroundColor = system.colorPalette.slice(0, count);

        grafico.update();
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
     * Load Goals vs actual sales' graph (data and show)
     */
    async function showGoalsGraph(){
        // Local variables
        const graph = "showGoalsGraph";
        const goals = document.querySelector(`#${graph} canvas`);

        const dato = {
            labels: [],
            datasets : [
                { label: 'Meta', data: [], backgroundColor: system.colorPalette[5] },
                { label: 'Ventas', data: [], backgroundColor: system.colorPalette[3] }
            ]
        };
        const grafico =  new Chart(goals, {
            type: 'bar',
            data : dato
        });

        // Local functions
        const uniqueValue = (arr) => [...new Set(arr)];

        const applyFilter = (arr) => {
            const filter = arr.filter(g => g['Año'] === chart.showGoalsGraph.filter);
            const arrange = filter.reverse();

            dato.labels = arrange.map(g => getMonthNameFromNumber( g['Mes'] ));
            dato.datasets[0]['data'] = arrange.map(g => g['Meta']);
            dato.datasets[1]['data'] = arrange.map(g => g['Ventas']);

            grafico.update();
        };

        // Calling API
        const req = await fetch(`${system.path}/${graph}`, {
            method: 'POST',
            body : ''
        });
        const res = await req.json();
        chart[graph]['source'] = res;

        // Filling up select's options
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

        //Creating graph
        applyFilter([...chart.showGoalsGraph.source]);
    }

}())