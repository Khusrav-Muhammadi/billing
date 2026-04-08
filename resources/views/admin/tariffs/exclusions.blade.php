@extends('layouts.app')

@section('title')
    Исключения услуги
@endsection

@section('content')
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h4 class="card-title mb-0">Услуга: {{ $tariff->name }}</h4>
                <div class="text-muted" style="font-size: 13px;">Организации, которые видят услугу даже после завершения периода действия</div>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('tariff.index') }}" class="btn btn-light">Назад</a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExclusionModal">
                    Добавить исключение
                </button>
                </div>
        </div>

        @if(isset($tariff->excludedOrganizations) && $tariff->excludedOrganizations->count() > 0)
            <div class="mb-2" style="max-width: 420px;">
                <input type="text" class="form-control" id="excludedSearch" placeholder="Поиск по организациям...">
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th>Организация</th>
                    <th style="width: 160px;">Действие</th>
                </tr>
                </thead>
                <tbody id="excludedOrganizationsBody">
                @forelse($tariff->excludedOrganizations as $organization)
                    <tr class="js-excluded-row">
                        <td>
                            {{ $organization->name }}
                            {{ $organization->order_number ? '(' . $organization->order_number . ')' : '' }}
                        </td>
                        <td>
                            <form action="{{ route('tariff.exclusions.destroy', [$tariff->id, $organization->id]) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Убрать исключение для этой организации?')">
                                    Удалить
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr class="js-empty-row">
                        <td colspan="2" class="text-muted">Исключений пока нет.</td>
                    </tr>
                @endforelse
                <tr id="excludedNoResultsRow" hidden>
                    <td colspan="2" class="text-muted">Ничего не найдено.</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addExclusionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('tariff.exclusions.store', $tariff->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Добавить исключение</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-2">
                            <label>Поиск</label>
                            <input type="text" class="form-control" id="organizationSearch" placeholder="Введите название или номер заказа...">
                        </div>
                        <div class="form-group">
                            <label>Организация <span class="text-danger">*</span></label>
                            <select class="form-control @error('organization_id') is-invalid @enderror" name="organization_id" id="organizationSelect" required>
                                <option value="">Выберите организацию</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">
                                        {{ $org->name }} {{ $org->order_number ? '(' . $org->order_number . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('organization_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        @if(isset($organizations) && $organizations->count() === 0)
                            <div class="alert alert-info mb-0 mt-2">
                                Все организации уже добавлены в исключения.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-primary" {{ (isset($organizations) && $organizations->count() === 0) ? 'disabled' : '' }}>
                            Добавить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const excludedSearch = document.getElementById('excludedSearch');
            const excludedBody = document.getElementById('excludedOrganizationsBody');
            const excludedNoResultsRow = document.getElementById('excludedNoResultsRow');

            if (excludedSearch && excludedBody && excludedNoResultsRow) {
                const rows = Array.from(excludedBody.querySelectorAll('tr.js-excluded-row'));

                const applyExcludedFilter = () => {
                    const term = (excludedSearch.value || '').toLowerCase().trim();
                    let visible = 0;

                    rows.forEach((row) => {
                        const text = (row.textContent || '').toLowerCase();
                        const match = term === '' || text.includes(term);
                        row.hidden = !match;
                        if (match) visible += 1;
                    });

                    excludedNoResultsRow.hidden = term === '' || visible > 0;
                };

                excludedSearch.addEventListener('input', applyExcludedFilter);
                applyExcludedFilter();
            }

            const modal = document.getElementById('addExclusionModal');
            const search = document.getElementById('organizationSearch');
            const select = document.getElementById('organizationSelect');

            if (search && select) {
                const applyOrgFilter = () => {
                    const term = (search.value || '').toLowerCase().trim();
                    Array.from(select.options).forEach((option) => {
                        if (!option.value) {
                            option.hidden = false;
                            return;
                        }
                        const text = (option.textContent || '').toLowerCase();
                        option.hidden = term !== '' && !text.includes(term);
                    });
                };

                search.addEventListener('input', applyOrgFilter);
                applyOrgFilter();

                if (modal) {
                    modal.addEventListener('shown.bs.modal', function () {
                        search.value = '';
                        applyOrgFilter();
                        search.focus();
                    });
                }
            }
        });
    </script>
@endsection
