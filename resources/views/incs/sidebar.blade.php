<ul class="nav">
    <li class="nav-item pt-3">
        <a class="nav-link d-block" href="#">
            <h1 style="color: blue">Billing</h1>
        </a>
    </li>
    <li class="pt-2 pb-1">
        <span class="nav-item-head">Template Pages</span>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('dashboard') }}">
            <i class="mdi mdi-compass-outline menu-icon"></i>
            <span class="menu-title">Главная</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('client.index') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Клиенты</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{route('business_type.index')}}">
            <i class="mdi mdi-format-list-bulleted menu-icon"></i>
            <span class="menu-title">Тип бизнеса</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('tariff.index') }}">
            <i class="mdi mdi-chart-bar menu-icon"></i>
            <span class="menu-title">Тарифы</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('partner.index') }}">
            <i class="mdi mdi-account-multiple menu-icon"></i>
            <span class="menu-title">Партнеры</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('sale.index') }}">
            <i class="mdi mdi-sale menu-icon"></i>
            <span class="menu-title">Скидки</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('pack.index') }}">
            <i class="mdi mdi-package menu-icon"></i>
            <span class="menu-title">Пакеты</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('logout') }}">
            <i class="mdi mdi-logout-variant menu-icon"></i>
            <span class="menu-title">Выход</span>
        </a>
    </li>

</ul>
