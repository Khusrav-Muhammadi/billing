# Движок чат-агента (мессенджеры)

Источник фактов: код CRM `/var/www/Crm`, модуль входящего чата. Это не голосовой агент, не агент комментариев и не агент реактивации базы. Дожим молчания и напоминание о встрече описаны, потому что они шлют сообщения в тот же чат и читают те же настройки воронки.

Имя бота, компания и канал Instagram Direct в коде движка не зашиты. Текст «Малика» / President Business Gifts в этом репозитории не найден: его задаёт поле `ai_funnel_settings.system_prompt` (и соседние поля) конкретной воронки.

Локальная БД, доступная с этой машины (`localhost-back_tenant` на порту CRM MySQL), содержит таблицу моделей, но **0 строк** в `ai_funnel_settings` и **0 строк** в `ai_request_logs`. Живые диалоги этого бота отсюда не прочитаны.

---

## 1. Сборка системного промпта

### Где хранится

Таблица тенанта `ai_funnel_settings`. Модель `App\Models\AiFunnelSetting`.

Поля, из которых собирается текст:

| Поле | Куда попадает |
| --- | --- |
| `company_name` | первая строка роли |
| `company_description` | блок описания |
| `services` | блок услуг |
| `system_prompt` | блок «Инструкции для тебя» |
| `glossary` | словарь |
| `language` | язык каркаса (`ru` / `uz` / `tg` / `en`, иначе `ru`) |
| `meeting_mode` | какие правила и tools включены |
| `request_instruction` | доп. текст к правилу заявки |
| `enable_vision` | блок про фото |
| `enable_goods_lookup` | карточки товаров в динамической части |
| `pipeline_rules` | не в промпт ответа клиенту; отдельный job после ответа |
| `extracted_fields` | то же |
| `followup_settings` | не в промпт ответа; отдельный дожим |
| `work_time_from` / `work_time_to` | бот молчит вне окна; запасной график менеджера |

Каркас фраз лежит в файлах `lang/{ru,uz,tg,en}/ai-prompts.php`, ключи `chat.*` и `dynamic.*`. Метод `AiFunnelSetting::prompt()` → `AiPromptLocale::prompt()`.

Сборка: `AiConversationService::handleIncomingMessage` вызывает `AiPromptBuilder::buildParts`. Результат — два system-сообщения, не одна склейка в payload (склейка есть только в `AiPromptBuilder::build`, её чат-ход не использует).

### Статическая часть (`AiPromptBuilder::buildStaticPrompt`)

Порядок, если поле непустое:

1. `chat.assistant_named` с `:name` = `company_name`, иначе `chat.assistant_unnamed`.
2. `chat.company_description` с `:text`.
3. `chat.services` с `:text`.
4. `chat.instructions` с `:text` = `system_prompt`.
5. `chat.glossary` с `:text`.
6. Всегда `chat.other_capabilities`.
7. Всегда `chat.no_hallucination`.
8. Всегда `chat.call_manager_rules`.
9. `chat.kp_rules` только если `tenant('id')` входит в список `sham-new-back`, `tajikistan-new-back`, `khnodiraaaicloudcom-back`. Для остальных тенантов этого блока нет.
10. `chat.update_lead_info`.
11. `chat.notice_header` и дальше:
    - если режим не `requests`: `chat.notice_meeting`;
    - всегда `chat.notice_callback`.
12. Если `meeting_mode = meetings`: `chat.check_slots`.
13. Если `meeting_mode = requests`: `chat.create_request` и, если задано, `chat.create_request_extra` с `:text` = `request_instruction`.
14. Если `enable_vision`: `chat.vision`.

`meeting_mode` (`AiFunnelSetting::meetingMode`): `off` | `meetings` | `requests`. Если в колонке мусор, берётся старый флаг `enable_meetings`.

Строку «пиши клиенту только на языке X» код не добавляет. Комментарий в `buildStaticPrompt`: это должен написать человек в `system_prompt`.

### Динамическая часть (`AiPromptBuilder::buildDynamicPrompt`)

Всегда, ключи `dynamic.*`:

- заголовок «данные о текущем клиенте»;
- имя лида или «Клиент»;
- `lead.description` (может быть пустой строкой);
- телефон или «Не указан»;
- email или «Не указан»;
- текущие дата и время `Y-m-d H:i` в поясе менеджера (`ManagerReplyEta::now`);
- блок «когда ответит менеджер» (`ManagerReplyEta::promptBlock`): часы, пояс, статус «рабочее / нерабочее», готовая фраза клиенту.

