# ТЗ: новая админка биллинга в стиле партнёрского портала + светофор клиентов и партнёров

Исполнитель: другая модель (Cursor). Репозиторий: `billing` (Laravel 10, PHP 8.1+), ветка `main`.
Образец дизайна: соседний проект `../partners` (портал партнёров). Его нужно открыть в том же workspace или скопировать указанные ниже файлы.

Прочитай перед началом: `CLAUDE.md` в корне биллинга. Главные правила оттуда:
- не запускать приложение, миграции и тесты против локальной БД; проверять статически (`php -l`, `git diff`, `npm run build`);
- не коммитить и не пушить без просьбы;
- мусор в корне (`,`, `[]`, `1`, `refresh_token,`, `.en`, `tmp*/`) не трогать;
- деньги, расчёты, контроллеры оплат не менять — эта задача только про внешний вид и светофор;
- API для портала (`routes/partner_program.php`, `App\Http\Controllers\API\*`) **не менять**: портал от него зависит.

---

## 0. Цель и границы

1. Админка биллинга (`routes/web.php`, Blade) должна выглядеть как кабинет `../partners`: Tailwind + Alpine, шрифт Onest, цвета brand/ink, узкое меню-«рельса» слева с раскрытием по наведению, белая шапка, карточки, таблицы `table-base`, кнопки `btn`.
2. Переводятся **только 16 активных разделов** (список в п. 4) — все их страницы: списки, карточки (show), создание (create), редактирование (edit), модалки, AJAX-партиалы.
3. Добавить **светофор клиентов** (плитки, колонка, страница «Здоровье клиентов» с историей, блок в карточке клиента).
4. Добавить **светофор партнёров** (по каждому партнёру — сколько у него красных/жёлтых/новых/зелёных клиентов, с переходом в отфильтрованный список, и блок в карточке партнёра).
5. Остальные страницы (дашборд, отчёты, старые клиенты, пакеты, заявки партнёров, настройки и т.д.) **не трогать**: они остаются на старом лейауте `layouts.app`.

Логику контроллеров, репозиториев, валидаций, имена полей форм, `name=""`, `id=""`, `action`, `method`, `@csrf`, `@method`, JS-обработчики **сохранять как есть**. Меняется разметка и классы, не поведение.

---

## 1. Что взять из `../partners`

| Файл в partners | Куда в billing | Что сделать |
|---|---|---|
| `tailwind.config.js` | `tailwind.config.js` (заменить) | Взять `theme.extend` целиком (brand, ink, gold, Onest, `rounded-card`, `rounded-panel`, `shadow-card`, `max-w-content`). `content` и плагины — см. п. 2.1 |
| `resources/css/portal.css` | `resources/css/admin.css` (новый) | Взять `@layer base` и `@layer components` (btn, card, label, input, input-error, field-error, badge, table-base, rail-link, rail-link-active, rail-label, kpi, kpi-label, kpi-value, chip). `prose-contract`, `prose-lesson`, `mark`, `eyebrow`, `section-title` не нужны |
| `resources/views/components/icon.blade.php` | `resources/views/components/icon.blade.php` | Скопировать как есть. Добавить иконки, которых нет: `server` (поддомены), `robot` (ИИ), `receipt` (счета), `currency` (курс), `percent` (скидки), `tag` (прайслист), `calendar-check` (закрытие дня), `cpu` (ИИ-модели), `heart` (здоровье). Формат — как у соседних иконок (SVG path 24×24, stroke) |
| `resources/views/components/layouts/portal-cabinet.blade.php` | `resources/views/components/layouts/admin.blade.php` | Основа нового лейаута. Убрать всё партнёрское (уровни, OwnCrm, locale-switch, `cabinet.*` переводы, меню партнёра). См. п. 3 |
| `resources/views/admin/partials/client-health-blocks.blade.php` | `resources/views/admin/health/blocks.blade.php` | Плитки светофора. См. п. 5.2 |
| `resources/views/admin/partials/client-health-help.blade.php` | `resources/views/admin/health/help.blade.php` | Окно «?» с пояснением цветов. Тексты не менять |
| `resources/views/portal-admin/client-health/index.blade.php`, `show.blade.php`, `_color.blade.php` | `resources/views/admin/health/index.blade.php`, `show.blade.php`, `_color.blade.php` | Страница «Здоровье клиентов» и история. Обёртку `<x-layouts.portal-cabinet>` заменить на `<x-layouts.admin>` |
| В `admin/partials/organizations_v2.blade.php` портала строки 23, 57–70 | `resources/views/admin/partials/organizations_v2.blade.php` биллинга | Колонка «Здоровье» с кругом индекса. См. п. 5.3 |
| `public/images/site/logo.webp` | `public/images/logo.webp` | Логотип в меню |

В портале строки обёрнуты в `__('...')` (переводы RU/UZ). В биллинге переводов нет: писать русский текст напрямую, без `__()`.

---

## 2. Инфраструктура

### 2.1. Tailwind / Vite

