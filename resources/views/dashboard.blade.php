@extends('layouts.admin')

@section('title')
    Дашборд
@endsection

@php
    $money = static fn ($value) => '$' . number_format((float) $value, 2, '.', ' ');
    $kpis = [
        ['Активные клиенты', $cards['active_clients']],
        ['Чистый доход за месяц', $money($cards['month_income'])],
        ['Количество партнеров', $cards['partners']],
        ['Доход от партнеров', $money($cards['partner_income'])],
        ['Поступления за год', $money($cards['gross_income'])],
        ['Партнерские расходы', $money($cards['partner_expenses'])],
        ['Скидки', $money($cards['discount_expenses'])],
        ['Внедрение', $money($cards['implementation_income'])],
    ];
@endphp

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <p class="mb-0 text-sm text-ink-500">Показатели за {{ $dashboardYear }} год</p>
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-end gap-2">
            <div>
                <label for="dashboardYear" class="label">Год</label>
                <input id="dashboardYear" name="year" type="number" min="2020" max="{{ now()->year + 1 }}"
                       value="{{ $dashboardYear }}" class="input py-2" style="width: 110px;">
            </div>
            <button type="submit" class="btn btn-primary px-4 py-2">Показать</button>
        </form>
    </div>

    <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($kpis as [$label, $value])
            <div class="kpi">
                <p class="kpi-label mb-0">{{ $label }}</p>
                <p class="kpi-value">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="mb-5 grid gap-3 lg:grid-cols-2">
        <div class="card p-5">
            <p class="mb-4 text-sm font-semibold text-ink-900">Типы операций по оплатам</p>
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-full max-w-[220px]">
                    <canvas id="operationTypeChart" width="200" height="100"></canvas>
                </div>
                <div id="operationTypeLegend" class="min-w-[180px] flex-1 text-sm"></div>
            </div>
        </div>
        <div class="card p-5">
            <p class="mb-4 text-sm font-semibold text-ink-900">Партнеры по типу</p>
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-full max-w-[220px]">
                    <canvas id="partnersChart" width="200" height="100"></canvas>
                </div>
                <div id="partnersLegend" class="min-w-[180px] flex-1 text-sm"></div>
            </div>
        </div>
        <div class="card p-5">
            <div id="connectionStatusChart"></div>
        </div>
        <div class="card p-5">
            <div id="tariffRevenueChart"></div>
        </div>
    </div>

    <div class="card p-5">
        <div id="financialRegistryChart"></div>
    </div>
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script>
        const monthLabels = @json($monthLabels);
        const chartColors = ['#0b0efd', '#10b981', '#f59e0b', '#ef4444', '#6870ff', '#6b7088'];

        function renderLegend(containerId, labels, colors, values, amounts) {
            const container = document.getElementById(containerId);
            if (!container) return;

            container.innerHTML = '<ul style="margin:0;padding:0;list-style:none">' + labels.map(function (label, index) {
                const amountText = amounts ? ' / $' + Number(amounts[index] || 0).toLocaleString('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }) : '';
                return '<li style="display:flex;align-items:center;gap:8px;margin:6px 0;color:#272b46">'
                    + '<span style="width:8px;height:8px;border-radius:999px;flex:none;background:' + colors[index % colors.length] + '"></span>'
                    + '<span>' + label + ' — ' + (values[index] || 0) + amountText + '</span></li>';
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
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
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
            chartColors,
            @json($operationTypeAmounts)
        );

        renderDoughnut(
            'partnersChart',
            'partnersLegend',
            ['Partner', 'Agent'],
            [@json($activePartners), @json($inactivePartners)],
            ['#10b981', '#ef4444']
        );

        const chartBase = {
            chart: {
                backgroundColor: 'transparent',
                style: { fontFamily: 'Onest, sans-serif' }
            },
            credits: { enabled: false },
            colors: chartColors,
            title: { style: { color: '#1c1f36', fontSize: '14px', fontWeight: '600' } }
        };

        Highcharts.chart('connectionStatusChart', Highcharts.merge(chartBase, {
            chart: { type: 'column' },
            title: { text: 'Подключения и отключения клиентов' },
            xAxis: { categories: monthLabels, crosshair: true },
            yAxis: { min: 0, title: { text: 'Клиенты' } },
            tooltip: { valueSuffix: ' клиентов' },
            plotOptions: { column: { pointPadding: 0.2, borderWidth: 0 } },
            series: [
                { name: 'Подключены', data: @json($activeClientsByMonth) },
                { name: 'Отключены', data: @json($inactiveClientsByMonth) }
            ]
        }));

        Highcharts.chart('tariffRevenueChart', Highcharts.merge(chartBase, {
            title: { text: 'Доход по тарифам и услугам', align: 'left' },
            xAxis: { categories: monthLabels, crosshair: true },
            yAxis: { min: 0, title: { text: 'Сумма, USD' } },
            tooltip: { valuePrefix: '$', valueDecimals: 2 },
            series: @json($tariffRevenueChartData),
            responsive: {
                rules: [{
                    condition: { maxWidth: 500 },
                    chartOptions: {
                        legend: { layout: 'horizontal', align: 'center', verticalAlign: 'bottom' }
                    }
                }]
            }
        }));

        Highcharts.chart('financialRegistryChart', Highcharts.merge(chartBase, {
            title: { text: 'Финансы по реестрам', align: 'left' },
            xAxis: { categories: monthLabels, crosshair: true },
            yAxis: { min: 0, title: { text: 'Сумма, USD' } },
            tooltip: { shared: true, valuePrefix: '$', valueDecimals: 2 },
            series: @json($financialSeries)
        }));
    </script>
@endsection
