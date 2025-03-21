@extends('layouts.app')

@section('title')
    Партнеры
@endsection


@section('content')

    <form id="filterForm">
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Поиск">
            </div>
        </div>
    </form>


    <div class="card-body">
        <h4 class="card-title">Партнеры</h4>
        <a href="{{ route('partner.create') }}" type="button" class="btn btn-primary">Создать</a>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>E-mail</th>
                    <th>Статус</th>
                    <th>Адрес</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($partners as $partner)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $partner->name }}</td>
                        <td>{{ $partner->phone }}</td>
                        <td>{{ $partner->email }}</td>
                        <td>{{ $partner->partnerStatus?->name }}</td>
                        <td>{{ $partner->address }}</td>
                        <td>

                            <a href="{{ route('partner.edit', $partner->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$partner->id}}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>
                    <div class="modal fade" id="deleteClient{{$partner->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('partner.delete', $partner->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление клиента</h5>
                                    </div>
                                    <div class="modal-body">
                                        Вы уверены что хотите удалить эти данные?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-danger">Удалить</button>
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
@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const filterForm = document.getElementById('filterForm');
            const tableContainer = document.querySelector('.table-responsive');
            console.log(filterForm)
            const fetchClients = () => {
                const formData = new FormData(filterForm);

                fetch('{{ route('partner.index') }}?' + new URLSearchParams(formData), {
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


