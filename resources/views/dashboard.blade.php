@extends('layouts.app')

@section('title')
    Дашбоард
@endsection

@php
    $money = static fn ($value) => '$' . number_format((float) $value, 2, '.', ' ');
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Дашбоард за {{ $dashboardYear }}</h4>
        <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center" style="gap: 8px;">
            <label for="dashboardYear" class="mb-0">Год</label>
            <input id="dashboardYear" name="year" type="number" min="2020" max="{{ now()->year + 1 }}"
                   value="{{ $dashboardYear }}" class="form-control" style="width: 110px;">
            <button type="submit" class="btn btn-primary btn-sm">Показать</button>
        </form>
    </div>




    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Активные клиенты</strong></div>
                    <div class="card-text">{{ $cards['active_clients'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Чистый доход за месяц</strong></div>
                    <div class="card-text">{{ $money($cards['month_income']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Количество партнеров</strong></div>
                    <div class="card-text">{{ $cards['partners'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Доход от партнеров</strong></div>
                    <div class="card-text">{{ $money($cards['partner_income']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Поступления за год</strong></div>
                    <div class="card-text">{{ $money($cards['gross_income']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Партнерские расходы</strong></div>
                    <div class="card-text">{{ $money($cards['partner_expenses']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Скидки</strong></div>
                    <div class="card-text">{{ $money($cards['discount_expenses']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100">
                <div class="card-body">
                    <div class="card-text"><strong>Внедрение</strong></div>
                    <div class="card-text">{{ $money($cards['implementation_income']) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" style="margin-top: 15px">
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-text"><strong>Типы операций по оплатам</strong></div>
                    <div class="d-flex flex-wrap">
                        <div class="doughnut-wrapper w-50">
                            <canvas id="operationTypeChart" width="200" height="100"></canvas>
                        </div>
                        <div id="operationTypeLegend"
                             class="pl-lg-3 rounded-legend align-self-center flex-grow legend-vertical legend-bottom-left"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <div class="card-title">Партнеры по типу</div>
                    <div class="d-flex flex-wrap">
                        <div class="doughnut-wrapper w-50">
                            <canvas id="partnersChart" width="200" height="100"></canvas>
                        </div>
                        <div id="partnersLegend"
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
                        <div id="connectionStatusChart"></div>
                    </figure>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0">
                <div class="card-body">
                    <figure class="highcharts-figure">
                        <div id="tariffRevenueChart"></div>
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
        const monthLabels = @json($monthLabels);

        function renderLegend(containerId, labels, colors, values, amounts) {
            const container = document.getElementById(containerId);
            if (!container) return;

            container.innerHTML = '<ul>' + labels.map(function (label, index) {
                const amountText = amounts ? ' / $' + Number(amounts[index] || 0).toLocaleString('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) : '';
                return '<li><span class="legend-dots" style="background:' + colors[index % colors.length] + '"></span>'
                    + label + ' - ' + (values[index] || 0) + amountText + '</li>';
            }).join('') + '</ul>';
        }

        function renderDoughnut(canvasId, legendId, labels, data, colors, amounts) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            new Chart(canvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        hoverBackgroundColor: colors,
                        borderColor: colors
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            renderLegend(legendId, labels, colors, data, amounts);
        }

        renderDoughnut(
            'operationTypeChart',
            'operationTypeLegend',
            @json($operationTypeLabels),
            @json($operationTypeData),
            ['#1326f8', '#ffd70d', '#119b18', '#c20920', '#6f42c1'],
            @json($operationTypeAmounts)
        );

        renderDoughnut(
            'partnersChart',
            'partnersLegend',
            ['Partner', 'Agent'],
            [@json($activePartners), @json($inactivePartners)],
            ['#119b18', '#c20920']
        );

        Highcharts.chart('connectionStatusChart', {
            chart: {
                type: 'column'
            },
            title: {
                text: 'Подключения и отключения клиентов'
            },
            xAxis: {
                categories: monthLabels,
                crosshair: true
            },
            yAxis: {
                min: 0,
                title: {
                    text: 'Клиенты'
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
                    name: 'Подключены',
                    data: @json($activeClientsByMonth)
                },
                {
                    name: 'Отключены',
                    data: @json($inactiveClientsByMonth)
                }
            ]
        });

        Highcharts.chart('tariffRevenueChart', {
            title: {
                text: 'Доход по тарифам и услугам',
                align: 'left'
            },
            xAxis: {
                categories: monthLabels,
                crosshair: true
            },
            yAxis: {
                min: 0,
                title: {
                    text: 'Сумма, USD'
                }
            },
            tooltip: {
                valuePrefix: '$',
                valueDecimals: 2
            },
            series: @json($tariffRevenueChartData),
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

        Highcharts.chart('financialRegistryChart', {
            title: {
                text: 'Финансы по реестрам',
                align: 'left'
            },
            xAxis: {
                categories: monthLabels,
                crosshair: true
            },
            yAxis: {
                min: 0,
                title: {
                    text: 'Сумма, USD'
                }
            },
            tooltip: {
                shared: true,
                valuePrefix: '$',
                valueDecimals: 2
            },
            series: @json($financialSeries)
        });
    </script>
@endsection
