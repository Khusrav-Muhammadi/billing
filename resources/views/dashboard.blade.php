@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')
    <div class="row">
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Количество клиентов</strong></div>
                    <div class="card-text">{{$clients_count}}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Доход за месяц</strong></div>
                    <div class="card-text">{{$totalIncomeForMonth}} $</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Количество партнеров</strong></div>
                    <div class="card-text">{{$partners}}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Доход от партнеров</strong></div>
                    <div class="card-text">{{$totalIncomeFromPartners}} $</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 15px">
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Тип подключения клиентов</strong></div>
                    <div class="d-flex flex-wrap">
                        <div class="doughnut-wrapper w-50">
                            <canvas id="doughnutt" width="200" height="100"></canvas>
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
                    <div class="card-title">Партнеры</div>
                    <div class="d-flex flex-wrap">
                        <div class="doughnut-wrapper w-50">
                            <canvas id="doughnutChart5" width="200" height="100"></canvas>
                        </div>
                        <div id="doughnut-chart-legend3"
                             class="pl-lg-3 rounded-legend align-self-center flex-grow legend-vertical legend-bottom-left"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row" style="margin-top: 15px">
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <figure class="highcharts-figure">
                        <div id="container"></div>
                    </figure>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <figure class="highcharts-figure">
                        <div id="line-chart"></div>
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
        if ($("#doughnutt").length) {
            var ctx = document.getElementById('doughnutt').getContext("2d");
            const realClients = @json($clients->real_clients);
            const demoClients = @json($clients->demo_clients);

            var red2 = '#ffd70d';

            var green2 = '#1326f8';

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

                    text.push('</ul>');
                    return text.join('');
                }
            };
            var trafficChartCanvas = $("#doughnutt").get(0).getContext("2d");
            var trafficChart = new Chart(trafficChartCanvas, {
                type: 'doughnut',
                data: trafficChartData,
                options: trafficChartOptions
            });
        }

        var activeClients = @json($activeClientsByMonth);
        var inactiveClients = @json($inactiveClientsByMonth);
        var chartData = @json($chartData);

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
                    text: ''
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
        //line chart

        Highcharts.chart('line-chart', {
            title: {
                text: 'Доход по тарифам',
                align: 'left'
            },

            legend: {
                layout: 'vertical',
                align: 'right',
                verticalAlign: 'middle'
            },

            xAxis: {
                categories: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                crosshair: true
            },

            yAxis: {
                title: {
                    text: ''
                }
            },
            series: chartData,

            responsive: {
                rules: [{
                    condition: {
                        maxWidth: 500
                    },
                    chartOptions: {
                        legend: {
                            layout: 'horizontal',
                            align: 'center',
                            verticalAlign: 'bottom'
                        }
                    }
                }]
            }
        });

        if ($("#doughnutChart5").length) {
            var ctx = document.getElementById('doughnutChart5').getContext("2d");
            const activePartners = @json($activePartners);
            const inactivePartners = @json($inactivePartners);

            var red2 = '#c20920';

            var green2 = '#119b18';

            var trafficChartData = {
                datasets: [{
                    data: [activePartners, inactivePartners],
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
                    'Активные',
                    'Не активные'
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
            var trafficChartCanvas = $("#doughnutChart5").get(0).getContext("2d");
            var trafficChart = new Chart(trafficChartCanvas, {
                type: 'doughnut',
                data: trafficChartData,
                options: trafficChartOptions
            });
        }


    </script>
@endsection
