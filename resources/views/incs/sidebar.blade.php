<ul class="nav">
    <li class="nav-item pt-3">
        <a class="nav-link d-block" href="#">
            <h1 style="color: blue">Billing</h1>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('dashboard') }}">
            <i class="mdi mdi-view-dashboard menu-icon"></i>
            <span class="menu-title">Дашбоард</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('client.index') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Клиенты</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('partner.index') }}">
            <i class="mdi mdi-account-multiple menu-icon"></i>
            <span class="menu-title">Партнеры</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('partner-request.index') }}">
            <i class="mdi mdi-account-multiple menu-icon"></i>
            <span class="menu-title">Заявки партнера</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link collapsed" data-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
            <i class="mdi mdi-settings menu-icon"></i>
            <span class="menu-title">Настройки</span>
            <i class="menu-arrow"></i>
        </a>
        <div class="collapse show" id="ui-basic" style="">
            <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                    <a class="nav-link" href="{{route('business_type.index')}}">
                        <i class="mdi mdi-format-list-bulleted menu-icon" style="color: white"></i>
                        <span class="">Тип бизнеса</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('tariff.index') }}">
                        <i class="mdi mdi-chart-bar menu-icon" style="color: white"></i>
                        <span class="">Тарифы</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('sale.index') }}">
                        <i class="mdi mdi-sale menu-icon" style="color: white"></i>
                        <span class="">Скидки</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('pack.index') }}">
                        <i class="mdi mdi-package menu-icon" style="color: white"></i>
                        <span class="">Пакеты</span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
    <li class="nav-item mt-5">
        <a class="nav-link" href="{{ route('logout') }}">
            <i class="mdi mdi-logout-variant menu-icon"></i>
            <span class="menu-title">Выход</span>
        </a>
    </li>

</ul>
