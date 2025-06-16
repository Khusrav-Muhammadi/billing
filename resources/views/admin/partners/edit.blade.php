@extends('layouts.app')

@section('title')
    Клиенты
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение партнера</h4>
        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>

        <form method="POST" action="{{ route('partner.update', $partner->id) }}">
            @csrf
            @method('PATCH')

            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name', $partner->name) }}">
                @error('name')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="phone">Телефон <span class="text-danger">*</span></label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" value="{{ old('phone', $partner->phone) }}" required>
                @error('phone')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email', $partner->email) }}">
                @error('email')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="partner_status_id">Статус партнёра</label>
                <select class="form-control form-control-sm @error('partner_status_id') is-invalid @enderror" name="partner_status_id">
                    @foreach($partnerStatuses as $status)
                        <option value="{{ $status->id }}" {{ old('partner_status_id', $partner->partner_status_id) == $status->id ? 'selected' : '' }}>{{ $status->name }}</option>
                    @endforeach
                </select>
                @error('partner_status_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="address">Адрес</label>
                <textarea name="address" cols="30" rows="10" class="form-control @error('address') is-invalid @enderror">{{ old('address', $partner->address) }}</textarea>
                @error('address')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary mr-2">Изменить</button>
        </form>
    </div>

    <!-- Отступ между формой и таблицей -->
    <div style="margin-top: 40px;"></div>

    <!-- Секция менеджеров -->
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Менеджеры партнера</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createManager">
                Добавить менеджера
            </button>
        </div>

        @if($managers && $managers->count() > 0)
            <table class="table table-striped table-hover">
                <thead class="table-light">
                <tr>
                    <th width="5%">№</th>
                    <th width="25%">ФИО</th>
                    <th width="20%">Телефон</th>
                    <th width="25%">Почта</th>
                    <th width="15%">Ссылка</th>
                    <th width="10%">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($managers as $manager)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $manager->name }}</td>
                        <td>{{ $manager->phone }}</td>
                        <td>{{ $manager->email }}</td>
                        <td>
                            <a href="https://shamcrm.com/?manager_id={{$manager->id}}" target="_blank" class="text-primary">
                                <small>shamcrm.com/?partner_id={{$manager->id}}</small>
                            </a>
                        </td>
                        <td>
                            <div class="btn-group-vertical btn-group-sm">
                                <a data-bs-toggle="modal" data-bs-target="#updateManager{{$manager->id}}" href="#" class="btn btn-outline-primary btn-sm mb-1">
                                    Изменить
                                </a>

                                <form method="POST" action="{{ route('partner.manager.delete', $manager->id) }}" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить этого менеджера?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        Удалить
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="updateManager{{$manager->id}}" tabindex="-1" aria-labelledby="createManagerLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('partner.manager.update', $manager->id) }}">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="createManagerLabel">Добавить менеджера</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group mb-3">
                                            <label for="manager_name">ФИО <span class="text-danger">*</span></label>
                                            <input  type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="manager_name" placeholder="Введите ФИО менеджера" value="{{$manager->name}}" required>
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_password">Пароль <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="manager_password" placeholder="Введите пароль" value="{{ old('password') }}" required>
                                            @error('password')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_phone">Телефон <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" id="manager_phone" placeholder="Введите телефон" value="{{$manager->phone}}" required>
                                            @error('phone')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-3">
                                            <label for="manager_email">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="manager_email" placeholder="Введите email" value="{{$manager->email}}" required>
                                            @error('email')
                                            <span class="text-danger">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
                </tbody>
            </table>
        @else
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> У этого партнера пока нет менеджеров.
            </div>
        @endif
    </div>

    <!-- Модальное окно для создания менеджера -->
    <div class="modal fade" id="createManager" tabindex="-1" aria-labelledby="createManagerLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('partner.manager.create', $partner->id) }}">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createManagerLabel">Добавить менеджера</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-3">
                            <label for="manager_name">ФИО <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" id="manager_name" placeholder="Введите ФИО менеджера" value="{{ old('name') }}" required>
                            @error('name')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_password">Пароль <span class="text-danger">*</span></label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" id="manager_password" placeholder="Введите пароль" value="{{ old('password') }}" required>
                            @error('password')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_phone">Телефон <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" name="phone" id="manager_phone" placeholder="Введите телефон" value="{{ old('phone') }}" required>
                            @error('phone')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="manager_email">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" id="manager_email" placeholder="Введите email" value="{{ old('email') }}" required>
                            @error('email')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <input type="hidden" name="partner_id" value="{{ $partner->id }}">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if($errors->any() && request()->has('create_manager'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('createManager'));
                modal.show();
            });
        </script>
    @endif

@endsection