Часы менеджера: `localizations.start_time` / `end_time` воронки, иначе организации, иначе `work_time_from` / `work_time_to` агента, иначе заглушка `09:00–18:00` и флаг «часы неизвестны» (тогда статус всегда «рабочее время»). Рабочий день проверяет `Localization::isWorkday`. Графика филиалов в этом блоке нет.

Если у лида есть `manager_id`:

- менеджер уже писал в чат как человек → `dynamic.manager_replied`;
- только назначен в карточке → `dynamic.manager_assigned_only`.

Если у чата есть рекламная кампания или `chats.referral_body`: название, описание, источник, текст referral и правило не переспрашивать оффер.

До 10 последних `notices` лида: статус, заголовок, дата, body, conclusion, report.

Если по лиду есть активный прогон реактивации: блок кампании (`CampaignContextProvider::promptBlock`) — причина отказа, оффер, дедлайн, цель, ограничения агента. Это не чат-tool, а контекст другого модуля.

Дальше RAG (`buildRagPrompt`) и каталог (п. 2) и блок плохих оценок (`MessageRating::promptBlock`).

`CountryDetector::detect` вызывается и передаётся в `buildParts` аргументом `$likelyCountry`. Внутри `buildDynamicPrompt` этот аргумент не читается. Страна в текст промпта не подставляется.

### Плейсхолдеры каркаса

В `lang/ru/ai-prompts.php` для чата используются: `:name`, `:text`, `:n`, `:value`, `:from`, `:to`, `:tz`, `:status`, `:title`, `:date`, `:score`, `:question`, `:answer`, `:price`, `:article`, `:slots`.

Значения берутся из полей настройки, лида, чата, локализации, `Notice`, `ChatExample`, `Good` — не из произвольного шаблона менеджера. Отдельного движка `{{переменных}}` внутри `system_prompt` в коде не найдено: текст инструкции вставляется как есть.

### Что ещё уходит в модель, но не внутри system

История: последние 20 сообщений чата (`latest()->take(20)`), заметки `is_note` пропускаются. Роль `user`, если `AiChatHistoryFormatter::isLeadMessage`, иначе `assistant` (и бот, и менеджер). Текст медиа подменяется подписями из `history.*` (голос, фото, файл, разбор рилса).

Tools — см. п. 3. Они не дописываются в system-текст, а идут полем `tools` запроса.

### Структура запроса

`AiConversationService::handleIncomingMessage`, массив `$messagesForLlm`:

1. `system` — статическая часть.
2. `system` — динамическая часть, если она не пустая.
3. `user` / `assistant` — история.
4. После ответа модели с `tool_calls`: сообщение ассистента целиком (включая `tool_calls`; при мышлении DeepSeek ещё `reasoning_content`), затем `role: tool` с `tool_call_id` и текстом результата.
5. Если модель вернула `[WAIT_FOR_MANAGER]` в тот же ход, где уже проверяла слоты, и встречу ещё не создала: это сообщение кладётся как assistant, следом `user` с текстом `history.wait_booking_nudge`, и запрос повторяется.

Обезличенный пример (режим `meetings`, каталог выключен, рекламы нет):

```json
{
  "model": "<key из ai_models>",
  "temperature": 0.7,
  "tool_choice": "auto",
  "messages": [
    {
      "role": "system",
      "content": "Ты — AI-ассистент компании '<company_name>'.\n\nОписание компании: <company_description>\n\nИнструкции для тебя:\n<system_prompt>\n\n...жёсткие правила call_manager / create_notice / update_lead_info..."
    },
    {
      "role": "system",
      "content": "[РАЗДЕЛ: ДАННЫЕ О ТЕКУЩЕМ КЛИЕНТЕ]\nИмя клиента: Клиент\nОписание клиента: \nТелефон: Не указан\nEmail: Не указан\nТекущая дата и время: 2026-10-07 09:40\n\n[КОГДА ОТВЕТИТ МЕНЕДЖЕР]\nРабочее время менеджеров: 09:00–18:00, пояс Asia/Tashkent.\nСейчас: рабочее время.\nФраза клиенту при передаче менеджеру: \"Понял, передаю вас специалисту, он ответит в течение 5 минут.\"\n..."
    },
    { "role": "user", "content": "Здравствуйте, сколько стоит?" },
    { "role": "assistant", "content": "Какой товар смотрите?" },
    { "role": "user", "content": "Напишите завтра в 11" }
  ]
}
```

Ответ с tool (следующий шаг того же хода):

```json
{
  "role": "assistant",
  "content": "Хорошо, напишу вам завтра в 11:00.",
  "tool_calls": [
    {
      "id": "call_1",
      "type": "function",
      "function": {
        "name": "create_notice",
        "arguments": "{\"title\":\"Повторный контакт по просьбе клиента\",\"date\":\"2026-10-08 11:00:00\",\"body\":\"Клиент просил написать завтра в 11.\"}"
      }
    }
  ]
}
```

