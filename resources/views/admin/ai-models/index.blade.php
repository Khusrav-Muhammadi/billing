@extends('layouts.app')

@section('title')
    ИИ — Модели
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="card-body">
    <h4 class="card-title">ИИ — Модели</h4>
    <div class="table-responsive">
        <a href="#" data-bs-toggle="modal" data-bs-target="#createModal" type="button" class="btn btn-primary">Добавить</a>
        <table class="table table-hover">
            <thead>
            <tr>
                <th>№</th>
                <th>Название</th>
                <th>Провайдер</th>
                <th>Цена вход / 1M токенов</th>
                <th>Цена выход / 1M токенов</th>
                <th>Статус</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @foreach($models as $model)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $model->name }}</td>
                    <td>{{ $providers[$model->provider] ?? $model->provider }}</td>
                    <td>{{ number_format($model->cost_per_1m_input, 4) }}</td>
                    <td>{{ number_format($model->cost_per_1m_output, 4) }}</td>
                    <td>
                        @if($model->is_active)
                            <span class="badge bg-success">Активна</span>
                        @else
                            <span class="badge bg-secondary">Отключена</span>
                        @endif
                    </td>
                    <td>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#edit{{ $model->id }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#delete{{ $model->id }}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
                    </td>
                </tr>

                {{-- Модал редактирования --}}
                <div class="modal fade" id="edit{{ $model->id }}" tabindex="-1" aria-labelledby="editLabel{{ $model->id }}" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-model.update', $model) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editLabel{{ $model->id }}">Изменение модели</h5>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label>Название <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" value="{{ $model->name }}" required maxlength="100">
                                    </div>
                                    <div class="form-group">
                                        <label>Провайдер <span class="text-danger">*</span></label>
                                        <select class="form-control" name="provider" required>
                                            @foreach($providers as $key => $label)
                                                <option value="{{ $key }}" {{ $model->provider === $key ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Цена вход / 1M токенов <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control" name="cost_per_1m_input" value="{{ $model->cost_per_1m_input }}" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Цена выход / 1M токенов <span class="text-danger">*</span></label>
                                        <input type="text" inputmode="decimal" class="form-control" name="cost_per_1m_output" value="{{ $model->cost_per_1m_output }}" required>
                                    </div>
                                    <div class="form-group">
                                        <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1" id="editActive{{ $model->id }}" {{ $model->is_active ? 'checked' : '' }} style="width: 20px; height: 20px">
                                        <label for="editActive{{ $model->id }}" class="ms-1">Активна</label>
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
                <div class="modal fade" id="delete{{ $model->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('ai-model.destroy', $model) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Удаление модели</h5>
                                </div>
                                <div class="modal-body">
                                    Вы уверены что хотите удалить модель <strong>{{ $model->name }}</strong>?
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-danger">Удалить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Модал создания --}}
<div class="modal fade" id="createModal" tabindex="-1" aria-labelledby="createLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('ai-model.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createLabel">Добавить ИИ модель</h5>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Название <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               name="name" value="{{ old('name') }}" required maxlength="100"
                               placeholder="например: gpt-4o, deepseek-v3">
                        @error('name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Провайдер <span class="text-danger">*</span></label>
                        <select class="form-control @error('provider') is-invalid @enderror" name="provider" required>
                            <option value="">Выберите провайдера</option>
                            @foreach($providers as $key => $label)
                                <option value="{{ $key }}" {{ old('provider') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('provider')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Цена вход / 1M токенов <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control @error('cost_per_1m_input') is-invalid @enderror"
                               name="cost_per_1m_input" value="{{ old('cost_per_1m_input', '0') }}" required>
                        @error('cost_per_1m_input')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>Цена выход / 1M токенов <span class="text-danger">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control @error('cost_per_1m_output') is-invalid @enderror"
                               name="cost_per_1m_output" value="{{ old('cost_per_1m_output', '0') }}" required>
                        @error('cost_per_1m_output')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <input type="checkbox" class="form-check-inline custom-checkbox" name="is_active" value="1" id="createIsActive" checked style="width: 20px; height: 20px">
                        <label for="createIsActive" class="ms-1">Активна</label>
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

@endsection