- `tailwind.config.js`:
  - `content`:
    - `./resources/views/components/**/*.blade.php`
    - `./resources/views/admin/health/**/*.blade.php`
    - все вьюхи 16 разделов из п. 4 (перечислить пути, глобы по папкам допустимы)
    - `./resources/views/admin/partials/**/*.blade.php`
    - `./resources/js/admin.js`
    - `./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php`
  - `blocklist: ['collapse', 'container']` — эти имена заняты Bootstrap, Tailwind-версии ломают `.collapse` из Bootstrap JS.
  - плагин `@tailwindcss/forms` подключить со `strategy: 'class'`, иначе он перекрасит все старые `<input>`/`<select>`. Класс `.input` в `admin.css` тогда описать полностью (border 1px ink-200, rounded-lg, bg-ink-50, px-3.5, py-2.5, text-sm, focus: border-brand-600, ring brand-600/20), не рассчитывая на базовые стили плагина.
- `resources/css/admin.css` — порядок: `@tailwind base;` → `@layer base {...}` → `@import`/вставка legacy-слоя (п. 2.2) → `@tailwind components;` → `@layer components {...}` → `@tailwind utilities;`.
- `resources/js/admin.js`: `import Alpine from 'alpinejs'; window.Alpine = Alpine; Alpine.start();` + обработчик `[data-copy]` из `partners/resources/js/portal.js` (только он; лендинг и телефонную маску не брать).
- `vite.config.js`: в `input` добавить `resources/css/admin.css`, `resources/js/admin.js` (старые `app.css`/`app.js` оставить).
- `.gitignore`: убрать строку `/public/build` (сборку коммитим, как в partners: на сервере нет node). Добавить комментарий об этом.
- Локально: `npm install` и `npm run build`. Собранный `public/build` — часть результата.

### 2.2. Legacy-слой для старой разметки (самое важное для экономии работы)

Старые страницы написаны на классах Bootstrap 4/5 вперемешку (тема Purple Admin, CSS Bootstrap 4.4.1, JS Bootstrap 5.1.3 с CDN). В новом лейауте CSS Bootstrap и темы **не подключается**. Вместо него в `admin.css` пишется слой, который даёт этим классам вид портала. Тогда большая часть страниц переводится одной заменой `@extends`, а руками переписываются только сложные места.

Правила слоя:
1. Классы (`.btn`, `.form-control` и т.д.) — обычные селекторы **вне `@layer`**: тогда Tailwind не выкидывает их при сборке. Это важно: часть классов собирается динамически (`btn-{{ $x }}`, `badge-{{ ... }}`, `bg-{{ ... }}`).
2. Стили элементов (`h1–h6`, `p`, `label`, `hr`, `small`, `a`) — только внутри обёртки `.bs` и через `:where(.bs)`, например `:where(.bs) h4 { ... }`. Так у селектора низкая специфичность, и Tailwind-утилиты на новых блоках его перебивают. Обёртку `<div class="bs">` вокруг `@yield('content')` ставит лейаут (п. 3).
3. Значения брать из партнёрских компонентов, например `.btn-primary` = `@apply bg-brand-600 text-white hover:bg-brand-700`, `.form-control` = как `.input`, `.card` = `rounded-card border border-ink-200 bg-white`, `.table thead` = как `.table-base thead`.

Классы, которые нужно покрыть. Это реальный список из 16 разделов, отсортированный по частоте использования. Получить его заново можно командой из п. 7.1.