```json
{
  "role": "tool",
  "tool_call_id": "call_1",
  "content": "OK: встреча создана в системе."
}
```

Цикл tool-вызовов — до 4 попыток (`$maxFunctionAttempts`). Неизвестное имя функции не получает `role: tool`: ветки `else` нет.

---

## 2. Карточка товара

Включается только если `enable_goods_lookup` истинен. Иначе блок не строится.

Класс `App\Services\AiAgent\Catalog\CatalogContextBuilder::forChat`. Ошибка построения глотается, в промпт уходит пустая строка.

Формат — обычный текст, не JSON. Метод `format`.

Порядок выбора товаров (`build`):

1. Если клиент просит примеры (`CatalogExamplePicker::forChat`) — до 3 товаров с фото в нужной категории и бюджете. К тексту дописывается `dynamic.catalog_photos`. После ответа бота `CatalogPhotoSender::send` шлёт подпись и файл. Это не tool модели.
2. Иначе поиск по тексту последних реплик и разбору фото (`ProductCardFinder::rank`, порог score ≥ 5, лимит 3). Явный победитель: score ≥ 12 и второй < 5 → одна карточка.
3. Иначе «замок» по рилсу/истории: `InstagramMediaRef::fromMessage` + `ai_media_memories.good_ids` (`lockedGoods`).
4. Иначе все найденные (до 3). Если ранг пуст — блока нет.

Откуда берётся привязка медиа (`InboundMediaService::describe`, job `DescribeImageMessageJob`):

- обычное фото (`PendingVision::isImage`) → `AiMediaService::describeImage`, текст в `messages.image_description`;
- рилс / пост / история (`InstagramMediaRef`) → скачивание кадра или ролика (`InstagramMediaFetcher::fetch`, permalink через oEmbed thumbnail), описание vision, запись в `ai_media_memories`. `good_ids` пишется только если есть единственный победитель поиска по тексту описания. Иначе `good_ids = null`.

Ручного выбора товара менеджером или моделью в коде не найдено. Отдельного tool «прикрепить товар» нет.

Поиск имени: `ProductCardFinder`. Стоп-слова выкидываются из токенов названия: `часы`, `набор`, `кожа`, `фарфор`, `цена`, `подарок`, `сувенир`, `президент`, `soat`, `narxi`, `watch`, `gift`, `настольные`, `наручные`. Совпадение артикула (длина ≥ 3) даёт score 15. Каталог читается до 400 активных `goods` организации.

Примеры (`CatalogExamplePicker`) срабатывают на слова вроде «пример», «покажи», «фото», «variant» и на короткое «да» после того, как бот сам предложил примеры. Группы зашиты в коде: часы, фарфор, кожа, ручки, медали. Бюджет парсится только из суммы с `$` / `usd` / `долл`.

### Поля карточки

Из `goods` и атрибутов (`CatalogContextBuilder::format`):

- название `goods.name`;
- цена: `goods.price` числом + первый атрибут, в имени которого есть подстрока `валют` (без учёта регистра). Если цены нет — фраза «в карточке не указана»;
- артикул `goods.article`, если не пуст;
- описание `goods.description`, обрезка 400 символов;
- до 12 атрибутов `имя: значение` (значение до 180 символов). Атрибут с «валют» в имени в список характеристик не дублируется.

`goods.quantity` (остаток) в карточку не попадает. Отдельных полей «цвет», «механизм», «размер», «комплектация», «наличие», «медиа» в форматтере нет. Они появятся только если так назван атрибут товара в CRM. Ссылки на фото в текст карточки не кладутся.

Обезличенный пример того, что реально собирает `format` (значения выдуманы как иллюстрация полей, не сняты из БД):

```text
[КАРТОЧКИ ТОВАРОВ ЭТОГО ЗАПРОСА]
Цена и характеристики только из этих карточек. Если карточка одна — можно назвать её цену. Если карточек несколько — цену не называй, уточни какой товар. Не выдумывай цену, которой здесь нет.
Товар: <name>
Цена: 120 USD
Артикул: <article>
Коротко: <description до 400 символов>
- Цвет: <value атрибута>
- Механизм: <value атрибута>

```

Если сработал подбор примеров, в конец добавляется абзац `dynamic.catalog_photos`: фото уйдут после текста, сначала название и цена, потом снимок, без ссылки.

Несколько карточек: все печатаются подряд. Правило в тексте запрещает называть цену, пока товар не один. Код цену при этом всё равно подставляет в каждую карточку.

