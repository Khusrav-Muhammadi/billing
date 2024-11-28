@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание клиента</h4>

        <form method="POST" action="{{ route('client.store') }}">
            @csrf
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" placeholder="ФИО">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" placeholder="Телефон">
            </div>

            <div class="form-group">
                <label for="project_uuid">Id проекта</label>
                <input type="text" class="form-control" name="project_uuid" placeholder="id проекта">
            </div>

            <div class="form-group">
                <label for="sub_domain">Поддомен</label>
                <input type="text" class="form-control" name="sub_domain" placeholder="Поддомен">
            </div>

            <div class="form-group">
                <label for="business_type_id">Тип бизнеса</label>
                <select class="form-control form-control-sm" name="business_type_id">
                    @foreach($businessTypes as $businessType)
                        <option value="{{ $businessType->id }}">{{ $businessType->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Сохранить </button>

        </form>
    </div>

@endsection

