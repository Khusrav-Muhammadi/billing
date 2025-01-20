@extends('layouts.app')

@section('title')
    Клиенты
@endsection
@section('content')
    <form id="filterForm">
        <div class="row mb-3">
            <div class="col-md-2">
                <select name="demo" class="form-control">
                    <option value="">Тип подключения</option>
                    <option value="1">Демо</option>
                    <option value="0">Боевая</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">Статус</option>
                    <option value="1">Активный</option>
                    <option value="0">Неактивный</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="tariff" class="form-control">
                    <option value="">Тариф</option>
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="partner" class="form-control">
                    <option value="">Партнер</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="text" name="search" class="form-control" placeholder="Поиск">
            </div>
        </div>
    </form>

    <div class="card-body">
        <h4 class="card-title">Клиенты</h4>
        <a href="{{ route('client.create') }}" type="button" class="btn btn-primary">Создать</a>
        <div class="table-responsive">

            @include('admin.partials.clients', ['clients' => $clients])

        </div>
        <div class="pagination-wrapper">
            {{$clients->links()}}
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