- **Формы:** `form-control`, `form-control-sm`, `form-control-plaintext`, `form-group` (отступ снизу 1rem), `form-label`, `is-invalid` (красная рамка), `form-check`, `form-check-inline`, `form-check-input`, `custom-checkbox` (+ `custom-control-input`/`custom-control-label`, если встретятся), `btn-check`, `input-group` (если встретится), `select.form-control` (стрелка).
- **Кнопки:** `btn`, `btn-sm`, `btn-lg`, `btn-primary`, `btn-secondary`, `btn-danger`, `btn-success`, `btn-warning`, `btn-info`, `btn-light`, `btn-dark`, `btn-link`, `btn-outline-{primary,secondary,danger,success,dark,warning,info}`, `btn-group`, `btn-group-sm`, `btn-group-vertical`, `btn-close`, `btn-block`.
- **Модалки (Bootstrap 5 JS):** `modal`, `fade`, `show`, `modal-dialog`, `modal-dialog-centered`, `modal-dialog-scrollable`, `modal-lg`, `modal-xl`, `modal-sm`, `modal-content`, `modal-header`, `modal-title`, `modal-body`, `modal-footer`, `modal-backdrop`, `modal-open` (на body), плюс `modal-x-close` из своих стилей. CSS модалки должен работать с JS Bootstrap 5: `.modal{display:none;position:fixed;inset:0;z-index:1055;overflow-y:auto}`, `.modal-dialog{margin:1.75rem auto;max-width:500px}`, `.modal-lg{max-width:800px}`, `.modal-xl{max-width:1140px}`, `.modal-backdrop{position:fixed;inset:0;z-index:1050;background:rgb(18 20 42/.4)}`, `.fade{transition:opacity .15s linear}`, `.fade:not(.show){opacity:0}`, `.modal.fade .modal-dialog{transform:translateY(-20px);transition:transform .2s}`, `.modal.show .modal-dialog{transform:none}`, `body.modal-open{overflow:hidden}`. Внешний вид `modal-content`: `rounded-panel bg-white shadow-xl`, шапка — `border-b border-ink-200 px-5 py-4`, футер — `border-t`, `flex justify-end gap-2`.
- **Dropdown / collapse:** `dropdown`, `dropdown-toggle`, `dropdown-menu` (скрыт, `.show` → виден, absolute, `card shadow-card py-1`), `dropdown-item`, `collapse` (`.collapse:not(.show){display:none}`), `collapsing`.
- **Таблицы:** `table` (width 100%, как `table-base`), `table-sm`, `table-hover`, `table-striped`, `table-bordered`, `table-light`, `table-responsive` (overflow-x auto), `align-middle`, `table-container`.
- **Карточки и алерты:** `card`, `card-body` (p-5), `card-title` (text-lg font-semibold mb-4), `card-header`, `card-footer`, `alert`, `alert-{success,danger,warning,info,light}` — цвета как у флеш-сообщений лейаута partners (emerald/red/amber/sky на 50 с рамкой 200).
- **Бейджи:** `badge` (уже есть в partners) + `badge-{success,danger,warning,info,secondary,primary}` и `bg-{success,danger,warning,info,secondary,primary,light,dark}`, применённые к `.badge`: светлые плашки (`bg-emerald-50 text-emerald-700` и т.п.). Отдельно `bg-*` без badge — фон.
- **Сетка:** `row` (flex wrap, отрицательные поля −0.75rem), `col`, `col-{1..12}`, `col-{sm,md,lg}-{1..12}` с брейкпоинтами Bootstrap (576/768/992/1200), `g-{0..4}`, `gx-*`, `gy-*`, `container-fluid`.
- **Утилиты Bootstrap**, которых нет в Tailwind или которые там значат другое: `d-{none,block,inline,inline-block,inline-flex,flex}` и `d-{sm,md,lg}-*`, `flex-column`, `flex-fill`, `flex-grow-1`, `justify-content-{start,end,center,between}` и `justify-content-sm-between`, `align-items-{start,end,center,stretch}`, `align-self-center`, `text-{muted,dark,danger,success,warning,primary,secondary,info}`, `text-decoration-none`, `text-break`, `text-nowrap`, `text-sm-left`, `float-sm-right`, `float-none`, `fw-{bold,semibold,normal}`, `font-weight-bold`, `fs-{1..6}`, `small`, `w-100`, `h-100`, `position-relative`, `ms-*`/`me-*` (если Tailwind их не сгенерировал), `mr-*`/`ml-*` (Bootstrap 4).
- Отступы `m*-N`/`p*-N` не переопределять: Tailwind генерирует их сам, разница в шкале (0.75rem против 1rem) допустима.
- **Иконки** `mdi mdi-*` (73 места): оставить ссылку на `assets/vendors/mdi/css/materialdesignicons.min.css` в лейауте. `fas fa-info-circle` (4 места в `admin/partners/edit.blade.php`) заменить на `mdi mdi-information-outline`, Font Awesome не подключать.
- **Пагинация:** `Paginator::useBootstrap*` в биллинге не вызывается (проверено), поэтому `->links()` уже рендерит стандартный Tailwind-шаблон Laravel. Путь `vendor/laravel/framework/src/Illuminate/Pagination/resources/views/tailwind.blade.php` должен быть в `content`, тогда пагинация станет нормальной сама. Глобально ничего не менять. Если на какой-то странице вручную сверстана Bootstrap-пагинация (`.pagination`, `.page-item`, `.page-link`), стилизовать эти классы в legacy-слое: кнопки как `btn-secondary` размера sm, активная — `bg-brand-600 text-white`. Обработчики `.pagination a` в JS страниц не ломать.

### 2.3. Бренд-логика цвета

- Главное действие (сохранить, создать) — `btn-primary` (brand-600).
- Удаление — `btn-danger`/`btn-outline-danger` (red-600).
- «Назад», «Отмена» — `btn-secondary`.
- Не придумывать новых цветов: только brand, ink, emerald, amber, red, sky, gold.

---

## 3. Новый лейаут `x-layouts.admin`

Файл `resources/views/components/layouts/admin.blade.php` — компонент, но старые страницы используют `@extends/@section`. Поэтому делаем две точки входа с одной разметкой:

1. `resources/views/components/layouts/admin.blade.php` — компонент (`{{ $slot }}`, `@props(['title' => null])`) для новых страниц: «Здоровье клиентов», история.
2. `resources/views/layouts/admin.blade.php` — для старых страниц: внутри `<x-layouts.admin :title="trim($__env->yieldContent('title'))"> <div class="bs">@yield('content')</div> </x-layouts.admin>` и после неё `@yield('script')`. Скрипты страниц должны оказаться **после** подключения Bootstrap JS (см. ниже) и внутри `<body>`. Если через компонент это неудобно, вынести шапку и меню в `resources/views/admin/layout/_shell-start.blade.php` и `_shell-end.blade.php` и подключить в обоих лейаутах.

Что в `<head>`:
- `meta csrf-token` — обязательно, его читают fetch-запросы старых страниц;
- шрифт Onest (как в partners);
- `materialdesignicons.min.css` (путь как в `layouts/app.blade.php`);
- `@vite(['resources/css/admin.css', 'resources/js/admin.js'])`;
- `https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js` — та же версия, что сейчас, с Popper внутри. Нужен для `data-bs-toggle="modal"`, `dropdown`, `collapse` и для двух мест с `new bootstrap.Modal(...)`;
- **не подключать** `vendor.bundle.base.css/js` (jQuery + Bootstrap 4 темы), `demo_1/style.css`, `public/style.css`, flot, chart.js. В 16 разделах нет ни одного `$(`/`jQuery` (проверено).

