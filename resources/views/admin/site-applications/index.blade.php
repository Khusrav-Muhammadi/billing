@extends('layouts.app')

@section('title')
    Тарифы
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Заявки с сайта</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Регион</th>
                    <th>Организация</th>
                    <th>Тип обращение</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($applications as $application)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $application->fio }}</td>
                        <td>{{ $application->phone }}</td>
                        <td>{{ $application->email }}</td>
                        <td>{{ $application->region }}</td>
                        <td>{{ $application->organization }}</td>
                        <td>{{ $application->request_type }}</td>
                        <td>
                            <a href="{{route('site-application.delete', $application->id)}}">Удалить</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

@endsection
