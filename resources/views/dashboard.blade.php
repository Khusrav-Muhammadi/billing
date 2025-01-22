@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')
    <div class="col-md-4">
        <div class="card border-0">
            <div class="card-body">
                <div class="card-title">Общее количество клиентов</div>
                <div class="d-flex flex-wrap">
                    <div class="doughnut-wrapper w-50">
                        <canvas id="doughnutChart3" width="100" height="100"></canvas>
                    </div>
                    <div id="doughnut-chart-legend3" class="pl-lg-3 rounded-legend align-self-center flex-grow legend-vertical legend-bottom-left"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