Если на какой-то странице найдётся `data-toggle=`/`data-dismiss=` (атрибуты Bootstrap 4), заменить на `data-bs-toggle=`/`data-bs-dismiss=`. Если оба атрибута стоят рядом, оставить только `data-bs-*`.

Меню (как `$menu` в partners; иконка, подпись, `match` для подсветки):

| # | Подпись | `route` | `match` (routeIs) | Иконка |
|---|---|---|---|---|
| 1 | Поддомены | `sub_domain.index` | `sub_domain.*` | server |
| 2 | Клиенты | `organization_v2.index` | `organization_v2.index`, `organization_v2.show`, `organization_v2.edit` | users |
| 3 | Здоровье клиентов | `client-health.index` | `client-health.*` | heart |
| 4 | ИИ-клиенты | `ai-subscription.index` | `ai-subscription.*` | robot |
| 5 | Демо | `organization_v2.demo` | `organization_v2.demo` | clock |
| 6 | Запросы с сайта | `site-application.index` | `site-application.*` | globe |
| 7 | Партнёры | `partner.index` | `partner.*` | briefcase |
| 8 | Подключение | `application.index` | `application.*` | shop |
| — | *разделитель «Справочники»* | | | |
| 9 | Услуги | `tariff.index` | `tariff.*` | folder |
| 10 | Прайслист | `price_list.index` | `price_list.*` | tag |
| 11 | Скидки | `implementation-deals.index` | `implementation-deals.*` | percent |
| 12 | Внедрение | `implementation-prices.index` | `implementation-prices.*` | settings |
| 13 | ИИ-модели | `ai-model.index` | `ai-model.*` | cpu |
| 14 | ИИ-тарифы | `ai-tariff.index` | `ai-tariff.*` | sparkles |
| — | *разделитель «Финансы»* | | | |
| 15 | Счета | `account.index` | `account.*` | receipt |
| 16 | Курс валюты | `currency-rate.index` | `currency-rate.*` | currency |
| 17 | Закрытие дня | `day-closing.index` | `day-closing.*` | calendar-check |

Внизу меню: «Профиль» (`profile.index`), «Выход» (`route('logout')`, это GET в `routes/auth.php`). Логотип ведёт на `organization_v2.index`.

Шапка: заголовок страницы (`title`), справа — инициалы и имя `auth()->user()->name`, ссылка на профиль. Без уровней и переключателя языка.

Флеш-сообщения: лейаут показывает `session('success'|'error'|'warning')` и `$errors->any()`, как в partners. На 13 страницах уже есть свой вывод флеша. На них нужно убрать **только верхний блок страницы** с `session('success')`/`session('error')`/общим `$errors->any()`, чтобы не было дублей. `@error('field')` у полей и блоки ошибок внутри модалок не трогать. Найти эти страницы командой из п. 7.1.

Высота для iframe: страницы `admin/applications/show-*.blade.php` и `create*.blade.php` показывают генераторы КП в `<iframe>`. Внутри iframe ничего не менять. Самому iframe дать `w-full`, высоту `calc(100vh - 4rem - 3rem)`, без рамки, обернуть в `.card overflow-hidden`.

---

## 4. Перевод 16 разделов

Для каждой вьюхи из таблицы:
1. `@extends('layouts.app')` → `@extends('layouts.admin')`. Секции `title`/`content`/`script` оставить.
2. Обёртка страницы: заголовок + кнопки действий в одну строку (`flex flex-wrap items-center justify-between gap-3 mb-5`), блоки — `.card`. Старые `<div class="card-body"><h4 class="card-title">` можно оставить: legacy-слой их стилизует. Переписывать на чистый Tailwind нужно только то, что помечено **руками**.
3. Фильтры над таблицами — в одну строку (`flex flex-wrap items-end gap-3`), поля `form-control` → `input` (или оставить `form-control`, его стилизует слой).
4. Таблицы: обернуть в `.card overflow-hidden` → `.overflow-x-auto` → `<table class="table-base">`. Если менять разметку долго, оставить `.table` (стилизуется как `table-base`).
5. Формы create/edit: сетка `grid gap-4 md:grid-cols-2`, подписи `.label`, ошибки `.field-error`. **Сохранить** все `name`, `id`, `value`, `old(...)`, `@error`, `required`, `data-*`, классы `js-*`: их ищут скрипты.
6. Модалки оставить на Bootstrap (`data-bs-toggle="modal"`). Их стилизует слой, на Alpine не переписывать.

