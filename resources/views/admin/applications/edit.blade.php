@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Редактирование заметки</h4>

        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 10px">Назад</a>

        <form method="POST" action="{{ route('application.update', $application['id']) }}">
            @csrf
            @method('PATCH')
            <div class="form-group">
                <label for="name">ФИО</label>
                <input required type="text" value="{{$application['name']}}" class="form-control" name="name" placeholder="ФИО">
            </div>

            <div class="form-group">
                <label for="phone">Телефон</label>
                <input required type="text" value="{{$application['phone']}}" class="form-control" name="phone" placeholder="Телефон">
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input required type="email" value="{{$application['email']}}" class="form-control" name="email" placeholder="Ваш email">
            </div>

            <div class="form-group">
                <label for="email">Адрес</label>
                <input required type="text" value="{{$application['address']}}" class="form-control" name="address" placeholder="Адрес">
            </div>

            <div class="form-group">
                <label for="client_type">Тип клиента <span class="text-danger">*</span></label>
                <select  class="form-control form-control-sm"
                         name="client_type" required>
                    <option value="">Выберите тип клиента</option>
                    <option value="{{ \App\Enums\ClientType::LegalEntity->value }}" {{ (isset($application) && $application['client_type'] == \App\Enums\ClientType::LegalEntity->value) || old('client_type') == \App\Enums\ClientType::LegalEntity->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::LegalEntity->value }}
                    </option>
                    <option value="{{ \App\Enums\ClientType::Individual->value }}" {{ (isset($application) && $application['client_type'] == \App\Enums\ClientType::Individual->value) || old('client_type') == \App\Enums\ClientType::Individual->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::Individual->value }}
                    </option>
                    <option value="{{ \App\Enums\ClientType::Entrepreneur->value }}" {{ (isset($application) && $application['client_type'] == \App\Enums\ClientType::Entrepreneur->value) || old('client_type') == \App\Enums\ClientType::Entrepreneur->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::Entrepreneur->value }}
                    </option>
                </select>

            </div>

            <div class="form-group">
                <label for="email">Поддомен</label>
                <input value="{{\Illuminate\Support\Facades\Auth::id()}}" type="hidden" class="form-control" name="partner_id" >
                <input value="{{ $application['date'] }}" type="hidden" class="form-control" name="date" >
                <input required type="text" value="{{$application['sub_domain']}}" class="form-control" name="sub_domain" placeholder="Поддомен   ">
            </div>
            <div class="form-group">
                <label for="partner_status_id">Тарифы</label>
                <select required class="form-control form-control-sm" name="tariff_id">
                    <option {{ $application['tariff_id'] == 1 ? 'selected' : '' }} value="1">Тариф стандарт</option>
                    <option {{ $application['tariff_id'] == 2 ? 'selected' : '' }} value="2">Тариф Премиум</option>
                    <option {{ $application['tariff_id'] == 3 ? 'selected' : '' }} value="3">Тариф PRO</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mr-2"> Сохранить</button>

        </form>
    </div>

@endsection

