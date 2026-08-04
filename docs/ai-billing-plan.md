# Модуль «Биллинг и Управление ИИ-Агентами shamCRM»
## Финальный план разработки

---

## Содержание

1. [Схема БД](#1-схема-бд)
2. [Бизнес-логика и алгоритмы](#2-бизнес-логика-и-алгоритмы)
3. [Архитектура кода](#3-архитектура-кода)
4. [Интеграция с существующим КП-потоком](#4-интеграция-с-существующим-кп-потоком)
5. [CRM-интеграция](#5-crm-интеграция)
6. [Cron-расписание](#6-cron-расписание)
7. [Admin UI](#7-admin-ui)
8. [Порядок разработки по фазам](#8-порядок-разработки-по-фазам)

---

## 1. Схема БД

### 1.1. `ai_tariff_plans` — Тарифные планы ИИ

```
ai_tariff_plans
├── id                    BigInt PK
├── name                  Varchar(100)       Название тарифа
├── price_monthly         Decimal(12,2)      Базовая цена за месяц
├── included_limit_balance Decimal(12,2)     Начисляемый лимит в месяц
├── currency_id           BigInt FK          → currencies (ВАЖНО: валюта тарифа)
├── is_active             Boolean            Доступен ли тариф для подключения
├── created_at / updated_at
```

### 1.2. `ai_tariff_plan_periods` — Периоды и скидки (с историей)

```
ai_tariff_plan_periods
├── id                    BigInt PK
├── plan_id               BigInt FK          → ai_tariff_plans
├── months                TinyInt            1 / 3 / 6
├── discount_percent      Decimal(5,2)       Скидка за период, %
├── price_total           Decimal(12,2)      Авто: price_monthly * months * (1 - discount/100)
├── valid_from            Date               С какой даты действует скидка
├── valid_to              Date nullable      До какой даты (NULL = действует сейчас)
├── created_by            BigInt FK          → users (кто установил)
├── created_at / updated_at

Индекс: (plan_id, months, valid_to) — для быстрого поиска текущей скидки
Правило: при изменении скидки — старая запись закрывается (valid_to = вчера),
         создаётся новая (valid_from = сегодня, valid_to = NULL)
```

### 1.3. `ai_token_pricing` — Прайс моделей (с историей цен)

```
ai_token_pricing
├── id                         BigInt PK
├── provider                   Varchar(50)    openai / anthropic / deepseek / etc
├── model_name                 Varchar(50)    Должен совпадать с model_key в CRM (deepseek-v4-pro)
├── cost_currency_id           BigInt FK      → currencies  Валюта себестоимости (обычно USD)
├── cost_per_1m_input          Decimal(12,6)  Себестоимость входных токенов за 1M
├── cost_per_1m_output         Decimal(12,6)  Себестоимость выходных токенов за 1M
├── margin_percent             Decimal(5,2)   Доля прибыли в цене продажи, % (макс. 99.99)
├── price_currency_id          BigInt FK      → currencies  Валюта продажной цены (обычно USD)
├── price_per_1m_input         Decimal(12,6)  Авто: cost / (1 - margin/100)
├── price_per_1m_output        Decimal(12,6)  Авто: аналогично
├── effective_from             Timestamp      Начало действия цены
├── effective_to               Timestamp null Конец действия (NULL = текущая)
├── is_active                  Boolean
├── created_by                 BigInt FK      → users
├── created_at / updated_at

Формула маржи:
  price_per_1m_input  = cost_per_1m_input  / (1 - margin_percent / 100)
  price_per_1m_output = cost_per_1m_output / (1 - margin_percent / 100)
  Пример: cost=$1.00, margin=90% → price = 1.00 / 0.10 = $10.00 ✓

Примечание по валютам: провайдеры (OpenAI, Anthropic, Deepseek) тарифицируют в USD.
  cost_currency_id = USD  (себестоимость всегда от провайдера в USD)
  price_currency_id = USD (продаём тоже в USD, конвертация происходит при списании с баланса)
  Если price_currency_id ≠ ai_balances.currency_id → конвертация через ExchangeRate при списании.

Индекс: (model_name, effective_to) — для поиска текущей цены по модели
Правило (SCD Type 2): изменение цены → НЕ UPDATE, а:
  1. effective_to = now() у старой записи
  2. Новая запись с effective_from = now(), effective_to = NULL
Запрет UPDATE ценовых полей напрямую — только через AiTokenPricing::updatePrice()
```

### 1.4. `ai_subscriptions` — Подписки организаций

```
ai_subscriptions
├── id                    BigInt PK
├── organization_id       BigInt FK          → organizations
├── plan_id               BigInt FK          → ai_tariff_plans
├── status                TinyInt            1 = активна, 0 = неактивна
├── period_months         TinyInt            1 / 3 / 6 (выбрано при оплате КП)
├── price_paid            Decimal(12,2)      Фактическая сумма оплаты (снапшот)
├── started_at            Timestamp          Начало текущего периода
├── expires_at            Timestamp          Конец периода
├── last_crm_fetch_at     Timestamp null     Метка последнего получения логов из CRM
├── commercial_offer_id   BigInt FK null     → commercial_offers (КП, по которому подключено)
├── created_at / updated_at

Правило продления: если подписка активна (expires_at ещё не прошёл) и клиент
платит за новый период → новый started_at = старый expires_at + 1 день (не теряем дни)
```

### 1.5. `ai_balances` — Текущие балансы организации

```
ai_balances
├── id                        BigInt PK
├── organization_id           BigInt FK unique  → organizations (один баланс на org)
├── currency_id               BigInt FK         → currencies  Валюта обоих счетов (из тарифного плана)
├── limited_balance           Decimal(12,2)     Лимитированный (начисляется по тарифу, сгорает)
├── ai_balance                Decimal(12,2)     Основной ИИ-счёт (пополняемый, не сгорает)
├── is_agent_enabled          Boolean           Флаг активности агента
├── scheduled_activation_at   Date null         Дата отложенной активации (раздел C ТЗ)
├── created_at / updated_at

Примечание: currency_id устанавливается из ai_tariff_plans.currency_id при первой подписке.
  Оба счёта (limited_balance и ai_balance) всегда в одной валюте.
  При пополнении ai_balance через КП — сумма конвертируется в эту валюту если нужно.
```

### 1.6. `ai_usage_raw_logs` — Сырые логи (fetch из CRM)

```
ai_usage_raw_logs
├── id                            BigInt PK
├── organization_id               BigInt FK Index  → organizations
├── crm_log_id                    BigInt           ID записи в CRM (для дедупликации)
├── model_name                    Varchar(50)      model_key из CRM
├── prompt_tokens                 Integer
├── prompt_cache_hit_tokens       Integer          (считаются по той же цене что prompt_tokens)
├── completion_tokens             Integer
├── calculated_cost               Decimal(12,6)    Рассчитанная стоимость по нашим ценам
├── cost_currency_id              BigInt FK        → currencies  Валюта calculated_cost (от ai_token_pricing, обычно USD)
├── price_per_1m_input_snapshot   Decimal(12,6)    Снапшот цены на момент лога
├── price_per_1m_output_snapshot  Decimal(12,6)    Снапшот цены на момент лога
├── margin_percent_snapshot       Decimal(5,2)     Снапшот маржи на момент лога
├── processed                     Boolean default false
├── created_at                    Timestamp        Время вызова (из CRM)
├── fetched_at                    Timestamp        Когда биллинг получил запись

Составной индекс: (organization_id, processed) — быстрая выборка необработанных
Уникальный индекс: (organization_id, crm_log_id) — защита от дублей при повторном fetch
Если model_name не найден в ai_token_pricing → calculated_cost = 0, cost_currency_id = NULL,
  снапшоты NULL, запись всё равно сохраняется (данные не теряются), логируется предупреждение

Конвертация при биллинге:
  Если cost_currency_id ≠ ai_balances.currency_id →
    конвертация через ExchangeRate на дату fetched_at
    сконвертированная сумма = calculated_cost * курс → списывается с баланса
```

### 1.7. `ai_usage_logs` — Агрегированные результаты биллинга (каждые 30 мин)

```
ai_usage_logs
├── id                        BigInt PK
├── organization_id           BigInt FK        → organizations
├── currency_id               BigInt FK        → currencies  Валюта всех сумм (= ai_balances.currency_id)
├── total_cost                Decimal(12,6)    Общая сумма списания за период (в валюте баланса)
├── deducted_from_limited     Decimal(12,6)    Списано с limited_balance
├── deducted_from_ai_balance  Decimal(12,6)    Списано с ai_balance
├── period_start              Timestamp        Начало расчётного периода
├── period_end                Timestamp        Конец расчётного периода
├── created_at

Примечание: все суммы здесь уже в валюте баланса (после конвертации из USD если нужно).
```

### 1.8. `ai_balance_transactions` — Аудит всех движений

```
ai_balance_transactions
├── id                BigInt PK
├── organization_id   BigInt FK           → organizations
├── currency_id       BigInt FK           → currencies  Валюта суммы операции
├── type              Varchar(30)         topup | deduction | overdraft_cover |
│                                         expired_profit | tariff_grant |
│                                         tariff_grant_prorated | debt_cover
├── target_balance    Varchar(20)         limited | ai_balance
├── amount            Decimal(12,4)       Сумма операции (в currency_id)
├── description       Varchar(255)        Причина / комментарий
├── created_at / updated_at

Примечание: currency_id = ai_balances.currency_id организации.
  Все транзакции в одной валюте (валюте баланса). Конвертация выполняется до записи.
```

### 1.9. `commercial_offer_ai_items` — AI-блок в КП

```
commercial_offer_ai_items
├── id                    BigInt PK
├── commercial_offer_id   BigInt FK         → commercial_offers
├── plan_id               BigInt FK         → ai_tariff_plans
├── period_months         TinyInt           1 / 3 / 6
├── unit_price            Decimal(12,4)     Цена за 1 месяц без скидки (снапшот)
├── discount_percent      Decimal(5,2)      Скидка на момент КП (снапшот)
├── partner_percent       Decimal(5,2)      Партнёрский процент
├── original_price        Decimal(12,4)     unit_price * period_months (без скидки)
├── total_price           Decimal(12,4)     Итого с учётом скидки
├── created_at / updated_at
```

---

## 2. Бизнес-логика и алгоритмы

### 2.0. Конвертация валют при списании

```
Если ai_token_pricing.price_currency_id ≠ ai_balances.currency_id:
  Берём ExchangeRate WHERE base_currency_id = price_currency_id
                       AND quote_currency_id = balance_currency_id
                       ORDER BY rate_date DESC LIMIT 1
  converted_cost = calculated_cost * exchange_rate.kurs

Если курс не найден → списание не выполняется, ошибка логируется,
  логи остаются processed=false (попадут в следующий запуск)

Конвертация применяется к:
  - каждому SUM(calculated_cost) из ai_usage_raw_logs перед списанием с ai_balances
  - ai_usage_logs.total_cost / deducted_from_* (фиксируем уже конвертированную сумму)
  - ai_balance_transactions.amount (фиксируем в валюте баланса)
```

### 2.1. Расчёт каждые 30 минут

**Общий флоу для каждой организации с `ai_subscriptions.status = 1`:**

```
Для каждой org с активной подпиской:
  1. DB-транзакция + SELECT ... FOR UPDATE на строку ai_balances
  2. Собрать необработанные логи:
       SELECT SUM(calculated_cost) FROM ai_usage_raw_logs
       WHERE organization_id = ? AND processed = false
  3. Если total_cost = 0 → пропустить
  4. Распределение списания:

     [Шаг A] Списать с limited_balance:
       if limited_balance >= total_cost:
         limited_balance -= total_cost
         deducted_from_limited = total_cost
         deducted_from_ai_balance = 0
         → КОНЕЦ

     [Шаг B] limited_balance не хватает:
       deducted_from_limited = max(0, limited_balance)
       remaining = total_cost - deducted_from_limited
       limited_balance = 0
       ai_balance -= remaining
       deducted_from_ai_balance = remaining

     [Шаг C] Обработка технического минуса:
       if limited_balance < 0:
         overdraft = abs(limited_balance)
         limited_balance = 0
         ai_balance -= overdraft
         → транзакция overdraft_cover

  5. Пометить логи processed = true в ai_usage_raw_logs
  6. Вставить запись в ai_usage_logs (period_start, period_end, суммы)
  7. Записать deduction в ai_balance_transactions

  8. Проверить: total_balance = limited_balance + ai_balance
     if total_balance <= 0:
       is_agent_enabled = false
       → AiAgentToggleJob(org, enabled=false)
     else if was_disabled AND total_balance > 0:
       is_agent_enabled = true
       → AiAgentToggleJob(org, enabled=true)
```

### 2.2. Правила начала и конца месяца

**Конец месяца (последний день месяца 23:59, Asia/Dushanbe):**

```
Для каждой org с ai_balances:
  if limited_balance > 0:
    amount = limited_balance
    limited_balance = 0
    → транзакция expired_profit (сгорание в доход компании)
```

**Начало месяца (1-е число 00:00, Asia/Dushanbe):**

```
Для каждой org:
  1. Погашение долга:
     if limited_balance < 0:
       debt = abs(limited_balance)
       if ai_balance >= debt:
         ai_balance -= debt
         limited_balance = 0
         → транзакция debt_cover
       else:
         limited_balance += ai_balance  (частичное погашение)
         ai_balance = 0
         → транзакция debt_cover (частично)

  2. Начисление лимита (если подписка активна на сегодня):
     is_prorated = (started_at.month == сегодня.month)

     if is_prorated:
       T = число дней в месяце
       D = started_at.day
       days_left = T - D + 1
       prorated = (plan.included_limit_balance / T) * days_left
       limited_balance += prorated
       → транзакция tariff_grant_prorated

     else:
       limited_balance += plan.included_limit_balance
       → транзакция tariff_grant
```

### 2.3. Отложенная активация (нет тарифа, но есть ai_balance)

```
Условие: к 1-му числу нет активной ai_subscriptions, но ai_balance > 0

1. is_agent_enabled = false (нет тарифа — нет работы)
2. Вычислить дату активации:
     daily_rate = last_plan.included_limit_balance / T
     days_covered = floor(ai_balance / daily_rate)
     scheduled_activation_at = конец_месяца - days_covered + 1
3. Сохранить в ai_balances.scheduled_activation_at

Ежедневный cron (09:00):
  if today == scheduled_activation_at:
    is_agent_enabled = true
    → AiAgentToggleJob(org, enabled=true)
    scheduled_activation_at = NULL
    (ai_balance тратится по обычной 30-мин логике до конца месяца)

Сброс при пополнении ai_balance или новой подписке:
  → пересчитать scheduled_activation_at или обнулить
```

### 2.4. Пропорциональное начисление при подключении в середине месяца

```
T = число дней в текущем месяце (28/29/30/31)
D = день started_at
days_left = T - D + 1
prorated = (plan.included_limit_balance / T) * days_left
limited_balance += prorated
→ транзакция tariff_grant_prorated
```

### 2.5. Продление подписки

```
Если подписка ещё активна (expires_at > today):
  new_started_at = expires_at + 1 день  (не теряем оплаченные дни)
  new_expires_at = new_started_at + period_months - 1 день (конец месяца)
  
Если подписка уже истекла:
  new_started_at = today
  new_expires_at = today + period_months - 1 день
```

---

## 3. Архитектура кода

### 3.1. Модели (`app/Models/Ai/`)

```
AiTariffPlan          → ai_tariff_plans
AiTariffPlanPeriod    → ai_tariff_plan_periods
AiTokenPricing        → ai_token_pricing
AiSubscription        → ai_subscriptions
AiBalance             → ai_balances
AiUsageRawLog         → ai_usage_raw_logs
AiUsageLog            → ai_usage_logs
AiBalanceTransaction  → ai_balance_transactions
CommercialOfferAiItem → commercial_offer_ai_items
```

### 3.2. Observers (`app/Observers/`)

**`AiTokenPricingObserver`**
- `creating` — вычислить `price_per_1m_input/output` по формуле, валидировать `margin_percent < 100`
- `updating` — если меняются ценовые поля напрямую → throw Exception (использовать `AiTokenPricing::updatePrice()`)

**`AiTariffPlanObserver`**
- `updating` — если изменился `price_monthly` → пересчитать `price_total` во всех активных `ai_tariff_plan_periods`

### 3.3. Методы версионирования

```php
// Изменение цены модели (SCD Type 2)
AiTokenPricing::updatePrice(array $newData, int $userId): AiTokenPricing
  → old->effective_to = now()
  → new record: effective_from = now(), effective_to = null

// Изменение скидки периода (SCD Type 2)
AiTariffPlanPeriod::updateDiscount(float $newDiscount, int $userId): AiTariffPlanPeriod
  → old->valid_to = yesterday
  → new record: valid_from = today, valid_to = null
```

### 3.4. Сервисы (`app/Services/Ai/`)

**`AiCrmFetchService`** — получение логов из CRM
```
Для каждой активной ai_subscriptions:
  GET https://{sub_domain}-back.{domain}/api/ai/token-logs
      ?since={last_crm_fetch_at}
  
  Маппинг полей CRM → ai_usage_raw_logs:
    crm.id            → crm_log_id
    crm.model_key     → model_name
    crm.prompt_tokens → prompt_tokens
    crm.prompt_cache_hit_tokens → prompt_cache_hit_tokens (та же цена)
    crm.completion_tokens → completion_tokens
    crm.created_at    → created_at
  
  Поиск цены: AiTokenPricing WHERE model_name = ? AND effective_to IS NULL
  Расчёт: calculated_cost = (prompt_tokens + cache_hit_tokens) / 1M * price_input
                           + completion_tokens / 1M * price_output
  Снапшоты: price_per_1m_input/output_snapshot, margin_percent_snapshot
  
  При дублях (crm_log_id уже есть) → пропустить (уникальный индекс)
  Обновить last_crm_fetch_at после успеха
  HTTP: Http::withHeaders(['Accept' => 'application/json'])
        (аналогично TariffExtensionJob, без токена)
  Логировать через IntegrationActionLogService
```

**`AiBillingService`** — 30-минутный расчёт
```
processOrganization(int $orgId): void
distributeDeduction(AiBalance $balance, float $amount): array
checkAndToggleAgent(AiBalance $balance, bool $wasPreviouslyEnabled): void
```

**`AiMonthlyService`** — цикл месяца
```
processEndOfMonth(): void
processStartOfMonth(): void
grantMonthlyLimit(AiSubscription $sub, bool $prorated): void
```

**`AiScheduledActivationService`** — отложенная активация
```
calculateScheduledActivation(AiBalance $balance, AiTariffPlan $plan): ?Carbon
checkAndActivate(AiBalance $balance): void
```

**`AiSubscriptionRegistryService`** — вызывается из KP-Listeners
```
register(CommercialOffer $offer, CommercialOfferStatus $status): void
  → Найти CommercialOfferAiItem для этого offer
  → Если нет AI-блока → выйти
  → Создать/продлить ai_subscriptions
  → Начислить limited_balance (пропорционально если середина месяца)
  → Записать транзакцию tariff_grant / tariff_grant_prorated
  → is_agent_enabled = true
  → AiAgentToggleJob(org, enabled=true)
```

### 3.5. Jobs (`app/Jobs/`)

**`FetchAiUsageLogsJob`** — обёртка над AiCrmFetchService для очереди

**`ProcessAiBillingJob`** — обёртка над AiBillingService::processOrganization()

**`AiAgentToggleJob`** — включить/выключить агент в CRM
```php
// Аналог TariffExtensionJob
Http::withHeaders(['Accept' => 'application/json'])
    ->post("https://{sub_domain}-back.{domain}/api/ai/agent-toggle", [
        'enabled' => $this->enabled,
        'b_organization_id' => $this->organization->id,
    ]);
// Логировать через IntegrationActionLogService (action: 'ai_agent_toggle')
// dispatchSync (синхронно, как TariffExtensionJob)
```

### 3.6. Commands (`app/Console/Commands/`)

```
AiFetchAndBillCommand     app:ai-fetch-and-bill        — каждые 30 мин
AiEndOfMonthCommand       app:ai-end-of-month          — конец месяца
AiStartOfMonthCommand     app:ai-start-of-month        — начало месяца
AiCheckScheduledCommand   app:ai-check-scheduled       — ежедневно
```

---

## 4. Интеграция с существующим КП-потоком

### 4.1. Форма КП — новый AI-блок

В форме КП (все типы: connection, renewal, renewal_no_changes, connection_extra_services) добавляется **отдельный блок «AI-тариф»**:
- Выбор тарифного плана (`ai_tariff_plans.is_active = 1`)
- Выбор периода (1/3/6 мес.) — показывает цену с учётом текущей скидки
- Авторасчёт итоговой цены
- Партнёрский процент
- При сохранении → запись в `commercial_offer_ai_items`
- Сумма AI-блока прибавляется к `CommercialOffer.grand_total`

### 4.2. Обработка в Listeners

Все 4 Listener'а (`CommercialOfferPaidStatusListener`, `CommercialOfferRenewalPaidStatusListener`, `CommercialOfferRenewalNoChangePaidStatusListener`, `CommercialOfferExtraServicesPaidStatusListener`) получают новый вызов:

```php
$this->aiSubscriptionRegistryService->register($offer, $event->status);
```

**`AiSubscriptionRegistryService::register()`** внутри:
1. Проверяет `CommercialOfferAiItem` для данного offer — если нет, выходит
2. Создаёт/продлевает `ai_subscriptions`
3. Начисляет `limited_balance` (пропорционально)
4. Пишет `ai_balance_transactions` (tariff_grant / tariff_grant_prorated)
5. Устанавливает `is_agent_enabled = true`
6. Диспатчит `AiAgentToggleJob(org, enabled=true)` — синхронно

> **Важно:** ClientBalance (income) для AI-части НЕ пишем отдельно.
> Существующий `ClientBalanceRegistryService` уже считает `grand_total` КП,
> в которое входит AI-блок. Так финансовая отчётность сходится автоматически.

### 4.3. EventServiceProvider

Добавить `AiSubscriptionRegistryService` в constructor всех 4 Listener'ов.

---

## 5. CRM-интеграция

### 5.1. Получение логов (AiCrmFetchService)

```
Endpoint: GET https://{sub_domain}-back.{domain}/api/ai/token-logs?since={timestamp}
Auth: Http::withHeaders(['Accept' => 'application/json']) (как в существующих Jobs)
Параметр b_organization_id передаётся если CRM требует

Формат ответа CRM (ai_token_logs):
  id, sales_funnel_id, chat_id, lead_id, model_key,
  prompt_tokens, prompt_cache_hit_tokens, completion_tokens,
  input_cost, output_cost, total_cost, created_at

Примечание: total_cost из CRM НЕ используем — пересчитываем по нашим ценам
```

### 5.2. Управление агентом (AiAgentToggleJob)

```
Endpoint: POST https://{sub_domain}-back.{domain}/api/ai/agent-toggle
           (ЗАГЛУШКА — эндпоинт разрабатывается на стороне CRM)
Payload: { enabled: true/false, b_organization_id: org.id }
Auth: Http::withHeaders(['Accept' => 'application/json'])
Логировать: IntegrationActionLogService (action: 'ai_agent_toggle')
```

### 5.3. Часовой пояс

Все расчёты границ месяца — в `Asia/Dushanbe`.
`started_at` / `expires_at` / `effective_from` / `effective_to` — хранить в UTC.
Границы месяца вычислять с явным `now()->timezone('Asia/Dushanbe')`.

---

## 6. Cron-расписание

Добавить в `app/Console/Kernel.php`:

```php
// Fetch логов из CRM + биллинг (каждые 30 минут)
$schedule->command('app:ai-fetch-and-bill')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Сгорание limited_balance в конце месяца
$schedule->command('app:ai-end-of-month')
    ->monthlyOn(31, '23:59')    // Дополнительно проверять is_last_day_of_month внутри команды
    ->withoutOverlapping();

// Начисление лимита в начале месяца
$schedule->command('app:ai-start-of-month')
    ->monthlyOn(1, '00:01')
    ->withoutOverlapping();

// Проверка отложенных активаций
$schedule->command('app:ai-check-scheduled')
    ->dailyAt('09:00')
    ->withoutOverlapping();
```

> **Примечание для end-of-month:** команда внутри проверяет
> `Carbon::now('Asia/Dushanbe')->isLastOfMonth()` — иначе не сработает
> в месяцах с 28/29/30 днями.

---

## 7. Admin UI

### 7.1. `/admin/ai-tariffs` — Тарифы ИИ

**Список тарифов:**
- Таблица: название, цена/мес, валюта, лимит, статус, действия
- Кнопка «Создать тариф»

**Форма создания/редактирования тарифа:**
- Название, `price_monthly`, `currency_id`, `included_limit_balance`, `is_active`
- Блок «Периоды и скидки»:
  - Строки 1 / 3 / 6 месяцев
  - Для каждого: скидка % → итоговая цена считается в реальном времени JS
  - `valid_from` (по умолчанию сегодня)
- Кнопка «История скидок» → таблица всех `ai_tariff_plan_periods` по плану

**Вкладка «Модели и цены»:**
- Таблица: провайдер, модель, себестоимость input/output, маржа %, цена input/output, с какой даты
- Инлайн-превью цены при изменении маржи (JS расчёт до сохранения)
- Кнопка «История цен» по каждой модели → все записи (effective_from → effective_to)

### 7.2. `/admin/ai-subscriptions` — Клиенты ИИ

**Список подписок:**
- Таблица: организация, клиент, тариф, период, статус, started_at, expires_at, is_agent_enabled
- Фильтры: по статусу, тарифу, дате окончания

**Детальная страница подписки / организации:**
- Текущий баланс: `limited_balance` + `ai_balance` + `is_agent_enabled`
- Таблица `ai_balance_transactions` (история движений)
- Таблица `ai_usage_logs` (история 30-мин расчётов)
- Дата отложенной активации (если есть)

### 7.3. Блок «AI-тариф» в текущих подключениях

На странице организации / в разделе «Доп. услуги» — отдельная карточка:
- Тарифный план, срок действия, дни до окончания
- `limited_balance` / `ai_balance`
- Статус агента (включён/выключен)

---

## 8. Порядок разработки по фазам

| Фаза | Задачи | Приоритет |
|------|--------|-----------|
| **1. Миграции** | 9 таблиц + индексы | Критично |
| **2. Модели + Observer** | Все модели, `AiTokenPricingObserver`, `AiTariffPlanObserver`, методы `updatePrice`/`updateDiscount` | Критично |
| **3. Admin UI тарифов** | `/admin/ai-tariffs` — CRUD с историей цен и скидок | Высокий |
| **4. AiSubscriptionRegistryService** | Регистрация подписки при оплате КП, блок в форме КП | Высокий |
| **5. EventServiceProvider** | Добавить AiSubscriptionRegistryService во все 4 Listener'а | Высокий |
| **6. AiCrmFetchService** | Fetch логов из CRM + запись в raw_logs | Высокий |
| **7. AiBillingService** | 30-мин расчёт (A→B→C), toggle агента | Высокий |
| **8. AiMonthlyService** | Конец/начало месяца, пропорциональное начисление | Высокий |
| **9. Commands + Kernel** | 4 команды, расписание | Средний |
| **10. AiAgentToggleJob** | Заглушка + логирование | Средний |
| **11. Admin UI подписок** | `/admin/ai-subscriptions` | Средний |
| **12. Блок в подключениях** | Карточка AI-тарифа на странице орг | Низкий |

---

## Решённые вопросы (итог)

| Вопрос | Решение |
|--------|---------|
| Cache hit токены | Та же цена что и обычные (Вариант A) |
| История цен | SCD Type 2 в ai_token_pricing (effective_from / effective_to) |
| История скидок | SCD Type 2 в ai_tariff_plan_periods (valid_from / valid_to) |
| CRM → Биллинг логи | Биллинг тянет у CRM каждые 30 мин (GET запрос) |
| Auth для CRM запросов | Http без токена, только Accept: application/json (как TariffExtensionJob) |
| Валюта тарифа | currency_id в ai_tariff_plans |
| КП тип для AI | connection_extra_services + проверка AI-блока в Listener'е |
| ConnectedClientServices для AI | Не нужно — есть ai_subscriptions |
| ClientBalance income | Считается автоматически через grand_total КП |
| PartnerExpense для AI | Через partner_percent в commercial_offer_ai_items |
| Продление подписки | started_at = expires_at + 1 день (не теряем дни) |
| ИИ-тариф и CRM-тариф | Полностью независимы |
| Часовой пояс | Asia/Tashkent для границ месяца |
| Начисление при мульти-периоде | Каждый месяц отдельно по cron |
| Валюта токен-прайсинга | cost_currency_id + price_currency_id в ai_token_pricing (обычно USD) |
| Валюта балансов | currency_id в ai_balances (= currency_id тарифного плана) |
| Валюта транзакций | currency_id в ai_balance_transactions (= валюта баланса) |
| Валюта ai_usage_logs | currency_id (= валюта баланса, суммы уже сконвертированы) |
| Конвертация USD → баланс | ExchangeRate при 30-мин расчёте если валюты различаются |
| commercial_offer_ai_items | Без currency_id — наследует из CommercialOffer.currency |