| Раздел | Роуты | Контроллер | Вьюхи (все обязательны) | Что учесть |
|---|---|---|---|---|
| Поддомены | `sub_domain.index/show/create/store/update/activation/deleteAll/createTransaction` | `V2\SubdomainController` | `v2/sub_domains/index`, `v2/sub_domains/show`, `admin/clients/create` (её отдаёт `sub_domain.create`, она же у старого `client.create` — менять можно, общая), `admin/partials/sub_domains` | **Руками:** `show` (556 строк, intl-tel-input из CDN, модалки транзакций). В `index` JS делает fetch на `client.index` и вставляет ответ в таблицу, а AJAX-ветка в контроллере закомментирована. Это старая ошибка: поведение не менять, но проверить, что страница не ломается. Подключение intl-tel-input внутри `@section('content')` оставить |
| Клиенты | `organization_v2.index/show/update/destroy/access/addPack/pack.destroy/integration-log.retry` | `V2\OrganizationV2Controller` | `v2/organizations_v2/index`, `v2/organizations_v2/show`, `admin/partials/organizations_v2` (AJAX-партиал, общий с «Демо») | **Руками:** `index` (фильтры + плитки светофора, п. 5) и `show` (карточка + блок светофора). Клик по строке `.organization-row[data-href]` сохранить. Сортировка по ссылкам `$sortLink()` сохранить |
| Здоровье клиентов | новые `client-health.index`, `client-health.show` | новый `Admin\ClientHealthController` | `admin/health/*` | п. 5 |
| ИИ-клиенты | `ai-subscription.index/show` | `AiSubscriptionController` | `admin/ai-subscriptions/index`, `admin/ai-subscriptions/show` | `show` 335 строк — руками шапку и карточки баланса. Строки `.ai-subscription-row` сохранить |
| Демо | `organization_v2.demo` | `V2\OrganizationV2Controller@demo` | `v2/organizations_v2/demo`, тот же партиал | Плиток светофора нет, колонка «Здоровье» есть (п. 5.3) |
| Запросы с сайта | `site-application.index/delete` | `API\SiteApplicationController` | `admin/site-applications/index` | Только `@extends` + таблица |
| Партнёры | `partner.index/create/store/edit/update/delete` + manager/curator/procent/status | `PartnerController` | `admin/partners/index`, `admin/partners/create`, `admin/partners/edit` | **Руками:** `index` (колонка светофора, п. 6), `edit` (727 строк, много модалок: история, менеджеры, кураторы, проценты, статусы; блок светофора сверху). `create` — intl-tel-input |
| Подключение | `application.*` | `ApplicationController` | `admin/applications/index`, `edit`, `create`, `create-renewal`, `create-renewal-no-changes`, `create-connection-extra-services`, `show-connection`, `show-extra-services`, `show-renewal`, `show-renewal-no-changes` | `index` 589 строк: таблица КП, статусы (`offer-status-*` классы), три модалки с `modal-x-close`. Классы `offer-*` и `js-*` не трогать. Остальные — iframe (п. 3) |
| Услуги | `tariff.index/store/update/delete`, `tariff.included_services.*`, `tariff.exclusions.*` | `TariffController` | `admin/tariffs/index`, `admin/tariffs/included_services`, `admin/tariffs/exclusions` | `index` 406 строк, модалки создания и редактирования. Поля `js-is-tariff`, `js-parent-tariff-wrap`, `js-is-extra-user` и их скрипт сохранить |
| Прайслист | `price_list.*` | `PriceListController` | `admin/tariffs/price_list` | 448 строк, расчёт маржи в JS (`js-price-form`, `js-cost-*`, `js-sell-*`, `js-margin`, `js-money-input`): **классы и структуру полей не менять** |
| Счета | `account.*` | `AccountController` | `admin/accounts/index` | Простая |
| Курс валюты | `currency-rate.*` | `CurrencyRateController` | `admin/currency_rates/index`, `admin/currency_rates/show` | Простая |
| Скидки | `implementation-deals.*` | `ImplementationDiscountCapController` | `admin/implementation/discount_caps` | Простая |
| Внедрение | `implementation-prices.*`, `implementation-prices.ai.*` | `ImplementationPriceController`, `AiImplementationPriceController` | `admin/implementation/prices`, `admin/implementation/ai-prices` (include), `admin/implementation/ai-price-fields` (include) | Include-ы — без `@extends` |
| Закрытие дня | `day-closing.index/create/store/show` | `DayClosingController` | `admin/day_closings/index`, `create`, `show` | Статусы раскрашены через `$dayClosing->status`/`$operationStatusClass`: сопоставить с `badge-*` |
| ИИ-модели | `ai-model.*`, `ai-model.prices.*` | `AiModelController` | `admin/ai-models/index`, `admin/ai-models/prices` | История цен не перезаписывается: поведение форм не менять |
| ИИ-тарифы | `ai-tariff.*`, `ai-tariff.prices.*`, `ai-tariff.periods.*` | `AiTariffController` | `admin/ai-tariffs/index`, `prices`, `periods` | В `periods` JS-расчёт (`period-*` классы) сохранить |

**Не трогать:** `v2/organizations_v2/create.blade.php`, `v2/organizations_v2/edit.blade.php`, `v2/sub_domains/create.blade.php`, `v2/sub_domains/edit.blade.php`, `v2/layouts/*`, `v2/incs/*`. Их не возвращает ни один контроллер (проверено grep-ом по `view(`). Если при проверке окажется, что какая-то из них отдаётся, — перевести её так же.

---

## 5. Светофор клиентов

### 5.1. Данные (без HTTP, прямо из БД биллинга)

Источник: таблица снимков, модель `App\Models\ClientHealthSnapshot`:
- поля `client_id`, `date`, `client_type` (`paid`|`demo`), `color` (`red|yellow|grey|green`), `health_index` (0–100), `logins_score`, `coverage_score`, `dialogs_score`, `entities_score`, `reason_text`, `reasons` (array), `paid_until`, `metrics` (array);
- константы `COLOR_ORDER = [red, yellow, grey, green]`.

