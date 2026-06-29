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
        showTopServicesGraph : {filter: '', source : []}
    };

    document.addEventListener("DOMContentLoaded", e => {

        console.log('init_PanelChart');
        showSalesGraph();
        showTopServicesGraph();
        
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
            dato.datasets[0]['data'] = arrange.map(sale => sale['Ganancias']);
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
     * Load Top services' graph (data and show)
     */
    async function showTopServicesGraph(){
        // Local variables
        const graph = "showTopServicesGraph";
        const topServices = document.querySelector(`#${graph} canvas`).getContext("2d");
       
        const dato = {
            labels: [],
            datasets : [{
                label: 'Servicio',
                showInLegend: true, 
                data: [],
                backgroundColor : []
            }]
        };

        const grafico =  new Chart(topServices, {
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
        dato.datasets[0].data = chart[graph]['source'].map(freq => freq.frequency);

        const count = chart[graph]['source'].length;
        dato.datasets[0].backgroundColor = system.colorPalette.slice(0, count);

        grafico.update();
    }
    
}())