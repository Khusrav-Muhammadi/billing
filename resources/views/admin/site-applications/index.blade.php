@extends('layouts.app')

@section('title')
    Запросы из сайта
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card-body">
        <h4 class="card-title">Запросы из сайта</h4>
        <p class="text-muted mb-3">Заявки с форм shamcrm.com: партнёрская программа и остальные обращения, кроме демо.</p>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Дата</th>
                    <th>Имя</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Регион</th>
                    <th>Тип</th>
                    <th>Комментарий</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>{{ $application->created_at?->format('d.m.Y H:i') }}</td>
                        <td>{{ $application->fio }}</td>
                        <td>{{ $application->phone }}</td>
                        <td>{{ $application->email ?: '—' }}</td>
                        <td>{{ $application->region ?: '—' }}</td>
                        <td>{{ $types[$application->request_type] ?? $application->request_type }}</td>
                        <td style="max-width: 280px;">{{ $application->comment ?: '—' }}</td>
                        <td>
                            <form action="{{ route('site-application.delete', $application) }}" method="POST"
                                  onsubmit="return confirm('Удалить этот запрос?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-link text-danger p-0" title="Удалить">
                                    <i class="mdi mdi-delete" style="font-size: 24px"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Пока нет запросов с сайта</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

    </div>
@endsection
