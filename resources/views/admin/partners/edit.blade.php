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
                <input type="text" class="form-control" name="name" value="{{ $partner->name }}">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input type="text" class="form-control" name="phone" value="{{ $partner->phone }}">
            </div>

            <div class="form-group">
                <label for="email">Почта</label>
                <input type="email" class="form-control" name="email" value="{{ $partner->email }}">
            </div>

            <div class="form-group">
                <label for="partner_status_id">Статус партнёра</label>
                <select class="form-control form-control-sm" name="partner_status_id">
                    @foreach($partnerStatuses as $status)
                        <option value="{{ $status->id }}" {{ $status->id == $partner->partner_status_id ? 'selected' : '' }}>{{ $status->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="address">Адрес</label>
                <textarea name="address" cols="30" rows="10" class="form-control">{{ $partner->address }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

