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
                    <th class="text-center">Партнер</th>
                    <th class="text-center">Клиент</th>
                    <th class="text-center">Тип клиента</th>
                    <th class="text-center">Телефон</th>
                    <th class="text-center">E-mail</th>
                    <th class="text-center">Тариф</th>
                    <th class="text-center">Поддомен</th>
                    <th class="text-center">Дата подачи</th>
                    <th class="text-center">Тип подключения</th>
                    <th class="text-center">Статус</th>
                    <th class="text-center">Адрес</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partnerRequests as $partner)
                    <tr @if ($partner->request_status != 'Успешный')
                        data-bs-toggle="modal" data-bs-target="#edit-{{ $partner->id }}"
                        @endif
                    >
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td class="text-center">{{ $partner->partner->name }}</td>
                        <td class="text-center">{{ $partner->name }}</td>
                        <td class="text-center">{{ $partner->client_type }}</td>
                        <td class="text-center">{{ $partner->phone }}</td>
                        <td class="text-center">{{ $partner->email }}</td>
                        <td class="text-center">{{ $partner->tariff->name }}</td>
                        <td class="text-center">{{ $partner->sub_domain }}</td>
                        <td class="text-center">{{ $partner->date }}</td>
                        <td class="text-center">
                            @if($partner->is_demo)
                                Демо версия
                            @else
                                Боевая версия
                            @endif
                        </td>
                        <td class="text-center" style="color: {{ $partner->request_status == 'Отклонён' ? 'red' : '' }}">{{ $partner->request_status }}
                            @if($partner->request_status == 'Отклонён') <a style="color: #0033c4; cursor: pointer"
                                                                           data-bs-toggle="modal"
                                                                           data-bs-target="#reject{{ $partner->id }}">(Причина)</a> @endif
                        </td>
                        <td class="text-center">{{ $partner->address }}</td>
                    </tr>

                    <div class="modal fade" id="edit-{{ $partner->id }}" tabindex="-1"
                         aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner-request.approve', $partner->id) }}" method="GET">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Обработка запроса</h5>
                                    </div>
                                    <div class="modal-body">
                                        Что вы хотите делать с этим запросом?
                                    </div>
                                    <div class="modal-footer">
                                        @if($partner->request_status != 'Отклонён')
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#reject_cause{{ $partner->id }}">
                                                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                                    Отклонить
                                                </button>
                                            </a>
                                        @endif
                                        <button type="submit" class="btn btn-success">Одобрить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


                    <div class="modal fade" id="reject_cause{{ $partner->id }}" tabindex="-1" aria-labelledby="exampleModalLabel"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner-request.reject', $partner->id) }}" method="POST">
                                @csrf
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Причина отклонения</h5>
                                    </div>
                                    <div class="modal-body">
                                        <textarea name="reject_cause" id="" cols="30" rows="5" class="form-control"
                                                  placeholder="Почему вы отклоняете этот запрос?"></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена
                                        </button>
                                        <button type="submit" class="btn btn-primary">Отправить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>


                    <div class="modal fade" id="reject{{ $partner->id }}" tabindex="-1" aria-labelledby="exampleModalLabel"
                         aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Причина отклонения</h5>
                                </div>
                                <div class="modal-body">
                                    <textarea name="reject_cause" id="" cols="30" rows="5" class="form-control">{{ $partner->reject_cause }}</textarea>
                                </div>
                            </div>
                        </div>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
