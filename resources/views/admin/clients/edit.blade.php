@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение клиента</h4>

        <form method="POST" action="{{ route('client.update', $client->id) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input type="text" class="form-control" name="name" value="{{ $client->name }}">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" value="{{ $client->phone }}">
            </div>

            <div class="form-group">
                <label for="project_uuid">Id проекта</label>
                <input type="text" class="form-control" name="project_uuid" value="{{ $client->project_uuid }}">
            </div>

            <div class="form-group">
                <label for="sub_domain">Поддомен</label>
                <input type="text" class="form-control" name="sub_domain" value="{{ $client->sub_domain }}">
            </div>

            <div class="form-group">
                <label for="business_type_id">Тип бизнеса</label>
                <select class="form-control form-control-sm" name="business_type_id">
                    @foreach($businessTypes as $businessType)
                        <option value="{{ $businessType->id }}" {{ $businessType->id == $client->business_type_id ? 'selected' : '' }}>{{ $businessType->name }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