Снимки пишет команда `app:client-health-calculate` (ночью). Логику расчёта (`ClientHealthCalculator`, `ClientHealthService`) **не трогать**. Образец чтения — `App\Http\Controllers\API\ClientHealthController` (берётся последний `max('date')`, сортировка красные сверху, формат строки `row()`). Сам этот контроллер **не менять**: его зовёт портал.

Создать `app/Services/ClientHealth/ClientHealthReadService.php`:

```php
latestDate(): ?string                          // ClientHealthSnapshot::max('date'), запомнить в свойстве на время запроса
counts(?int $partnerId = null, ?string $type = 'paid'): array   // ['red'=>n,'yellow'=>n,'grey'=>n,'green'=>n] на latestDate
forClients(array $clientIds): array            // [client_id => ClientHealthSnapshot] на latestDate, без фильтра по типу; пустой массив, если ids пуст или даты нет
countsByPartner(?string $type = 'paid'): array // [partner_id => ['red'=>..,'yellow'=>..,'grey'=>..,'green'=>..]]
list(array $filters): LengthAwarePaginator     // color/type/search/partner_id, как в API index(); with client:id,name,sub_domain,partner_id,manager_id + client.partner:id,name + client.manager:id,name; orderByRaw FIELD(color,...), health_index, client_id; paginate(50)->withQueryString()
history(Client $client, int $days = 60): Collection  // снимки клиента за $days дней, orderByDesc('date')
```

- `countsByPartner`: `ClientHealthSnapshot::whereDate('date', $date)->where('client_type', $type)->join('clients', 'clients.id', '=', 'client_health_snapshots.client_id')->whereNotNull('clients.partner_id')->groupBy('clients.partner_id', 'client_health_snapshots.color')->selectRaw('clients.partner_id AS pid, client_health_snapshots.color AS color, COUNT(*) AS c')`. Имя таблицы проверить в миграции `client_health_snapshots`.
- Везде, где даты нет (`latestDate() === null`), возвращать пустые значения, не падать.
- Фильтр `search` — как в API: `whereHas('client', name like / sub_domain like)`.

### 5.2. Плитки над списком «Клиенты» (`organization_v2.index`)

В `OrganizationV2Controller::index`:
- Параметр `health`: `red|yellow|grey|green` уже понимает `OrganizationRepository::applyHealthFilter` (на последнюю дату, только `paid`). Значение `off` = «Отключенные»: перед вызовом репозитория превратить его в `status = '0'` и убрать `health` (как в `partners/app/Http/Controllers/ClientController::index`).
- Собрать массив `$health`:
  - `available` — есть ли `latestDate()`;
  - `date`;
  - `counts` — `ClientHealthReadService::counts(null, 'paid')`;
  - `color` — текущий `health`;
  - `totals` — `['all' => repository->index([])->total(), 'disconnected' => repository->index(['status' => '0'])->total()]`, обернуть в `Cache::remember('billing_org_totals', 300, ...)`. Внимание: `repository->index()` пагинирует и грузит связи. Если это тяжело, сделать в репозитории лёгкий метод `count(array $data)` на тех же фильтрах, без `with` и без пагинации.
- `$healthByClient = ClientHealthReadService::forClients(ids)`, где ids — `client_id` организаций текущей страницы.
- Передать `$health` и `$healthByClient` во вьюху **и** в AJAX-ветку (`admin.partials.organizations_v2`), иначе колонка пропадёт после фильтра.

Вьюха `admin/health/blocks.blade.php` — из `partners/.../client-health-blocks.blade.php` без `__()`:
- 6 плиток: Все, Красные, Жёлтые, Новые (grey), Зелёные, Отключенные;
- ссылка — `request()->fullUrlWithQuery(['health' => ..., 'page' => null])`, повторный клик снимает фильтр;
- кнопка «?» открывает `admin/health/help.blade.php` (Alpine `x-data="{ help: null }"`).

Подключить в `v2/organizations_v2/index.blade.php` над фильтрами. В форме фильтров `#filterForm` добавить `<input type="hidden" name="health" value="{{ request('health') }}">`, чтобы фильтр не терялся.

### 5.3. Колонка «Здоровье» в таблице клиентов

В `admin/partials/organizations_v2.blade.php` (общий для Клиентов и Демо):
- `<th>Здоровье</th>` только если `isset($healthByClient)`; поправить `colspan` пустой строки (+1);
- ячейка: круг 40×40 с `health_index`, фон по цвету (`red #ef4444`, `yellow #f59e0b`, `grey #9ca3af`, `green #10b981`), `title` = `reason_text`; клик — ссылка на `client-health.show`. Если снимка нет — `—`;
- `client_id` брать как `$organization->client_id`.

В `demo()` тоже передать `$healthByClient` (без плиток). Для демо снимки идут с `client_type = demo`, и `forClients` их вернёт.

### 5.4. Страница «Здоровье клиентов»

Новый контроллер `app/Http/Controllers/Admin/ClientHealthController.php`, роуты в группе `Route::middleware(['auth', 'role:admin'])` в `routes/web.php`:

```php
Route::prefix('client-health')->group(function () {
    Route::get('/', [Admin\ClientHealthController::class, 'index'])->name('client-health.index');
    Route::get('/{client}', [Admin\ClientHealthController::class, 'show'])->whereNumber('client')->name('client-health.show');
});
```

