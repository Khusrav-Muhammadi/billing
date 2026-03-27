@extends('layouts.app')

@section('title')
    Включенные услуги
@endsection

@section('content')
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="card-title mb-0">Тариф: {{ $tariff->name }}</h4>
                <div class="text-muted" style="font-size: 13px;">Услуги, которые включены автоматически</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('tariff.index') }}" class="btn btn-light">Назад</a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    Добавить услугу
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>Услуга</th>
                    <th>Можно увеличивать</th>
                    <th style="width: 160px;">Кол-во</th>
                    <th style="width: 120px;">Действие</th>
                </tr>
                </thead>
                <tbody>
                @forelse($tariff->includedServices as $service)
                    <tr>
                        <td>{{ $service->name }}</td>
                        <td>{{ $service->can_increase ? 'Да' : 'Нет' }}</td>
                        <td>{{ (int) ($service->pivot?->quantity ?? 1) }}</td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $service->id }}">
                                <i class="mdi mdi-pencil-box-outline" style="font-size: 24px"></i>
                            </a>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $service->id }}">
                                <i style="color:red; font-size: 24px" class="mdi mdi-delete"></i>
                            </a>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit{{ $service->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.included_services.update', [$tariff->id, $service->id]) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Изменение услуги</h5>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-2"><strong>{{ $service->name }}</strong></div>
                                        <div class="form-group">
                                            <label>Количество</label>
                                            <input type="number"
                                                   class="form-control"
                                                   name="quantity"
                                                   min="1"
                                                   value="{{ (int) ($service->pivot?->quantity ?? 1) }}"
                                                   {{ $service->can_increase ? '' : 'disabled' }}>
                                            @if(!$service->can_increase)
                                                <small class="text-muted">Для этой услуги количество фиксировано (1).</small>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="modal fade" id="delete{{ $service->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('tariff.included_services.destroy', [$tariff->id, $service->id]) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Удалить услугу</h5>
                                    </div>
                                    <div class="modal-body">
                                        Убрать услугу <strong>{{ $service->name }}</strong> из тарифа?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                        <button type="submit" class="btn btn-danger">Удалить</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="4" class="text-muted">Пока нет включенных услуг.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addServiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('tariff.included_services.store', $tariff->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить услугу в тариф</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Услуга <span class="text-danger">*</span></label>
                            <select class="form-control" name="service_id" id="serviceSelect" required>
                                <option value="">Выберите услугу</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-can-increase="{{ $service->can_increase ? 1 : 0 }}">
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mt-2">
                            <label>Количество</label>
                            <input type="number" class="form-control" name="quantity" id="serviceQty" min="1" value="1" disabled>
                            <small class="text-muted" id="qtyHint">Количество доступно только для услуг с “Можно увеличивать количество”.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary">Добавить</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const select = document.getElementById('serviceSelect');
            const qty = document.getElementById('serviceQty');
            const hint = document.getElementById('qtyHint');
            if (!select || !qty) return;

            const sync = () => {
                const opt = select.options[select.selectedIndex];
                const canInc = opt && opt.dataset ? (opt.dataset.canIncrease === '1') : false;
                qty.disabled = !canInc;
                if (!canInc) {
                    qty.value = 1;
                }
                if (hint) {
                    hint.style.display = canInc ? 'none' : '';
                }
            };

            select.addEventListener('change', sync);
            sync();
        });
    </script>
@endsection

