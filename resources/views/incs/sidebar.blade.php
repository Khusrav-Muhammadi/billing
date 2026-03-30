<ul class="nav">
    <li class="nav-item pt-3">
        <a class="nav-link d-block" href="#">
            <h1 style="color: blue">Billing</h1>
        </a>
    </li>
{{--    <li class="nav-item">--}}
{{--        <a class="nav-link" href="{{ route('dashboard') }}">--}}
{{--            <i class="mdi mdi-view-dashboard menu-icon"></i>--}}
{{--            <span class="menu-title">Дашбоард</span>--}}
{{--        </a>--}}
{{--    </li>--}}
    <li class="nav-item">
        <a class="nav-link" href="{{ route('sub_domain.index') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Поддомены</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('organization_v2.index') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Клиенты</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('organization_v2.demo') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Демо</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('partner.index') }}">
            <i class="mdi mdi-account-multiple menu-icon"></i>
            <span class="menu-title">Партнеры</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('tariff.index') }}">
            <i class="mdi mdi-chart-bar menu-icon"></i>
            <span class="menu-title">Услуги</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('price_list.index') }}">
            <i class="mdi mdi-chart-bar menu-icon"></i>
            <span class="menu-title">Прайслист</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('account.index') }}">
            <i class="mdi mdi-file-document-box menu-icon"></i>
            <span class="menu-title">Счета</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('currency-rate.index') }}">
            <i class="mdi mdi-swap-horizontal menu-icon"></i>
            <span class="menu-title">Курс валюты</span>
        </a>
    </li>
{{--    <li class="nav-item">--}}
{{--        <a class="nav-link" href="{{ route('pack.index') }}">--}}
{{--            <i class="mdi mdi-sale menu-icon"></i>--}}
{{--            <span class="menu-title">Пакеты</span>--}}
{{--        </a>--}}
{{--    </li>--}}
    <li class="nav-item">
        <a class="nav-link" href="{{ route('client.index') }}">
            <i class="mdi mdi-contacts menu-icon"></i>
            <span class="menu-title">Клиенты (Старые)</span>
        </a>
    </li>

{{--    <li class="nav-item">--}}
{{--        <a class="nav-link" href="{{ route('partner-request.index') }}">--}}
{{--            <i class="mdi mdi-account-card-details menu-icon"></i>--}}
{{--            <span class="menu-title">Заявки партнера</span>--}}
{{--        </a>--}}
{{--    </li>--}}
{{--    <li class="nav-item">--}}
{{--        <a class="nav-link" href="{{ route('site-application.index') }}">--}}
{{--            <i class="mdi mdi-account-card-details menu-icon"></i>--}}
{{--            <span class="menu-title">Заявки с сайта</span>--}}
{{--        </a>--}}
{{--    </li>--}}
    <li class="nav-item">
        <a class="nav-link" href="{{ route('client-payment.index') }}">
            <i class="mdi mdi-account-card-details menu-icon"></i>
            <span class="menu-title">Шаблон ссылки</span>
        </a>
    </li>
{{--    <li class="nav-item">--}}
{{--        <a class="nav-link" href="{{ route('report.income') }}">--}}
{{--            <i class="mdi mdi-chart-bar menu-icon"></i>--}}
{{--            <span class="menu-title">Отчёт о доходности</span>--}}
{{--        </a>--}}
{{--    </li>--}}
    <li class="nav-item">
        <a class="nav-link" href="{{ route('payment.index') }}">
            <i class="mdi mdi-currency-usd menu-icon"></i>
            <span class="menu-title">Платежи</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('application.create') }}">
            <i class="mdi mdi-currency-usd menu-icon"></i>
            <span class="menu-title">Подключение</span>
        </a>
    </li>
{{--    <li class="nav-item">--}}
{{--        <a class="nav-link collapsed" data-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">--}}
{{--            <i class="mdi mdi-settings menu-icon"></i>--}}
{{--            <span class="menu-title">Настройки</span>--}}
{{--            <i class="menu-arrow"></i>--}}
{{--        </a>--}}
{{--        <div class="collapse show" id="ui-basic" style="">--}}
{{--            <ul class="nav flex-column sub-menu">--}}
{{--                <li class="nav-item">--}}
{{--                    <a class="nav-link" href="{{route('business_type.index')}}">--}}
{{--                        <i class="mdi mdi-format-list-bulleted menu-icon" style="color: white"></i>--}}
{{--                        <span class="">Тип бизнеса</span>--}}
{{--                    </a>--}}
{{--                </li>--}}
{{--                <li class="nav-item">--}}
{{--                    <a class="nav-link" href="{{ route('sale.index') }}">--}}
{{--                        <i class="mdi mdi-sale menu-icon" style="color: white"></i>--}}
{{--                        <span class="">Скидки</span>--}}
{{--                    </a>--}}
{{--                </li>--}}

{{--                <li class="nav-item">--}}
{{--                    <a class="nav-link" href="{{ route('partner-status.index') }}">--}}
{{--                        <i class="mdi mdi-sale menu-icon" style="color: white"></i>--}}
{{--                        <span class="">Статусы партнеров</span>--}}
{{--                    </a>--}}
{{--                </li>--}}
{{--            </ul>--}}
{{--        </div>--}}
{{--    </li>--}}
    <li class="nav-item mt-5">
        <a class="nav-link" href="{{ route('logout') }}">
            <i class="mdi mdi-logout-variant menu-icon"></i>
            <span class="menu-title">Выход</span>
        </a>
    </li>

</ul>