Перед добавлением проверить `php artisan route:list --name=client-health`, если это можно без БД, или grep по `routes/`: имя не должно быть занято.

- `index`: фильтры `color` (из `COLOR_ORDER`), `type` (`paid|demo`), `search`, `partner` (id партнёра, select из `User::where('role','partner')`). Данные — `list()` и `counts()` с теми же фильтрами, кроме цвета. Вьюха — копия `portal-admin/client-health/index.blade.php`:
  - вкладки цветов со счётчиками;
  - таблица: Клиент (имя + поддомен + бейдж «демо»), Цвет, Индекс (+ «В · О · Д · С»), Причина, Оплачено до (+ «через N дн.», красным при ≤ 30), Ответственный (партнёр, менеджер), ссылка «История»;
  - пояснение формулы внизу — текст как в портале;
  - «Клиент» ведёт на карточку: найти организацию клиента (`Organization::where('client_id', ...)->value('id')` одной выборкой на страницу) → `organization_v2.show`, иначе `sub_domain.show`.
- `show`: шапка клиента (имя, поддомен, ссылка на CRM `$client->crmUrl()`, партнёр, менеджер), таблица истории за 60 дней: дата, цвет, индекс, 4 балла, причина, `metrics.users_total` / `active_users`. Копия `portal-admin/client-health/show.blade.php`.
- Обе вьюхи на `<x-layouts.admin title="Здоровье клиентов">`.

### 5.5. Блок в карточке клиента

Partial `admin/health/client-card.blade.php`, принимает `$snapshot` (или null) и `$clientId`. Внутри: круг индекса, цвет словом, дата расчёта, 4 балла, `reason_text`, «Оплачено до», ссылка «История» → `client-health.show`. Если снимка нет — «Индекс ещё не посчитан» серым.

Подключить:
- `v2/organizations_v2/show.blade.php` — `forClients([$organization->client_id])`, передать из `OrganizationV2Controller::show`;
- `v2/sub_domains/show.blade.php` — `forClients([$client->id])`, из `SubdomainController::show`.

---

## 6. Светофор партнёров

### 6.1. Список партнёров (`partner.index`)

В `PartnerController::index` добавить:
- `$healthByPartner = ClientHealthReadService::countsByPartner('paid')`;
- `$activeClients` — тот же запрос, что в `API\PartnerProgramController::index` (организации с `latestConnection.status = 'connected'`, `COUNT(DISTINCT organizations.client_id)` по `clients.partner_id`). Скопировать запрос, API-контроллер не трогать. Лучше вынести в метод `ClientHealthReadService::activeClientsByPartner()` и звать его только из админки;
- сортировка `?sort=health` — по убыванию красных, потом жёлтых. Без параметра — как сейчас (репозиторий возвращает `get()`, сортировать коллекцию в контроллере).

В таблицу `admin/partners/index.blade.php` после «Имя» добавить колонки:
- **«Клиенты»** — число активных клиентов;
- **«Светофор»** — 4 пилюли `● N` (красная, жёлтая, серая, зелёная). Каждая — ссылка на `route('organization_v2.index', ['partner' => $partner->id, 'health' => $color])`. Фильтр `partner` уже есть в `OrganizationRepository::applyOrganizationFilters`. Пилюля с нулём — бледная (`opacity-40`), без ссылки.
- В заголовке колонки «Светофор» — ссылка-переключатель сортировки «сначала проблемные».

Над таблицей — плитки суммарно по всем партнёрам: `counts(null, 'paid')` только по клиентам с `partner_id`. Можно передать в `counts()` флаг «только с партнёром» или посчитать сумму из `countsByPartner`. Переиспользовать `admin/health/blocks.blade.php` нельзя, там другие ссылки: сделать простой ряд из 4 плиток со ссылкой на `organization_v2.index?health=...`.

Учесть, что `admin/partners/index.blade.php` шлёт fetch на `partner.index` с `X-Requested-With`, а в контроллере AJAX-ветки нет, поэтому приходит целая страница. Это старая ошибка. Чинить не требуется, но если чинить, нужно вернуть партиал таблицы и передать в него те же `$healthByPartner`/`$activeClients`.

### 6.2. Карточка партнёра (`partner.edit`)

Сверху `admin/partners/edit.blade.php`, над формой, блок «Здоровье клиентов партнёра»:
- 4 плитки цветов (`counts($partner->id, 'paid')`) — ссылки на `organization_v2.index?partner={id}&health={color}`;
- под ними таблица до 10 клиентов: сначала красные, потом жёлтые (`list(['partner_id' => $partner->id, 'type' => 'paid'])`, первые 10). Колонки: клиент, круг индекса, причина, «Оплачено до», ссылка «История»;
- если данных нет — строка «Индекс ещё не посчитан».

Данные готовить в `PartnerController::edit`, переменные назвать `$partnerHealthCounts`, `$partnerHealthRows`.

---

## 7. Как работать и проверять

### 7.1. Полезные команды (только чтение)

