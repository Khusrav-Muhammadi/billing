@extends('layouts.app')

@section('title')
    Профиль
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif


    <div class="card-body">
        <h4 class="card-title">Изменение профиля</h4>
        <form method="POST" action="{{ route('profile.update', $user->id) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" value="{{ $user->name }}">
                @if($errors->has('name')) <p class="text-danger">{{ $errors->first('name') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="login">Логин</label>
                <input type="text" class="form-control" name="login" value="{{ $user->login }}">
                @if($errors->has('login')) <p class="text-danger">{{ $errors->first('login') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="email">Почта</label>
                <input type="email" class="form-control" name="email" value="{{ $user->email }}">
                @if($errors->has('email')) <p class="text-danger">{{ $errors->first('email') }}</p> @endif
            </div>

            <button type="submit" class="btn btn-primary mr-2">Изменить</button>
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#changePasswordModal">Изменить пароль</button>
        </form>
    </div>

    <!-- Модальное окно для изменения пароля -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Изменение пароля</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="{{ route('profile.changePassword', $user->id) }}">
                        @csrf
                        @method('PATCH')
                        <div class="form-group">
                            <label for="new_password">Новый пароль</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password_confirmation">Подтвердите новый пароль</label>
                            <input type="password" class="form-control" name="password_confirmation" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
