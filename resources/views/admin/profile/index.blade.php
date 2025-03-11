@extends('layouts.app')

@section('title')
    Профиль
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение профиля</h4>
        <form method="POST" action="{{ route('profile.update', $user->id) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" value="{{ $user->name }}">
                @if($errors->has('name')) <p
                    style="color: red;">{{ $errors->first('name') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="phone">Логин</label>
                <input type="text" class="form-control" name="login" value="{{ $user->login }}">
                @if($errors->has('login')) <p
                    style="color: red;">{{ $errors->first('login') }}</p> @endif
            </div>

            <div class="form-group">
                <label for="sub_domain">Почта</label>
                <input type="email" class="form-control" name="email" value="{{ $user->email }}">
                @if($errors->has('email')) <p
                    style="color: red;">{{ $errors->first('email') }}</p> @endif
            </div>
            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