Карточки нет / товар не найден: метод возвращает `''`, блока в промпте нет. Отдельного сообщения «товар не найден» модель не получает.

Цена для ответа клиенту в коде берётся только из этой карточки (и из того, что человек написал в `system_prompt` / `services` / описании). Отдельного прайс-API нет. Наличие как остаток `quantity` боту не передаётся. Свободные слоты встреч к складу не относятся.

---

## 3. Инструменты бота

Список собирается в `AiConversationService::handleIncomingMessage`. Описания — `lang/*/ai-prompts.php`, ключи `tools.*`. `tool_choice` сначала `auto`.

Модель не видит исключений PHP. В `role: tool` уходит готовая строка. Для части tools строка «OK» ставится до реальной записи в БД.

### `call_manager`

- Параметры: пустой объект, обязательных нет.
- Описание: передать диалог человеку (просьба человека, жалоба, оплата, ручная работа).
- В ответ модели сразу: `history.tool_call_manager_ok` («OK: диалог будет передан менеджеру.»).
- Потом, уже после цикла, `AiToolExecutor::executeCallManager`: назначить менеджера, если его не было; уведомление CRM `type=ai_paused`; push FCM. Чат **не** ставится на паузу этим методом.
- Ошибка push пишется в лог. Модели об ошибке не сообщается. Ветки «не удалось» нет.

### `generate_commercial_proposal`

Только три tenant id (см. п. 1). Иначе функции нет, вызов игнорируется.

Обязательные: `tariff_name` (enum BASE, STANDART, PREMIUM, VIP, UCHET, «Тариф учет»), `period_months` (6 или 12), `user_count` (integer), `currency` (TJS, UZS, USD).

Необязательные: `requested_modules` (array of string), `extra_funnels` (integer), `implementation` (boolean), `implementation_discount_percent` (integer), `include_ai_agent` (boolean), `ai_plan` (enum START/PREMIUM/VIP × DEEPSEEK/CHATGPT), `ai_extra_months` (0–6), `ai_balance_topup` (number), `ai_gift_promo_used` (boolean).

Исполнение: `AiToolExecutor::generateCommercialProposal` → `CommercialProposalService::generate`. Модели: `history.tool_kp_ok` или `history.tool_kp_fail`. Текст ошибки генерации в tool-ответ не кладётся.

Это КП shamCRM (тарифы CRM), не каталог подарков.

### `create_notice`

Обязательные string: `title`, `date` (`YYYY-MM-DD HH:MM:SS` по описанию), `body`.

Модели сразу: `history.tool_notice_ok`. Запись — после цикла, `executeCreateNotice`. Без менеджера или лида запись не создаётся, исключение только в лог. Модель уже получила OK.

Побочные эффекты: round-robin менеджера; предыдущие незакрытые AI-события того же типа удаляются (`deleteReplacedAiNotices`: «Повторный контакт» не стирает встречу и наоборот); `deal_id` = последняя сделка лида, если есть; `lead_status_id` меняется, только если в `pipeline_rules.on_notice_created` задан id статуса.

### `mark_do_not_contact`

Обязательный `reason` (string).

`executeMarkDoNotContact` → `AgentRunResponder::handleOptOut`: `leads.do_not_contact=true`, причина, время, останов активных прогонов базы.

Модели: `history.tool_opt_out_ok` или `history.tool_opt_out_fail`.

### `update_lead_info`

Обязательные: `key_facts` (string), `deal_probability` (integer, в коде режется в 0–100). Необязательный `probability_reason`.

Пишет в `leads.description` **новый** текст (старое описание затирается): вероятность с меткой и блок «ключевая информация». В `leads.data`: `deal_probability`, `deal_probability_reason`, `deal_probability_updated_at`.

Успех: `history.tool_lead_ok`. Неудача (`false`): всё равно строка `history.tool_lead_ok_short` («OK: обновлено.»). Отдельной ошибки для модели нет.

### `create_request`

Только `meeting_mode = requests`. Обязательные `title`, `body`.

Модели сразу OK (`history.tool_request_ok`). Потом `executeCreateRequest`: событие на «сейчас», менеджер, push не описан в этом методе (создаётся Notice). Пустой title заменяется на «Новая заявка от клиента». Старые заявки не удаляются. Статус лида — если задан `pipeline_rules.on_request_created`. Нет менеджера или лида: warning в лог, модели уже сказано OK.

### `check_available_slots`

Только `meeting_mode = meetings`. Обязательный `date` (string).

`AiToolExecutor::checkAvailableSlots` возвращает массив часов `HH:00`. В tool-текст: `history.slots_result` или `history.slots_none`.

