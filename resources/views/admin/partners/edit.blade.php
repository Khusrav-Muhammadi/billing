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
                <label for="login">Логин</label>
                <input type="text" class="form-control @error('login') is-invalid @enderror" name="login" value="{{ old('login', $partner->login) }}">
                @error('login')
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

            <button type="submit" class="btn btn-primary mr-2"> Изменить </button>

        </form>
    </div>

@endsection

