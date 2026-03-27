@extends('layouts.app')

@section('title')
    Тарифы
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Услуги</h4>
        <div class="table-responsive">
            <a href="#" data-bs-toggle="modal" data-bs-target="#create" type="button" class="btn btn-primary">Добавить</a>
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Имя</th>
{{--                    <th>Цена</th>--}}
                    <th>Кол-во пользователей</th>
                    <th>Кол-во проектов</th>
                    <th>Тип</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @foreach($tariffs as $tariff)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $tariff->name }}</td>
{{--                        <td>{{ $tariff->price }} $</td>--}}
                        <td>{{ $tariff->user_count }}</td>
                        <td>{{ $tariff->project_count }}</td>
                        <td>
                            @if($tariff->is_extra_user)
                                Доп. пользователь (для тарифа #{{ $tariff->parent_tariff_id ?? '—' }})
                            @else
                                {{ $tariff->is_tariff ? 'Тариф' : 'Услуга' }}
                            @endif
                        </td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $tariff->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                            @if($tariff->is_tariff && !$tariff->is_extra_user)
                                <a href="{{ route('tariff.included_services.index', $tariff->id) }}"><i class="mdi mdi-eye" style="font-size: 30px"></i></a>
                            @endif
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $tariff->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $tariff->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.update', $tariff->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Изменение услуги</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label for="name">Наименование</label>
                                            <input type="text" class="form-control" name="name" value="{{ $tariff->name }}">
                                        </div>
{{--                                        <div class="form-group">--}}
{{--                                            <label for="name">Цена</label>--}}
{{--                                            <input type="number" class="form-control" name="price" value="{{ $tariff->price }}">--}}
{{--                                        </div>--}}
                                        <input type="hidden" name="price" value="{{ $tariff->price }}">
                        <div class="form-group">
                            <label for="name">Кол-во пользователей</label>
                            <input type="number" class="form-control" name="user_count" value="{{ $tariff->user_count }}">
                        </div>
                        <div class="form-group">
                            <label for="name">Кол-во проектов</label>
                            <input type="number" class="form-control" name="project_count" value="{{ $tariff->project_count }}">
                        </div>
                                        <div class="form-group">
                                            <label for="end_date">Дата завершения</label>
                                            <input type="date" class="form-control" name="end_date"
                                                   value="{{ optional($tariff->end_date)->format('Y-m-d') }}">
                                        </div>

                                        <div class="form-group">
                                            <label for="can_increase">Можно увеличивать количество</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox"
                                                   name="can_increase" value="1" {{ $tariff->can_increase ? 'checked' : '' }}
                                                   style="width: 20px; height: 20px">
                                        </div>

                                        <div class="form-group">
                                            <label for="name">Тариф</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox js-is-tariff"
                                                   name="is_tariff" value="1" {{ $tariff->is_tariff ? 'checked' : '' }}
                                                   style="width: 20px; height: 20px">
                                        </div>

                                        <div class="form-group">
                                            <label for="name">Это доп. пользователь</label>
                                            <input type="checkbox" class="form-check-inline custom-checkbox js-is-extra-user"
                                                   name="is_extra_user" value="1" {{ $tariff->is_extra_user ? 'checked' : '' }}
                                                   style="width: 20px; height: 20px">
                                        </div>

                                        <div class="form-group js-parent-tariff-wrap" style="{{ $tariff->is_extra_user ? '' : 'display:none;' }}">
                                            <label for="parent_tariff_id">Тариф для доп. пользователя</label>
                                            <select class="form-control" name="parent_tariff_id">
                                                <option value="">Выберите тариф</option>
                                                @foreach($baseTariffs as $baseTariff)
                                                    <option value="{{ $baseTariff->id }}" {{ (string)$tariff->parent_tariff_id === (string)$baseTariff->id ? 'selected' : '' }}>
                                                        {{ $baseTariff->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Изменить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="modal fade" id="delete{{ $tariff->id }}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.delete', $tariff->id) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="exampleModalLabel">Удаление услуги</h5>
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

    <div class="modal fade" id="create" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('tariff.store') }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Создание услуги</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="name">Название</label>
                            <input type="text" class="form-control" name="name" placeholder="Название">
                        </div>
{{--                        <div class="form-group">--}}
{{--                            <label for="name">Цена</label>--}}
{{--                            <input type="number" class="form-control" name="price" placeholder="Цена">--}}
{{--                        </div>--}}
                        <input type="hidden" name="price" value="0">
                        <div class="form-group">
                            <label for="name">Кол-во пользователей</label>
                            <input type="number" class="form-control" name="user_count" placeholder="Кол-во пользователей (необязательно)">
                        </div>
                        <div class="form-group">
                            <label for="name">Кол-во проектов</label>
                            <input type="number" class="form-control" name="project_count" placeholder="Кол-во проектов (необязательно)">
                        </div>
                        <div class="form-group">
                            <label for="end_date">Дата завершения</label>
                            <input type="date" class="form-control" name="end_date" value="{{ old('end_date') }}">
                        </div>

                        <div class="form-group">
                            <label for="can_increase">Можно увеличивать количество</label>
                            <input type="checkbox" class="form-check-inline custom-checkbox"
                                   name="can_increase" value="1" {{ old('can_increase') ? 'checked' : '' }}
                                   style="width: 20px; height: 20px">
                        </div>

                        <div class="form-group">
                            <label for="name">Тариф</label>
                            <input type="checkbox" class="form-check-inline custom-checkbox js-is-tariff"
                                   name="is_tariff" value="1"
                                   style="width: 20px; height: 20px">
                        </div>

                        <div class="form-group">
                            <label for="name">Это доп. пользователь</label>
                            <input type="checkbox" class="form-check-inline custom-checkbox js-is-extra-user"
                                   name="is_extra_user" value="1"
                                   style="width: 20px; height: 20px">
                        </div>

                        <div class="form-group js-parent-tariff-wrap" style="display:none;">
                            <label for="parent_tariff_id">Тариф для доп. пользователя</label>
                            <select class="form-control" name="parent_tariff_id">
                                <option value="">Выберите тариф</option>
                                @foreach($baseTariffs as $baseTariff)
                                    <option value="{{ $baseTariff->id }}">{{ $baseTariff->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sync = (root) => {
                const cb = root.querySelector('.js-is-extra-user');
                const wrap = root.querySelector('.js-parent-tariff-wrap');
                if (!cb || !wrap) return;
                wrap.style.display = cb.checked ? '' : 'none';

            };

            // Create modal
            const createModal = document.getElementById('create');
            if (createModal) {
                createModal.addEventListener('change', (e) => {
                    if (e.target && e.target.classList.contains('js-is-extra-user')) {
                        sync(createModal);
                    }
                });
                sync(createModal);
            }

            // Edit modals
            document.querySelectorAll('.modal[id^="edit"]').forEach((modal) => {
                modal.addEventListener('change', (e) => {
                    if (e.target && e.target.classList.contains('js-is-extra-user')) {
                        sync(modal);
                    }
                });
                sync(modal);
            });
        });
    </script>
@endsection