Пустой список и при выходном, и при занятом дне, и при неразобранной дате, и если менеджеров нет. Отдельного кода ошибки нет.

Слоты: локализация воронки/организации, `start_time`–`end_time` (иначе 09:00–18:00), шаг 1 час, прошедшие часы сегодня отбрасываются, занятость по незакрытым `notices` менеджера. Если менеджер уже на лиде — смотрится только он, иначе пул round-robin.

### Чего модель вызвать не может

| Ожидание | Факт |
| --- | --- |
| Отправить фото | Нет tool. Да, побочно: `CatalogPhotoSender::send` после текстового ответа, если включён каталог и `CatalogExamplePicker` нашёл 1–3 товара с картинкой. Подпись: «название — цена валюта». |
| Отправить видео | Нет. |
| Отправить каталог файлом / PDF каталога | Нет. PDF есть только у `generate_commercial_proposal` и только на трёх tenant CRM. |
| Геолокация | Нет. |
| Создать лид | Нет. Лид уже есть к моменту ответа. |
| Обновить лид | Да, через `update_lead_info` (описание и вероятность) и через `mark_do_not_contact`. Прочие поля — не tool, а `ProcessAiAutomationJob` (п. 4). |
| Создать или обновить сделку | Нет. В событие только подставляется id последней сделки. |
| Записать визит | Отдельной сущности визита нет. Встреча — это `create_notice` (запись `notices`). |
| Проверить наличие товара | Нет. Остаток `goods.quantity` в промпт не кладётся. |
| Проверить свободное время | Да, через `check_available_slots`, только в режиме встреч. |
| Сменить этап воронки | Нет tool. Код меняет `lead_status_id` сам: `pipeline_rules.on_notice_created`, `on_request_created`, и после ответа `ProcessAiAutomationJob::evaluatePipelineRules`, если в настройках есть `custom_rules` и у клиента уже ≥ 2 не-бот сообщений. |
| Назначить менеджера | Нет tool. Назначение — побочный эффект `RoundRobinManagerAssigner` внутри `call_manager`, `create_notice`, `create_request`, лимита из 3 ответов бота и падения job. |
| Поставить задачу | Да, как `notices` через `create_notice` / `create_request`. Отдельного task-tool нет. |
| Поставить тег | Нет. |

Как бот узнаёт итог: только строка в `role: tool` на следующем шаге того же запроса. Успех `call_manager` / `create_notice` / `create_request` не означает, что запись в БД прошла. После всех tools модель вызывается снова (`tool_choice` `auto`, пока не созданы notice/request и попытки не кончились; иначе `none`), и уже её `content` уходит клиенту.

---

## 4. Передача менеджеру

Технически это tool `call_manager`, не тег и не смена статуса.

Триггеры в промпте (`chat.call_manager_rules`), не в коде классификатора:

1. клиент просит человека;
2. вопрос, на который нет данных в базе знаний или услугах;
3. жалоба или агрессия;
4. готовность к сделке, счёт, договор.

Код не проверяет эти условия. Если модель не вызвала tool, передачи нет.

Что делает `executeCallManager`:

- `assignManagerIfMissing`: если `lead.manager_id` пуст — следующий менеджер из `ai_funnel_settings.managers` (`RoundRobinManagerAssigner::assignNext`), запись `manager_id` и участник чата;
- `notifications` с `type = ai_paused`, текст из `lang/*/ai.php` ключ `paused_body` (имя клиента);
- push тому же менеджеру.

`chats.is_ai_paused` здесь не ставится.

После передачи в этом ходе бот ещё может отправить текст (модель просят сначала написать фразу из блока «когда ответит менеджер»). Если в финальном тексте есть подстрока `[WAIT_FOR_MANAGER]`, сообщение клиенту не отправляется (`handleIncomingMessage`, ранний `return`). Следующее входящее снова запустит агента, пока пауза не включена другим путём.

Пауза «человек отвечает» включается, когда в чат пишет пользователь CRM: `Message::boot` и `MessageObserver` (sender `User`, не бот, не AI). Также пауза при 3 ответах бота подряд без реплики клиента (`isBotLimitExceeded`) и при окончательном падении `GenerateAiResponseJob::failed`.

Поля лида, которые этот ход умеет заполнить сам (`Lead::$fillable` шире; бот пишет не всё):

