@php
    $user = auth()->user();
    $pageTitle = trim((string) ($title ?? ''));
    $initials = mb_strtoupper(collect(preg_split('/\s+/u', trim((string) $user?->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')) ?: 'A';
    $menu = [
        ['route' => 'dashboard', 'match' => 'dashboard', 'icon' => 'chart', 'label' => 'Дашборд'],
        ['route' => 'sub_domain.index', 'match' => 'sub_domain.*', 'icon' => 'server', 'label' => 'Поддомены'],
        ['route' => 'organization_v2.index', 'match' => ['organization_v2.index', 'organization_v2.show', 'organization_v2.edit'], 'icon' => 'users', 'label' => 'Клиенты'],
        ['route' => 'client-health.index', 'match' => 'client-health.*', 'icon' => 'heart', 'label' => 'Здоровье клиентов'],
        ['route' => 'ai-subscription.index', 'match' => 'ai-subscription.*', 'icon' => 'robot', 'label' => 'ИИ-клиенты'],
        ['route' => 'organization_v2.demo', 'match' => 'organization_v2.demo', 'icon' => 'clock', 'label' => 'Демо'],
        ['route' => 'site-application.index', 'match' => 'site-application.*', 'icon' => 'globe', 'label' => 'Запросы с сайта'],
        ['route' => 'partner.index', 'match' => 'partner.*', 'icon' => 'briefcase', 'label' => 'Партнёры'],
        ['route' => 'application.index', 'match' => 'application.*', 'icon' => 'shop', 'label' => 'Подключение'],
        ['divider' => 'Справочники'],
        ['route' => 'tariff.index', 'match' => 'tariff.*', 'icon' => 'folder', 'label' => 'Услуги'],
        ['route' => 'price_list.index', 'match' => 'price_list.*', 'icon' => 'tag', 'label' => 'Прайслист'],
        ['route' => 'implementation-deals.index', 'match' => 'implementation-deals.*', 'icon' => 'percent', 'label' => 'Скидки'],
        ['route' => 'implementation-prices.index', 'match' => 'implementation-prices.*', 'icon' => 'settings', 'label' => 'Внедрение'],
        ['route' => 'ai-model.index', 'match' => 'ai-model.*', 'icon' => 'cpu', 'label' => 'ИИ-модели'],
        ['route' => 'ai-tariff.index', 'match' => 'ai-tariff.*', 'icon' => 'sparkles', 'label' => 'ИИ-тарифы'],
        ['divider' => 'Финансы'],
        ['route' => 'account.index', 'match' => 'account.*', 'icon' => 'receipt', 'label' => 'Счета'],
        ['route' => 'currency-rate.index', 'match' => 'currency-rate.*', 'icon' => 'currency', 'label' => 'Курс валюты'],
        ['route' => 'day-closing.index', 'match' => 'day-closing.*', 'icon' => 'calendar-check', 'label' => 'Закрытие дня'],
    ];
@endphp
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle !== '' ? $pageTitle . ' — ' : '' }}shamCRM Billing</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('../assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-ink-100" x-data="{ nav: false, rail: false }">
<div class="flex min-h-screen">
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-ink-200 bg-white transition-all duration-200 lg:w-[68px] lg:translate-x-0"
           :class="{ 'translate-x-0': nav, 'lg:!w-64 shadow-xl shadow-ink-900/10': rail }"
           @mouseenter="rail = true" @mouseleave="rail = false">
        <div class="flex h-16 shrink-0 items-center gap-3 px-[21px]">
            <a href="{{ route('organization_v2.index') }}" class="flex min-w-0 items-center gap-3 font-semibold" aria-label="shamCRM">
                <img src="{{ asset('images/logo.webp') }}" alt="" width="26" height="25" class="h-[26px] w-[26px] shrink-0">
                <span class="rail-label text-ink-900 lg:opacity-0" :class="rail ? 'lg:!opacity-100' : ''">sham<span class="text-brand-600">CRM</span></span>
            </a>
            <button type="button" class="ml-auto lg:hidden" @click="nav=false" aria-label="Закрыть"><x-icon name="x" /></button>
        </div>

        <nav class="rail-scroll min-h-0 flex-1 space-y-0.5 overflow-y-auto overflow-x-hidden px-3 py-2">
            @foreach($menu as $item)
                @if(!empty($item['divider']))
                    <p class="rail-label mb-1 mt-4 px-2 text-[11px] font-semibold uppercase tracking-wide text-ink-400 lg:opacity-0" :class="rail ? 'lg:!opacity-100' : ''">{{ $item['divider'] }}</p>
                @else
                    @php $active = request()->routeIs(...(array) $item['match']); @endphp
                    <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}"
                       class="rail-link {{ $active ? 'rail-link-active' : '' }}">
                        <x-icon :name="$item['icon']" class="h-[18px] w-[18px] shrink-0" />
                        <span class="rail-label lg:opacity-0" :class="rail ? 'lg:!opacity-100' : ''">{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </nav>

        <div class="space-y-0.5 border-t border-ink-200 px-3 py-3">
            <a href="{{ route('profile.index') }}" title="Профиль" class="rail-link">
                <x-icon name="user" class="h-[18px] w-[18px] shrink-0" />
                <span class="rail-label lg:opacity-0" :class="rail ? 'lg:!opacity-100' : ''">Профиль</span>
            </a>
            <a href="{{ route('logout') }}" title="Выход" class="rail-link">
                <x-icon name="logout" class="h-[18px] w-[18px] shrink-0" />
                <span class="rail-label lg:opacity-0" :class="rail ? 'lg:!opacity-100' : ''">Выход</span>
            </a>
        </div>
    </aside>
    <div x-show="nav" x-cloak @click="nav=false" class="fixed inset-0 z-30 bg-ink-900/30 lg:hidden"></div>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-[68px]">
        <header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-ink-200 bg-white px-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="lg:hidden" @click="nav=true" aria-label="Меню"><x-icon name="menu" class="h-6 w-6" /></button>
                <h1 class="truncate text-lg font-semibold text-ink-900">{{ $pageTitle }}</h1>
            </div>
            @if($user)
                <a href="{{ route('profile.index') }}" class="flex min-w-0 items-center gap-2 rounded-lg px-2 py-1 transition-colors hover:bg-ink-100" title="{{ $user->name }}">
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ $initials }}</span>
                    <span class="hidden max-w-[220px] truncate text-sm font-semibold text-ink-900 md:block">{{ $user->name }}</span>
                </a>
            @endif
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:py-8">
            @foreach(['success' => 'border-emerald-200 bg-emerald-50 text-emerald-800', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $key => $cls)
                @if(session($key))
                    <div class="mb-5 rounded-xl border px-4 py-3 text-sm {{ $cls }}">{{ session($key) }}</div>
                @endif
            @endforeach
            @if(isset($errors) && $errors->any())
                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif
