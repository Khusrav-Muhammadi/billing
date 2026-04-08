@extends('layouts.app')

@section('title')
    Закрытие дня
@endsection

@section('content')
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="card-title mb-0">Закрытие дня</h4>
            <a href="{{ route('day-closing.create') }}" class="btn btn-primary">Добавить</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>Документ</th>
                    <th>Дата</th>
                    <th>Автор</th>
                    <th>Кол-во организаций</th>
                    <th>Статус</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody>
                @forelse($dayClosings as $dayClosing)
                    <tr>
                        <td>{{ $loop->iteration + ($dayClosings->currentPage() - 1) * $dayClosings->perPage() }}</td>
                        <td>{{ $dayClosing->doc_number }}</td>
                        <td>{{ optional($dayClosing->date)->format('d.m.Y') }}</td>
                        <td>{{ $dayClosing->author?->name ?? '-' }}</td>
                        <td>{{ (int) $dayClosing->client_amount }}</td>
                        <td>
                            <span class="badge {{ $dayClosing->status ? 'badge-success' : 'badge-danger' }}">
                                {{ $dayClosing->status ? 'Успешно' : 'Есть нехватка баланса' }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('day-closing.show', $dayClosing->id) }}" title="Просмотр">
                                <i class="mdi mdi-eye" style="font-size: 30px"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Документов пока нет</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $dayClosings->links() }}
        </div>
    </div>
@endsection