- `description` и `data.deal_probability*` — tool `update_lead_info`;
- `do_not_contact`, `do_not_contact_reason`, `do_not_contact_at` — tool `mark_do_not_contact`;
- `manager_id` — побочный эффект назначения;
- `lead_status_id` — правила воронки, не свободный выбор модели;
- кастомные поля, справочники и колонки `leads`, перечисленные в `extracted_fields` — `ProcessAiAutomationJob::extractCustomFields` отдельным запросом к модели (жёсткий JSON, не tool чата). Модель `AiModel::find(2)`, temperature 0.1. Срабатывает только если правила или поля заданы и у клиента ≥ 2 сообщений не от бота.

Сделку бот не создаёт. У сделки в этом ходе отдельных заполняемых полей нет: в `NoticeDTO` кладётся `deal_id` последней сделки или null.

Какие статусы и какие кастомные поля включены — строки `pipeline_rules` / `extracted_fields` конкретной воронки. В локальной БД настроек нет, состав полей President Business Gifts в коде не найден.

---

## 5. Контекст клиента и диалога

В промпт ответа клиенту попадают:

- имя, описание, телефон, email лида;
- назначенный менеджер и факт, писал ли он в чат;
- реклама: `advertising_campaigns.name`, `description`, `source`; `chats.referral_body`;
- до 10 событий `notices`;
- блок кампании реактивации, если прогон активен;
- до 2 примеров `chat_examples` той воронки (эмбеддинг вопроса, косинус > 0.7, кэш 24 часа);
- карточки товаров (п. 2);
- до плохих оценок менеджеров по воронке (`message_ratings`, цитата до 120 символов, кэш 10 минут);
- дата/время и график менеджеров (п. 1).

В промпт **не** подставляются, хотя колонки у лида есть: `insta_login`, `tg_nick`, `tg_id`, `wa_name`, `facebook_id`, id лида, id чата, канал интеграции, язык клиента, страна (`likelyCountry` выбрасывается). Язык каркаса — `ai_funnel_settings.language`, не язык реплики.

История: 20 последних сообщений, без суммаризации переписки. Отдельной долгой памяти диалога нет. `AiClientBriefService` строит справку по запросу API менеджера (`ChatAiController::clientBrief`) и в ход ответа клиенту не входит.

Пояс: `localizations.timezone` воронки, иначе организации, иначе `config('app.timezone')`, иначе `UTC` (`ManagerReplyEta::safeTimezone`). Графика нескольких филиалов в коде не найдено. Один интервал часов и признак рабочего дня локализации.

---

## 6. Параметры модели и обработка вывода

Модель не зашита. `ai_funnel_settings.ai_model_id` → строка `ai_models`: `key`, `api_url`, `provider`. Провайдеры enum `AiProvider`: `deepseek`, `openai`, `anthropic`, `gemini`. Ключ API берётся у провайдера (`AiProvider::apiKey`). Если ключа нет, запрос не уходит и на другого провайдера не подменяется.

В локальной таблице `ai_models` лежат ключи: `deepseek-v4-pro`, `deepseek-v4-flash`, `gpt-5`, `gpt-5-mini`, `gpt-4o-mini`, `gpt-4o`, `gemini-flash-latest`, `gemini-2.0-flash`, `gemini-1.5-flash`, `gemini-1.5-pro`, `gemini-3.6-flash`, `gemini-3.7-flash`. Какая из них стоит у конкретной воронки — в этой БД не найдено (настроек воронок нет).

Температура: `ai_funnel_settings.temperature`, если null — `0.7`. Для моделей, чьё имя начинается с `gpt-5` или `o`+цифра, `LlmClient::applyProviderDefaults` удаляет `temperature` из тела.

`max_tokens` в ходе ответа клиенту не задаётся. Таймаут HTTP — 300 секунд (`LlmClient::DEFAULT_TIMEOUT_SEC`), до 3 попыток с паузами 0 / 10 / 30 секунд.

DeepSeek (`AiFunnelSetting::deepSeekThinkingPayload`): `thinking_level` = `disabled` | `low` | `high` | `max`. Пустое или битое значение становится `high`. `disabled` → `thinking.type=disabled`. Иначе `thinking.type=enabled` и `reasoning_effort`. Для не-DeepSeek эти поля не добавляются. Если мышление включено, у старых реплик без `reasoning_content` клиент подставляет пустое поле.

Постобработка текста (`handleIncomingMessage`):

- вырезаются обрывки `<｜｜DSML｜｜...` и `<tool_call>...</tool_call>`;
- если на первом tool-шаге у модели уже был непустой `content`, клиенту уходит **он**, а не финальный ответ после tools;
- markdown не снимается;
- на несколько пузырей текст не режется;
- лимита длины ответа в коде нет;
- имитации набора нет.