```bash
# Классы, которые реально используются в 16 разделах (для legacy-слоя)
V="resources/views/v2/sub_domains resources/views/v2/organizations_v2 resources/views/admin/ai-subscriptions resources/views/admin/site-applications resources/views/admin/partners resources/views/admin/tariffs resources/views/admin/accounts resources/views/admin/currency_rates resources/views/admin/applications resources/views/admin/implementation resources/views/admin/day_closings resources/views/admin/ai-models resources/views/admin/ai-tariffs resources/views/admin/partials resources/views/admin/clients/create.blade.php"
grep -rhoE 'class="[^"]*"' $V | sed 's/class="//;s/"$//' | tr ' ' '\n' | grep -vE '^\{|^$|\}|@|\(' | sort | uniq -c | sort -rn

# Страницы со своим флешем (убрать дубли верхнего блока)
grep -rlE "session\('(success|error|message|status)'\)|\\\$errors->any" $V

# Атрибуты Bootstrap 4, которые надо заменить на data-bs-*
grep -rnE 'data-(toggle|dismiss)=' $V

# Все ли вьюхи 16 разделов уже на новом лейауте
grep -rL "layouts.admin\|x-layouts.admin" $(grep -rl "@extends" $V)
```

### 7.2. Порядок

1. Инфраструктура (п. 2, 3) + `npm run build` без ошибок.
2. Самая простая страница (Счета) как пример: проверить, что меню, шапка, флеш, модалка «создать», пагинация выглядят правильно.
3. Светофор: сервис → «Здоровье клиентов» → плитки и колонка → карточки → партнёры.
4. Остальные разделы по таблице п. 4: сначала простые, потом сложные (sub_domains/show, partners/edit, applications/index, tariffs/index, price_list).
5. Пересобрать `npm run build` (новые классы попадают в CSS только после сборки).

### 7.3. Статическая проверка (обязательно)

- `php -l` по каждому изменённому PHP-файлу.
- `php artisan view:cache` — компилирует все Blade и ловит синтаксические ошибки без БД. После проверки `php artisan view:clear`.
- `git diff --stat`: в diff не должно быть изменений вне `resources/views` 16 разделов, `resources/views/components`, `resources/views/layouts/admin.blade.php`, `resources/views/admin/health`, `resources/css`, `resources/js`, `tailwind.config.js`, `vite.config.js`, `.gitignore`, `public/build`, `public/images/logo.webp`, `routes/web.php` (2 роута), `app/Services/ClientHealth/ClientHealthReadService.php`, `app/Http/Controllers/Admin/ClientHealthController.php`, `OrganizationV2Controller`, `SubdomainController`, `PartnerController` (+ при необходимости лёгкий `count()` в `OrganizationRepository`).
- По каждой вьюхе сверить, что количество `name="` и `id="` до и после одинаковое: `git diff <file> | grep -cE '^[-+].*name="'`. Удалённых `name=` быть не должно.

### 7.4. Ручная проверка на сервере (делает пользователь)

Для каждого из 16 разделов:
- открыть список; работают фильтры, сортировка, пагинация;
- открыть карточку (show);
- открыть создание и редактирование (create/edit или модалки): поля на месте, ошибки валидации показываются под полями, сохранение работает;
- открыть и закрыть модалку (крестик, кнопка «Отмена», клик по фону, Esc);
- удаление с подтверждением;
- в консоли браузера нет ошибок.

Светофор:
- плитки на «Клиентах» показывают те же числа, что «Здоровье клиентов» в портале;
- клик по плитке фильтрует список, повторный снимает фильтр, «Отключенные» = статус «Неактивный»;
- колонка в «Клиентах» и «Демо»;
- блок в карточке клиента и поддомена;
- у партнёра пилюли ведут в отфильтрованный список, где ровно столько клиентов, сколько написано на пилюле.

---

## 8. Деплой (делает пользователь)

1. `git fetch && git merge --no-edit origin/main` в `/var/www/billing`.
2. Миграций нет.
3. `php artisan view:clear && php artisan route:clear && php artisan config:clear`.
4. `systemctl reload php8.3-fpm` (opcache).
5. `php artisan queue:restart` (на всякий случай, джобы не менялись).
6. Node на сервере не нужен: `public/build` уже в коммите.

---

## 9. Чего не делать

- Не переводить разделы вне списка и не удалять старый `layouts/app.blade.php`, `incs/sidebar.blade.php` и ассеты в `public/assets`: на них работают остальные страницы.
- Не менять глобально пагинатор (`Paginator::useBootstrap*`).
- Не переписывать модалки на Alpine и не заменять Bootstrap JS.
- Не менять имена полей, роуты, валидацию, JS-логику расчётов (прайслист, ИИ-тарифы, подключение).
- Не трогать `API\ClientHealthController`, `API\PartnerProgramController`, `ClientHealthCalculator`, `ClientHealthService`.
- Не подставлять валюту по умолчанию в выводе сумм: показывать ту, что уже выводит страница.
- Не запускать `migrate`, тесты, `serve` против локальной БД.
- Не коммитить без просьбы пользователя.

## 10. Критерии приёмки

1. Все вьюхи 16 разделов (п. 4) на `layouts.admin`. Команда «все ли на новом лейауте» из п. 7.1 ничего не выводит.
2. Внешне не отличить от кабинета `partners`: меню, шапка, карточки, кнопки, таблицы, поля, модалки.
3. Ни одно поле формы, роут или JS-обработчик не пропал.
4. Светофор клиентов: плитки, колонка, страница со списком и историей, блок в карточках.
5. Светофор партнёров: колонка с пилюлями и сортировкой, плитки над списком, блок в карточке партнёра.
6. `php -l`, `php artisan view:cache`, `npm run build` без ошибок; `public/build` в diff.
