@extends('layouts.app')

@section('title') ИИ — Тарифные планы @endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card-body">
    <h4 class="card-title">ИИ — Тарифные планы</h4>
    <div class="table-responsive">
        <a href="#" data-bs-toggle="modal" data-bs-target="#createPlanModal" type="button" class="btn btn-primary">Добавить</a>
        <table class="table table-hover">
            <thead>
            <tr>
                <th>№</th>
                <th>Название</th>
                <th>Модель</th>
                <th>Активные периоды</th>
                <th>Статус</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $plan->name }}</td>
                    <td>
                        @if($plan->aiModel)
                            <span class="badge bg-light text-dark border">{{ $plan->aiModel->name }}</span>
                            <small class="text-muted">{{ \App\Models\Ai\AiModel::$providers[$plan->aiModel->provider] ?? $plan->aiModel->provider }}</small>
                        @elseif($plan->model)
                            <code>{{ $plan->model }}</code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @foreach($plan->activePeriods as $period)
                            <span class="badge bg-secondary">
                                {{ $period->months }} мес.
                                @if($period->discount_percent > 0)
                                    <small>−{{ $period->discount_percent }}%</small>
                                @endif
                            </span>
                        @endforeach
                        @if($plan->activePeriods->isEmpty())
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($plan->is_active)
                            <span class="badge bg-success">Активен</span>
                        @else
                            <span class="badge bg-secondary">Неактивен</span>
                        @endif
                    </td>
                    <td style="white-space: nowrap;">
                        <a href="{{ route('ai-tariff.prices.index', $plan) }}" title="Цены"><i class="mdi mdi-currency-usd" style="font-size: 30px"></i></a>
                        <a href="{{ route('ai-tariff.periods.index', $plan) }}" title="Периоды / скидки"><i class="mdi mdi-calendar-clock" style="font-size: 30px"></i></a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editPlan{{ $plan->id }}" title="Изменить"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#deletePlan{{ $plan->id }}" title="Удалить"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                    </td>
                </tr>

                {{-- Модал редактирования --}}
                <div class="modal fade" id="editPlan{{ $plan->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-tariff.update', $plan) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Изменение тарифного плана</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Название <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" value="{{ $plan->name }}" required maxlength="100">
                                    </div>
                                    <div class="form-group">
                                        <label>Модель ИИ</label>
                                        <select class="form-control" name="ai_model_id">
                                            <option value="">— не выбрана —</option>
                                            @foreach($aiModels->groupBy('provider') as $prov => $mods)
                                                <optgroup label="{{ \App\Models\Ai\AiModel::$providers[$prov] ?? $prov }}">
                                                    @foreach($mods as $m)
                                                        <option value="{{ $m->id }}" {{ $plan->ai_model_id == $m->id ? 'selected' : '' }}>
                                                            {{ $m->name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1"
                                               id="editActive{{ $plan->id }}" {{ $plan->is_active ? 'checked' : '' }}
                                               style="width: 20px; height: 20px">
                                        <label for="editActive{{ $plan->id }}" class="ms-1">Активен</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-primary">Изменить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Модал удаления --}}
                <div class="modal fade" id="deletePlan{{ $plan->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-tariff.destroy', $plan) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Удаление тарифного плана</h5>
                                </div>
                                <div class="modal-body">
                                    Вы уверены что хотите удалить тарифный план <strong>{{ $plan->name }}</strong>?
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
                    <td colspan="6" class="text-center text-muted py-4">Нет тарифных планов</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Модал создания --}}
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-labelledby="createPlanLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('ai-tariff.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createPlanLabel">Создать тарифный план</h5>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" required maxlength="100">
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Модель ИИ</label>
                        @if($aiModels->isNotEmpty())
                            <select class="form-control @error('ai_model_id') is-invalid @enderror" name="ai_model_id">
                                <option value="">— не выбрана —</option>
                                @foreach($aiModels->groupBy('provider') as $prov => $mods)
                                    <optgroup label="{{ \App\Models\Ai\AiModel::$providers[$prov] ?? $prov }}">
                                        @foreach($mods as $m)
                                            <option value="{{ $m->id }}" {{ old('ai_model_id') == $m->id ? 'selected' : '' }}>
                                                {{ $m->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        @else
                            <div class="form-control-plaintext text-warning" style="font-size: 13px;">
                                Сначала добавьте модели в разделе
                                <a href="{{ route('ai-model.index') }}" target="_blank">ИИ-Модели</a>
                            </div>
                        @endif
                        @error('ai_model_id')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1"
                               id="createIsActive" checked style="width: 20px; height: 20px">
                        <label for="createIsActive" class="ms-1">Активен</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
