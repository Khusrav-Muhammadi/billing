@extends('layouts.app')

@section('title')
    Клиенты
@endsection


@section('content')

    <div class="card-body">
        <h4 class="card-title">Создание клиента</h4>
        <a href="#" onclick="history.back();" class="btn btn-outline-danger" style="margin-bottom: 20px">Назад</a>

        <form method="POST" action="{{ route('client.store') }}">
            @csrf

            <div class="form-group">
                <label for="name">ФИО <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror"
                       name="name" placeholder="ФИО" value="{{ $partnerRequest->name ?? old('name') }}" required>
                @error('name')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="phone">Телефон <span class="text-danger">*</span></label>
                <input type="number" class="form-control @error('phone') is-invalid @enderror"
                       name="phone" placeholder="Телефон" value="{{ $partnerRequest->phone ?? old('phone') }}" required>
                @error('phone')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="email">Почта <span class="text-danger">*</span></label>
                <input type="email" class="form-control @error('email') is-invalid @enderror"
                       name="email" placeholder="Почта" value="{{ $partnerRequest->email ?? old('email') }}" required>
                @error('email')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="contact_person">Контактное лицо</label>
                <input type="text" class="form-control @error('contact_person') is-invalid @enderror"
                       name="contact_person" placeholder="Контактное лицо" value="{{ $partnerRequest->contact_person ?? old('contact_person') }}">
                @error('contact_person')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="sub_domain">Поддомен <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('sub_domain') is-invalid @enderror"
                       name="sub_domain" placeholder="Поддомен сайта" value="{{ $partnerRequest->sub_domain ?? old('sub_domain') }}" required>
                @error('sub_domain')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="partner_id">Партнер</label>
                <select class="form-control form-control-sm @error('business_type_id') is-invalid @enderror"
                        name="partner_id">
                    <option value="">Выберите партнера</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}" {{ (isset($partnerRequest) && $partnerRequest->partner_id == $partner->id) || old('partner_id') == $partner->id ? 'selected' : '' }}>
                            {{ $partner->name }}
                        </option>
                    @endforeach
                </select>
                @error('partner_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="client_type">Тип клиента <span class="text-danger">*</span></label>
                <select class="form-control form-control-sm @error('client_type') is-invalid @enderror"
                        name="client_type" required>
                    <option value="">Выберите тип клиента</option>
                    <option value="{{ \App\Enums\ClientType::LegalEntity->value }}" {{ (isset($partnerRequest) && $partnerRequest->client_type == \App\Enums\ClientType::LegalEntity->value) || old('client_type') == \App\Enums\ClientType::LegalEntity->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::LegalEntity->value }}
                    </option>
                    <option value="{{ \App\Enums\ClientType::Individual->value }}" {{ (isset($partnerRequest) && $partnerRequest->client_type == \App\Enums\ClientType::Individual->value) || old('client_type') == \App\Enums\ClientType::Individual->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::Individual->value }}
                    </option>
                    <option value="{{ \App\Enums\ClientType::Entrepreneur->value }}" {{ (isset($partnerRequest) && $partnerRequest->client_type == \App\Enums\ClientType::Entrepreneur->value) || old('client_type') == \App\Enums\ClientType::Entrepreneur->value ? 'selected' : '' }}>
                        {{ \App\Enums\ClientType::Entrepreneur->value }}
                    </option>
                </select>
                @error('client_type')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="tariff_id">Тариф <span class="text-danger">*</span></label>
                <select class="form-control form-control @error('tariff_id') is-invalid @enderror"
                        name="tariff_id" required>
                    <option value="">Выберите тариф</option>
                    @foreach($tariffs as $tariff)
                        <option value="{{ $tariff->id }}" {{ (isset($partnerRequest) && $partnerRequest->tariff_id == $tariff->id) || old('tariff_id') == $tariff->id ? 'selected' : '' }}>
                            {{ $tariff->name }}
                        </option>
                    @endforeach
                </select>
                @error('tariff_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="sale_id">Скидка</label>
                <select class="form-control form-control @error('sale_id') is-invalid @enderror"
                        name="sale_id">
                    <option value="">Выберите скидку</option>
                    @foreach($sales as $sale)
                        <option value="{{ $sale->id }}" {{ old('sale_id') == $sale->id ? 'selected' : '' }}>
                            {{ $sale->name }}
                        </option>
                    @endforeach
                </select>
                @error('sale_id')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group col-2" id="partner_checkbox_container" style="display: none; align-items: center;">
                <label for="partner_checkbox" style="margin-right: 10px;">НФР:</label>
                <input type="checkbox" name="nfr" class="form-control" style="width: 30px; margin: 0;">
            </div>

            <div class="form-group col-2" style="display: flex; align-items: center;">
                <label for="is_demo" style="margin-right: 10px;">Демо версия:</label>
                <input type="checkbox" name="is_demo" class="form-control @error('is_demo') is-invalid @enderror"
                       style="width: 30px; margin: 0;" {{ (isset($partnerRequest) && $partnerRequest->is_demo) || old('is_demo') ? 'checked' : '' }}>
                @error('is_demo')
                <span class="text-danger">{{ $message }}</span>
                @enderror
            </div>
            <input type="hidden" name="partner_request_id" value="{{ isset($partnerRequest) ? $partnerRequest->id : null }}">

            <button type="submit" class="btn btn-primary mr-2">Сохранить</button>
        </form>
    </div>

@endsection

<script>
    document.addEventListener("DOMContentLoaded", function () {
        let partnerSelect = document.querySelector("select[name='partner_id']");
        let partnerCheckboxContainer = document.getElementById("partner_checkbox_container");

        function togglePartnerCheckbox() {
            if (partnerSelect.value) {
                partnerCheckboxContainer.style.display = "flex";
            } else {
                partnerCheckboxContainer.style.display = "none";
            }
        }

        partnerSelect.addEventListener("change", togglePartnerCheckbox);
        togglePartnerCheckbox();
    });
</script>


