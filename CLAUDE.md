# shamCRM Billing (`/var/www/billing`)

Laravel 10 (PHP 8.1+), отдельный сервис биллинга и продаж shamCRM: `billing-back.shamcrm.com`. Ветка `main`. Своя БД, **без мультитенантности**. Отвечать пользователю по-русски.

## Что здесь

- **Клиенты и организации** (`Client`, `Organization`, `ClientSale`, `ConnectedClientServices`): кто купил CRM, какой поддомен (`clients.sub_domain`), тариф, пакеты, демо, NFR.
- **Тарифы и пакеты** (`Tariff`, `Pack`, `OrganizationPack`, `PackagePrice`, `ExtraUserPriceTier`, `TariffCurrency`).
- **Деньги**: оплаты, счета, балансы, закрытие дня (`Payment*`, `Invoice*`, `ClientBalance`, `Transaction`, `DayClosing*`, `Income`, `Advance`). Провайдеры в `app/Services/Payment`: Payme, Click, Alif, OctoBank, счёт (`Factory` + `Contracts`).
- **Коммерческие предложения (КП)**: `CommercialOffer*`, PDF через Browsershot/dompdf. Этот же эндпоинт `api/generateOffer` вызывает CRM.
- **ИИ-биллинг**: `app/Models/Ai/*`, `app/Services/Ai/*` (тарифы ИИ, баланс, списание по usage-логам из CRM, квоты анализа звонков). План модуля: `docs/ai-billing-plan.md`.
- **Партнёрская программа**: `Partner*`, `app/Models/PartnerProgram`, `routes/partner_program.php`.
- **Заявки и демо с сайта**: `SiteApplications`, `DemoRequest`, `Site*Controller` (каталог, продление, ссылка на оплату для shamcrm.com).
- Админка: Blade в `resources/views/admin/*` (`routes/web.php`). API: `routes/api.php`, `routes/payments.php`, `routes/partner_program.php` (Sanctum).
- Расписание в `app/Console/Kernel.php`: ИИ-списание каждые 30 минут, начало/конец месяца, контроль демо/NFR, закрытие дня, синхронизация квот хранилища и анализа звонков в CRM.

## Связь с CRM (`/var/www/Crm`) и фронтом (`/var/www/sham_front`)

- Биллинг → CRM: джобы `app/Jobs/*Job.php` (`CreateOrganizationJob`, `UpdateTariffJob`, `AddPackJob`, `ActivationJob`, `DisableDemoJob`…) через `App\Support\CrmHttp`. URL тенанта: `https://{clients.sub_domain}-back.shamcrm.com/api/organization/...`, заголовок `X-Service-Token`. В CRM их принимает middleware `billing.token`.
- CRM → биллинг: роуты с middleware `crm.token` (например `clients-balance`, `clients/change-sub-domain`). Секрет общий: `BILLING_CRM_TOKEN` в `.env` обоих проектов.
- Фронт CRM тоже ходит сюда (`sham_front/app/plugins/axios.js` отдельно обрабатывает `billing-back.shamcrm.com`, `app/utils/billingSiteCatalog.js`, `cpGenerator.js`).
- При изменении контракта менять обе стороны и проверять, что поддомен собирается одинаково (раньше лишний `-new` ломал демо, см. комментарий в `CrmHttp::tenantUrl`).
- `BOT_ENGINE_OVERVIEW.md` описывает движок чат-агента CRM, а не код биллинга.

## Как работать

- Сначала обсудить, потом код. На новую фичу сначала анализ и варианты, код только после явного «давай».
- Не запускать приложение и тесты против локальной БД без просьбы: пользователь деплоит и тестирует на сервере. Проверять статически (`php -l`, `git diff`), в конце написать, что деплоить (миграции, очереди, cron) и как проверить.
- Тесты на Pest (`tests/`). Запускать только отдельные файлы и только по просьбе.
- Деньги: суммы и курсы держать в `decimal`, валюту брать явно из записи (тариф, оплата), не подставлять по умолчанию. Историю цен и скидок не перезаписывать: старая запись закрывается, создаётся новая (см. `docs/ai-billing-plan.md`).
- В корне лежит мусор (`,`, `[]`, `1`, `refresh_token,`, `.en`, `tmp*/`): не трогать и не коммитить без просьбы.
- Коммитить и пушить только по просьбе.
- Секреты, токены провайдеров оплаты и данные клиентов не переносить в код, коммиты и внешние сервисы.

## Прод (только когда задача без этого не решается)

- `ssh shamcrm`, биллинг там лежит в `/var/www/billing` (CRM-бэкенд в `/home/projects/shamcrm-back`, фронт в `/var/www/sham_front`).
- Только чтение: `SELECT` в `mysql` и просмотр файлов/логов. Никаких записей в БД, artisan-команд, правок файлов и рестартов.