Задержка до генерации: `RunAiAgentPipe` ставит job через 5 секунд, через 3 секунды если сценарий передал диалог агенту (`handoffToAi`). Это debounce, не «печатает…». Пока ждётся расшифровка голоса или разбор фото, `GenerateAiResponseJob` откладывается ещё на 3 секунды, не больше 6 попыток.

Если за время генерации клиент прислал новое сообщение или AI уже ответил на этот trigger — ответ выбрасывается.

Вложения:

- голос / audio (`voice`, `audio`, расширения ogg, mp3, wav, m4a, webm): цепочка `TranscribeVoiceMessageJob`, в историю «[Голосовое сообщение]» и текст `transcription`;
- фото при `enable_vision`: `DescribeImageMessageJob` → описание в `image_description`, в историю «[Изображение от клиента]» плюс подпись `text`;
- рилс, пост, история Instagram: `InstagramMediaRef` + `InboundMediaService`. В историю, если это не картинка-файл, строка «[Разбор фото, рилса или истории]» и исходный текст (у ответа на историю парсер дописывает «📱 Ответ на историю» и HTML-ссылку — `InstagramParser::formatStoryReply`);
- прочий файл без текста: «[Пользователь отправил файл]»;
- стикер как отдельный тип чат-агент не разбирает. В Green API `stickerMessage` может быть отфильтрован как реакция (`CreateMessagePipe::isGreenApiReactionPayload`). Для Instagram отдельной ветки стикера в `AiChatHistoryFormatter` нет;
- реакция, эхо, группа, комментарий: `RunAiAgentPipe` агента не запускает.

Если vision выключен, фото в историю попадает как «[Изображение от клиента]» без описания (если нет `image_description`).

---

## 7. Таймеры и поведение

### Дожим молчания

Команда `ai:followup-scan` каждые 30 минут (`app/Console/Kernel.php`). Включается только если `followup_settings.enabled` истинен.

Кто пишет: отдельный LLM-запрос в `AiFollowUpJob`, не tool основного ответа. Промпт `FollowUpPromptBuilder`, каркас `lang/*/ai-prompts.php` ключи `followup.*` и `touch.*`. Текст каждый раз генерируется. Жёсткого шаблона сообщения в коде нет. Доп. инструкция — `followup_settings.instruction`.

Когда: задержка попытки из `followup_settings.attempt_delays[]`, иначе `delay_hours`, иначе 12 часов (`FollowUpScheduleGuard::DEFAULT_DELAY_HOURS`). Часы отправки: `followup_time_from/to`, иначе часы локализации, иначе 09:00–20:00. Лимит попыток `max_attempts` обязателен, иначе job выходит. Чаты с `is_ai_paused`, `is_ai_followup_paused` или `ai_followup_finalized_at` не дожимаются.

Перед текстом скоринг JSON (`touch_type`: greeting, price, after_meeting, no_answer, thinking, already_client, skip). `skip` завершает волну без сообщения. Если клиент назвал срок — ждёт `promised_at`.

Окно Instagram / Facebook / WhatsApp: если с последнего сообщения клиента прошло больше 24 часов (`Chat::isSendWindowOpen(24)`), дожим не шлётся. Либо создаётся notice менеджеру (`create_notice_on_window_closed`), либо чат ставится на паузу.

После `max_attempts` — notice (`create_notice_on_max_attempts`) или пауза.

Ручное выключение дожима: `ChatAiController::setFollowUp` (`is_ai_followup_paused`).

### Напоминание о встрече

`ai:followup-scan --reminders-only` каждые 5 минут. `FollowUpMeetingNotice::isInReminderWindow`: за 30 минут до слота, за 60 если в тексте события офлайн-маркеры. Окно закрывается через 10 минут после начала (`LATE_GRACE_MINUTES`). Пишет тот же `AiFollowUpJob` отдельным промптом `followup.reminder`. Модель может вернуть `[SKIP]` — клиенту ничего не уходит. Пауза чата это напоминание не блокирует (проверка `is_ai_paused` пропускается, если передан id события).

### Вне графика бота

`AiFunnelSetting::isWithinWorkingHours`: если `work_time_from` и `work_time_to` пустые — бот работает всегда. Иначе текущее `H:i` в поясе локализации должно попасть в интервал (интервал через полночь поддержан). Вне окна `RunAiAgentPipe` job не ставит, `handleIncomingMessage` выходит. Автосообщения «мы закрыты» в коде нет.

Это окно бота. Окно живого менеджера для фразы «ответит через 5 минут / после начала дня» считается отдельно (`ManagerReplyEta`) и ночью боту отвечать не запрещает.

### Человек вместо бота

