@extends('layouts.app')

@section('title')
    Платежи
@endsection
@section('content')

    <div class="card-body">
        <h4 class="card-title">Платежи</h4>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Дата</th>
                    <th>Организация</th>
                    <th>Статус</th>
                    <th>Метод оплаты</th>
                    <th>Тип операции</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoices as $invoice)
                    <tr style="cursor: pointer" data-bs-toggle="modal" data-bs-target="#invoiceItem{{$invoice->id}}">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $invoice->created_at }}</td>
                        <td>{{ $invoice->organization?->name }}</td>
                        <td>
                            <span class="{{ $invoice->status == 'pending' ? 'text-warning' : 'text-success' }}">
                                {{ $invoice->status == 'pending' ? 'В ожидании' : 'Успешный' }}
                            </span>
                        </td>
                        <td>{{ $invoice->provider }}</td>
                        <td>
                            @switch($invoice->operation_type)
                                @case('demo_to_live')
                                Подключение тарифа
                                @break

                                @case('tariff_change')
                                Изменение тарифа
                                @break

                                @case('tariff_renewal')
                                Продление тарифа
                                @break
                                @case('add_organization')
                                Добавление организации
                                @break

                                @default

                            @endswitch
                        </td>
                    </tr>

                    <div class="modal fade" id="invoiceItem{{$invoice->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content d-flex flex-column" style="min-height: 100%;">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLabel">Данные о платеже</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="table-responsive">
                                        <div class="d-flex fw-bold border-bottom py-2">
                                            <div class="col-1">№</div>
                                            <div class="col-4">Название</div>
                                            <div class="col-3">Цена</div>
                                            <div class="col-4">Скидка</div>
                                        </div>

                                        @foreach($invoice->invoiceItems as $invoiceItem)
                                            <div class="d-flex border-bottom py-2 align-items-center">
                                                <div class="col-1">{{ $loop->iteration }}</div>
                                                <div class="col-4">{{ $invoiceItem->name }}</div>
                                                <div class="col-3">{{ $invoiceItem->price }}</div>
                                                <div class="col-4">{{ $invoiceItem->sale?->amount ?? '-' }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end p-3">
                                    <button type="button" class="btn btn-secondary mr-2" data-bs-dismiss="modal">Закрыть</button>
                                    @if($invoice->provider == 'INVOICE')
                                        <button type="button" class="btn btn-primary">Одобрить</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>



                @endforeach
                </tbody>
            </table>


        </div>
        <div class="pagination-wrapper">
            {{$invoices->links()}}
        </div>
    </div>
@endsection
@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterForm = document.getElementById('filterForm');
            const tableContainer = document.querySelector('.table-responsive');
            console.log(filterForm)
            const fetchClients = () => {
                const formData = new FormData(filterForm);

                fetch('{{ route('client.index') }}?' + new URLSearchParams(formData), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.text())
                    .then(html => {
                        tableContainer.innerHTML = html;
                    })
                    .catch(error => console.error('Ошибка загрузки:', error));
            };

            filterForm.querySelectorAll('select, input').forEach(element => {
                element.addEventListener('change', fetchClients);
            });

            document.addEventListener('click', event => {
                const paginationLink = event.target.closest('.pagination a');
                if (paginationLink) {
                    event.preventDefault();
                    const url = paginationLink.getAttribute('href');

                    fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(response => response.text())
                        .then(html => {
                            // Заменяем содержимое контейнера с таблицей
                            tableContainer.innerHTML = html;
                        })
                        .catch(error => console.error('Ошибка пагинации:', error));
                }
            });
        });


    </script>
@endsection
