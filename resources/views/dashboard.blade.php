@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-title">Общее количество клиентов</div>
                    <div class="d-flex flex-wrap">
                        <div class="doughnut-wrapper w-50">
                            <canvas id="doughnutChart3" width="200" height="100"></canvas>
                        </div>
                        <div id="doughnut-chart-legend3"
                             class="pl-lg-3 rounded-legend align-self-center flex-grow legend-vertical legend-bottom-left"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <figure class="highcharts-figure">
                        <div id="container"></div>
                    </figure>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script>
        if ($("#doughnutChart3").length) {
            var ctx = document.getElementById('doughnutChart3').getContext("2d");
            const realClients = @json($clients->real_clients);
            const demoClients = @json($clients->demo_clients);

            var red2 = '#ff0d59';

            var green2 = '#00d284';

            var trafficChartData = {
                datasets: [{
                    data: [realClients, demoClients],
                    backgroundColor: [
                        green2,
                        red2
                    ],
                    hoverBackgroundColor: [
                        green2,
                        red2
                    ],
                    borderColor: [
                        green2,
                        red2
                    ],
                    legendColor: [
                        green2,
                        red2
                    ]
                }],

                labels: [
                    'Боевой',
                    'Демо'
                ]
            };
            var trafficChartOptions = {
                responsive: true,
                animation: {
                    animateScale: true,
                    animateRotate: true
                },
                legend: false,
                legendCallback: function (chart) {
                    var text = [];
                    text.push('<ul>');
                    for (var i = 0; i < trafficChartData.datasets[0].data.length; i++) {
                        text.push('<li><span class="legend-dots" style="background:' +
                            trafficChartData.datasets[0].legendColor[i] +
                            '"></span>');
                        if (trafficChartData.labels[i]) {
                            text.push(trafficChartData.labels[i]);
                        }
                        text.push('</li>');
                    }
                    text.push('</ul>');
                    return text.join('');
                }
            };
            var trafficChartCanvas = $("#doughnutChart3").get(0).getContext("2d");
            var trafficChart = new Chart(trafficChartCanvas, {
                type: 'doughnut',
                data: trafficChartData,
                options: trafficChartOptions
            });
        }

        var activeClients = @json($activeClientsByMonth);
        var inactiveClients = @json($inactiveClientsByMonth);

        Highcharts.chart('container', {
            chart: {
                type: 'column'
            },
            title: {
                text: 'Количество активных и неактивных клиентов'
            },
            xAxis: {
                categories: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                crosshair: true
            },
            yAxis: {
                title: {
                    text: 'Количество клиентов'
                }
            },
            tooltip: {
                valueSuffix: ' клиентов'
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0
                }
            },
            series: [
                {
                    name: 'Активные',
                    data: activeClients
                },
                {
                    name: 'Неактивные',
                    data: inactiveClients
                }
            ]

        });




        option = {
            title: {
                text: 'Stacked Line'
            },
            tooltip: {
                trigger: 'axis'
            },
            legend: {
                data: ['Email', 'Union Ads', 'Video Ads', 'Direct', 'Search Engine']
            },
            grid: {
                left: '3%',
                right: '4%',
                bottom: '3%',
                containLabel: true
            },
            toolbox: {
                feature: {
                    saveAsImage: {}
                }
            },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
            },
            yAxis: {
                type: 'value'
            },
            series: [
                {
                    name: 'Email',
                    type: 'line',
                    stack: 'Total',
                    data: [120, 132, 101, 134, 90, 230, 210]
                },
                {
                    name: 'Union Ads',
                    type: 'line',
                    stack: 'Total',
                    data: [220, 182, 191, 234, 290, 330, 310]
                },
                {
                    name: 'Video Ads',
                    type: 'line',
                    stack: 'Total',
                    data: [150, 232, 201, 154, 190, 330, 410]
                },
                {
                    name: 'Direct',
                    type: 'line',
                    stack: 'Total',
                    data: [320, 332, 301, 334, 390, 330, 320]
                },
                {
                    name: 'Search Engine',
                    type: 'line',
                    stack: 'Total',
                    data: [820, 932, 901, 934, 1290, 1330, 1320]
                }
            ]
        };

    </script>
@endsection
