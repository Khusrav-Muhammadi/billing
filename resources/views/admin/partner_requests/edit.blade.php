@extends('layouts.app')

@section('title')
    Клиенты
@endsection

@section('content')

    <div class="card-body">
        <h4 class="card-title">Изменение заявки</h4>

        <div class="d-flex align-items-center mb-3">
            <a href="{{ route('partner-request.index') }}" style="margin-right: 10px" class="btn btn-outline-secondary me-2">
                <i class="mdi mdi-tab-minus"></i> Назад
            </a>
            <a href="#" class="btn btn-danger me-2" style="margin-right: 10px" data-bs-toggle="modal" data-bs-target="#reject_cause{{ $partnerRequest->id }}">
                <i class="mdi mdi-trash-can"></i> Отклонить
            </a>
            <a href="{{ route('partner-request.approve', $partnerRequest->id) }}" class="btn btn-success">
                <i class="mdi mdi-pencil-box-outline"></i> Одобрить
            </a>
        </div>

        <div class="card p-4 shadow-sm">
            <form method="POST" action="{{ route('partner-request.update', $partnerRequest->id) }}">
                @csrf
                @method('PATCH')

                <div class="mb-3">
                    <label for="name" class="form-label">ФИО</label>
                    <input required type="text" class="form-control @error('name') is-invalid @enderror"
                           name="name" placeholder="Введите ФИО"
                           value="{{ old('name', $partnerRequest->name) }}">
                    @error('name')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Телефон</label>
                    <input required type="text" class="form-control @error('phone') is-invalid @enderror"
                           name="phone" placeholder="Введите телефон"
                           value="{{ old('phone', $partnerRequest->phone) }}">
                    @error('phone')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">E-mail</label>
                    <input required type="email" class="form-control @error('email') is-invalid @enderror"
                           name="email" placeholder="Введите email"
                           value="{{ old('email', $partnerRequest->email) }}">
                    @error('email')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Адрес</label>
                    <input required type="text" class="form-control @error('address') is-invalid @enderror"
                           name="address" placeholder="Введите адрес"
                           value="{{ old('address', $partnerRequest->address) }}">
                    @error('address')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="client_type" class="form-label">Тип клиента <span class="text-danger">*</span></label>
                    <select class="form-control @error('client_type') is-invalid @enderror"
                            name="client_type" required>
                        <option value="">Выберите тип клиента</option>
                        @foreach(\App\Enums\ClientType::cases() as $type)
                            <option value="{{ $type->value }}"
                                {{ old('client_type', $partnerRequest->client_type) == $type->value ? 'selected' : '' }}>
                                {{ $type->value }}
                            </option>
                        @endforeach
                    </select>
                    @error('client_type')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <input type="hidden" name="partner_id" value="{{ old('partner_id', $partnerRequest->partner_id) }}">

                <div class="mb-3">
                    <label for="sub_domain" class="form-label">Поддомен</label>
                    <input required type="text" class="form-control @error('sub_domain') is-invalid @enderror"
                           name="sub_domain" placeholder="Введите поддомен"
                           value="{{ old('sub_domain', $partnerRequest->sub_domain) }}">
                    @error('sub_domain')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="date" class="form-label">Дата подачи</label>
                    <input type="date" class="form-control @error('date') is-invalid @enderror"
                           name="date" value="{{ old('date', $partnerRequest->date) }}" disabled>
                    @error('date')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="tariff_id" class="form-label">Тарифы</label>
                    <select required class="form-control @error('tariff_id') is-invalid @enderror"
                            name="tariff_id">
                        <option value="1" {{ old('tariff_id', $partnerRequest->tariff_id) == 1 ? 'selected' : '' }}>Тариф стандарт</option>
                        <option value="2" {{ old('tariff_id', $partnerRequest->tariff_id) == 2 ? 'selected' : '' }}>Тариф Премиум</option>
                        <option value="3" {{ old('tariff_id', $partnerRequest->tariff_id) == 3 ? 'selected' : '' }}>Тариф PRO</option>
                    </select>
                    @error('tariff_id')
                    <span class="text-danger small">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Сохранить
                </button>
            </form>
        </div>
    </div>

    <div class="modal fade" id="reject_cause{{ $partnerRequest->id }}" tabindex="-1"
         aria-labelledby="exampleModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('partner-request.reject', $partnerRequest->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Причина отклонения</h5>
                    </div>
                    <div class="modal-body">
                        <textarea name="reject_cause" cols="30" rows="5" class="form-control"
                                  placeholder="Введите причину отказа">{{ old('reject_cause', $partnerRequest->reject_cause) }}</textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Отклонить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

@endsection

