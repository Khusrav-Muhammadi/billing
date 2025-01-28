@extends('layouts.app')

@section('title')
    Заявки партнёра
@endsection

@section('content')
    <div class="card-body">
        <h4 class="card-title">Заявки</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Партнер</th>
                    <th>Клиент</th>
                    <th>Телефон</th>
                    <th>E-mail</th>
                    <th>Тариф</th>
                    <th>Поддомен</th>
                    <th>Дата подачи</th>
                    <th>Статус</th>
                    <th>Адрес</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partnerRequests as $partner)
                    <tr data-bs-toggle="modal" data-bs-target="#edit-{{ $partner->id }}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $partner->partner->name }}</td>
                        <td>{{ $partner->name }}</td>
                        <td>{{ $partner->phone }}</td>
                        <td>{{ $partner->email }}</td>
                        <td>{{ $partner->tariff->name }}</td>
                        <td>{{ $partner->sub_domain }}</td>
                        <td>{{ $partner->date }}</td>
                        <td>{{ $partner->request_status }}</td>
                        <td>{{ $partner->address }}</td>
                    </tr>

                    <!-- Modal -->
                    <div class="modal fade" id="edit-{{ $partner->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner-request.approve', $partner->id) }}" method="POST">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Обработка запроса</h5>
                                    </div>
                                    <div class="modal-body">
                                        Что вы хотите делать с этим запросом?
                                    </div>
                                    <div class="modal-footer">
                                        <a href="{{ route('partner-request.reject', $partner->id) }}"><button type="button" class="btn btn-danger" data-bs-dismiss="modal">Отклонить</button></a>
                                        <button type="submit" class="btn btn-success">Одобрить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
