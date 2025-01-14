@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание партнера</h4>

        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>

        <form method="POST" action="{{ route('partner.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" placeholder="ФИО">
            </div>


            <div class="form-group">
                <label for="company">Компания</label>
                <input type="text" class="form-control" name="company" placeholder="Название компании">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" placeholder="Телефон">
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" class="form-control" name="email" placeholder="Ваш email">
            </div>

            <div class="form-group">
                <label for="business_type_id">Менеджеры</label>
                <select class="form-control form-control-sm" name="manager_id">
                    @foreach($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="email">Адрес</label>
                <textarea name="address" cols="30" rows="10" class="form-control"></textarea>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Сохранить </button>

        </form>
    </div>

@endsection