- сообщение сотрудника в чат → `is_ai_paused = true` (`Message` boot и `MessageObserver`);
- тумблер API `ChatAiController::setAi`: `enabled=false` → пауза;
- 3 сообщения бота подряд без реплики клиента → пауза, уведомление `ai_limit_exceeded`, назначение менеджера;
- необработанное падение генерации после ретраев job (backoff 30, 60, 100 секунд, `tries=10`) → пауза и уведомление `ai_failed`;
- сценарий с незакрытыми вопросами блокирует агента, пока нет `handoffToAi`;
- нет активной интеграции категории AI на воронке, канал не в `allowed_channels`, интеграция не в списке настройки — агент не стартует.

Пока `is_ai_paused`, `GenerateAiResponseJob` сразу выходит. Снять паузу кодом «само» после `call_manager` нельзя: только тумблер `setAi` с `enabled=true` или иная запись `is_ai_paused=false` (в `SendAgentMessageJob` пауза снимается при отправке сообщения агентом базы — это другой модуль).

---

## 8. Примеры диалогов

Реальных диалогов в доступной БД нет.

Проверено:

- тенант `localhost-back_tenant`: `ai_request_logs` = 0, `ai_funnel_settings` = 0;
- других `*_tenant` баз на этом MySQL нет;
- в `storage/logs/laravel.log` CRM записей `source: conversation` не найдено.

Где они должны лежать у живого тенанта:

- переписка: таблица `messages` (`text`, `is_created_by_ai`, `is_lead_message`, `transcription`, `image_description`);
- сырой запрос и ответ модели: `ai_request_logs` (`payload`, `response`, `source`). Для хода клиенту `source = conversation` (`LlmClient` пишет лог из контекста `AiConversationService`);
- токены: `ai_token_logs`;
- разбор рилса: `ai_media_memories`.

Вызовы tools в лог отдельной таблицей не пишутся. Они есть внутри `ai_request_logs.payload.messages` (assistant `tool_calls` и `role=tool`), если лог пишется. Текст аргументов и результатов — как в п. 3.

Подставлять вымышленный диалог «Малики» нельзя: настройки этой воронки здесь не прочитаны.

---

## 9. Ограничения, которые промпт не отменяет

Промпт может уговаривать модель вызвать tool. Он не может добавить tool, которого нет в списке п. 3, и не может включить `generate_commercial_proposal` или карточки товаров, если код/настройка их выключили.

Жёстко в коде:

- история ровно 20 сообщений, без сжатия;
- страна клиента в промпт не попадает, хотя детектор вызывается;
- ник Instagram, id, канал в промпт не попадают;
- вне `work_time_*` бот молчит и не шлёт заготовку;
- `[WAIT_FOR_MANAGER]` в финальном тексте глушит отправку этого ответа;
- три ответа бота подряд ставят паузу независимо от промпта;
- сообщение живого менеджера ставит паузу;
- `call_manager` не ставит паузу: следующий входящий снова пойдёт в модель, пока паузу не включит человек или лимит;
- OK по `call_manager` / `create_notice` / `create_request` выдаётся до записи; сбой БД модель не видит;
- `update_lead_info` перезаписывает `description` целиком;
- слоты только целые часы между start и end локализации; «нет слотов» смешивает выходной, ошибку даты и полную занятость;
- остаток товара модели не отдаётся;
- несколько карточек всё равно содержат цены, хотя текст правила просит цену не называть;
- фото уходят только из подбора примеров (`CatalogExamplePicker`), не по просьбе модели «отправь фото этого артикула»;
- группы примеров и стоп-слова поиска зашиты под подарки/часы (`CatalogExamplePicker`, `ProductCardFinder::STOPWORDS`);
- бюджет примеров понимается только в USD;
- КП-tool и правила КП есть только у трёх tenant id и описывают тарифы CRM, не витрину подарков;
- один текстовый пузырь, без нарезки, без задержки «печатает», без снятия markdown;
- если вместе с tool на первом шаге был текст, клиент получит его, а не текст после результата tool;
- дожим и напоминание о встрече — другие промпты (`followup.*`), их нельзя заменить абзацем в `system_prompt` основного ответа;
- смена этапа и запись кастомных полей — отдельная модель (`AiModel` id 2) по `pipeline_rules` / `extracted_fields`, не по тексту ответа клиенту;
- стикер, геоточка, видеофайл клиенту и исходящее видео в коде чат-агента не найдены;
- язык ответа клиента код не определяет и в запрос не кладёт.

Не найдено в коде чат-агента: корзина, скидка на товар каталога, промокод, оплата, трек доставки, бронирование конкретного изделия, несколько филиалов, ручной выбор карточки, суммаризация диалога, лимит токенов ответа.
