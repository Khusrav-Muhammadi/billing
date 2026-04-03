@extends('layouts.app')

@section('title')
    Создание закрытия дня
@endsection

@section('content')
    <div class="card-body">
        <h4 class="card-title">Создание закрытия дня</h4>

        <div class="mb-3">
            <a href="{{ route('day-closing.index') }}" class="btn btn-outline-danger">Назад</a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('day-closing.store') }}">
            @csrf

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="date_from">Дата от</label>
                        <input type="date"
                               class="form-control @error('date_from') is-invalid @enderror"
                               id="date_from"
                               name="date_from"
                               value="{{ old('date_from', now()->toDateString()) }}"
                               required>
                        @error('date_from')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label for="date_to">Дата до</label>
                        <input type="date"
                               class="form-control @error('date_to') is-invalid @enderror"
                               id="date_to"
                               name="date_to"
                               value="{{ old('date_to', now()->toDateString()) }}"
                               required>
                        @error('date_to')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
@endsection

