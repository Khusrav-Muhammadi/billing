@extends('layouts.app')

@section('title') Коммерческое предложение @endsection

@section('content')

    <script>
        window.CP_CONFIG       = @json($config);
        window.CP_CLIENTS      = @json($clients);
        window.CP_CLIENT_PRICES = @json($clientPrices); {{-- НОВОЕ --}}
            window.CP_META = {
            managerName: "{{ auth()->user()?->name }}",
            managerId:   {{ auth()->id() }},
            csrfToken:   "{{ csrf_token() }}",
            saveUrl:     "{{ route('application.kp.store') }}",
        };
        window.AI_TARIFF_PLANS = @json($aiTariffPlans ?? []);
    </script>

    <link rel="stylesheet" href="{{ asset('kp/styles.css') }}?v=2">

    <style>
        .kp-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: #1a1a2e;
        }

        /* ---- Заголовок страницы ---- */
        .kp-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #1a1a2e;
        }

        /* ---- Карточка-блок ---- */
        .kp-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .kp-card-title {
            font-size: 15px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
        }

        /* ---- Клиент select ---- */
        .kp-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            color: #1a1a2e;
            background: #fff;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            cursor: pointer;
        }

        .kp-select:focus {
            outline: none;
            border-color: #2B4BFF;
            box-shadow: 0 0 0 3px rgba(43,75,255,0.1);
        }

        .client-email-status {
            margin-top: 6px;
            font-size: 13px;
        }

        /* ---- Настройки: валюта + период ---- */
        .kp-settings-row {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
        }

        .kp-setting-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .kp-setting-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* ---- Кнопки валюты ---- */
        .currency-selector {
            display: flex;
            gap: 8px;
        }

        .currency-btn {
            padding: 7px 16px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
        }

        .currency-btn:hover {
            border-color: #2B4BFF;
            color: #2B4BFF;
        }

        .currency-btn.active {
            background: #2B4BFF;
            border-color: #2B4BFF;
            color: #fff;
        }

        /* ---- Кнопки периода ---- */
        .period-selector {
            display: flex;
            gap: 8px;
        }

        .period-btn {
            padding: 7px 18px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .period-btn:hover {
            border-color: #2B4BFF;
            color: #2B4BFF;
        }

        .period-btn.active {
            background: #2B4BFF;
            border-color: #2B4BFF;
            color: #fff;
        }

        .discount-badge {
            background: #dcfce7;
            color: #16a34a;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
        }

        .gift-badge {
            background: #16a34a;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 20px;
        }

        .period-btn.active .discount-badge {
            background: rgba(255,255,255,0.25);
            color: #fff;
        }

        .period-btn.active .gift-badge {
            background: rgba(255,255,255,0.3);
            color: #fff;
        }

        /* ---- Тарифы ---- */
        .kp-section-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1a1a2e;
        }

        .tariffs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        .tariff-card {
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 18px 20px;
            cursor: pointer;
            transition: all 0.15s;
            background: #fff;
            position: relative;
        }

        .tariff-card:hover {
            border-color: #2B4BFF;
            box-shadow: 0 4px 12px rgba(43,75,255,0.1);
        }

        .tariff-card.selected {
            border-color: #2B4BFF;
            background: #f0f3ff;
        }

        .tariff-card.popular::before {
            content: 'Популярный';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #2B4BFF;
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }

        .tariff-select-indicator {
            width: 18px;
            height: 18px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            margin-bottom: 10px;
            transition: all 0.15s;
        }

        .tariff-card.selected .tariff-select-indicator {
            border-color: #2B4BFF;
            background: #2B4BFF;
            box-shadow: inset 0 0 0 3px #fff;
        }

        .tariff-name {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a2e;
        }

        .tariff-price {
            display: flex;
            align-items: baseline;
            gap: 4px;
            flex-wrap: wrap;
        }

        .price-value {
            font-size: 22px;
            font-weight: 800;
            color: #1a1a2e;
        }

        .price-period {
            font-size: 13px;
            color: #6b7280;
        }

        .original-price {
            font-size: 13px;
            color: #9ca3af;
            text-decoration: line-through;
            margin-left: 4px;
        }

        /* ---- Доп. пользователи ---- */
        .extra-users-section {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .extra-users-control {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .users-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 14px;
            color: #374151;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }

        .qty-btn {
            width: 36px;
            height: 36px;
            border: none;
            background: #f9fafb;
            font-size: 18px;
            cursor: pointer;
            color: #374151;
            transition: background 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qty-btn:hover { background: #e5e7eb; }

        .qty-input {
            width: 56px;
            height: 36px;
            border: none;
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            text-align: center;
            font-size: 15px;
            font-weight: 600;
            color: #1a1a2e;
            background: #fff;
        }

        .qty-input:focus { outline: none; }

        /* ---- Услуги ---- */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 14px;
        }

        .service-card {
            border: 1.5px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px 18px;
            background: #fff;
            transition: all 0.15s;
        }

        .service-card.selected {
            border-color: #2B4BFF;
            background: #f0f3ff;
        }

        .service-card.included {
            border-color: #bbf7d0;
            background: #f0fdf4;
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .service-info h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .service-info p {
            font-size: 13px;
            color: #6b7280;
        }

        .service-toggle {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 24px;
            flex-shrink: 0;
        }

        .service-toggle input { opacity: 0; width: 0; height: 0; }

        .toggle-slider {
            position: absolute;
            inset: 0;
            background: #d1d5db;
            border-radius: 24px;
            cursor: pointer;
            transition: 0.2s;
        }

        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            left: 3px;
            top: 3px;
            background: #fff;
            border-radius: 50%;
            transition: 0.2s;
        }

        .service-toggle input:checked + .toggle-slider { background: #2B4BFF; }
        .service-toggle input:checked + .toggle-slider::before { transform: translateX(18px); }
        .service-toggle input:disabled + .toggle-slider { background: #86efac; cursor: default; }

        .service-channels {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .channels-label {
            font-size: 13px;
            color: #6b7280;
        }

        .channels-control {
            display: flex;
            align-items: center;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }

        /* ---- Итого ---- */
        .summary-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 20px;
        }

        .summary-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #1a1a2e;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .payments-table th,
        .payments-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #f3f4f6;
        }

        .payments-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }

        .payments-table .section-header {
            background: #eff6ff;
            color: #2B4BFF;
            font-weight: 700;
        }

        .summary-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 16px 0;
        }

        .summary-totals {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #374151;
        }

        .total-row.grand {
            font-size: 17px;
            font-weight: 800;
            color: #1a1a2e;
            padding-top: 8px;
            border-top: 2px solid #e5e7eb;
        }

        .total-value {
            font-weight: 700;
        }

        .period-details {
            font-size: 13px;
            color: #6b7280;
        }

        .total-value.monthly {
            font-size: 13px;
            color: #6b7280;
            font-weight: 400;
        }

        /* ---- Кнопка сохранить ---- */
        .actions-section {
            display: flex;
            justify-content: center;
            padding: 8px 0 32px;
        }

        .action-btn {
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }

        .action-btn.primary {
            background: #1a1a2e;
            color: #fff;
        }

        .action-btn.primary:hover {
            background: #2B4BFF;
        }

        .action-btn.secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        /* ---- Modals ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active { display: flex; }

        .modal-content {
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            max-width: 440px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }

        .success-modal {
            text-align: center;
        }

        .success-icon {
            width: 64px;
            height: 64px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #16a34a;
            margin: 0 auto 16px;
        }

        .success-modal h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .success-modal p {
            color: #6b7280;
            margin-bottom: 20px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 { font-size: 17px; font-weight: 700; }

        .modal-close {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #6b7280;
            line-height: 1;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .create-client-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 14px;
        }

        .create-client-label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .create-client-input {
            padding: 9px 12px;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            width: 100%;
        }

        .create-client-input:focus {
            outline: none;
            border-color: #2B4BFF;
        }

        /* ---- Loading ---- */
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
        }

        .loading-overlay.active { display: flex; }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e5e7eb;
            border-top-color: #2B4BFF;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ---- Footer ---- */
        .footer {
            text-align: center;
            padding: 16px;
            color: #9ca3af;
            font-size: 13px;
        }

        /* ---- Responsive ---- */
        @media (max-width: 600px) {
            .kp-settings-row { flex-direction: column; gap: 16px; }
            .tariffs-grid { grid-template-columns: 1fr 1fr; }
            .services-grid { grid-template-columns: 1fr; }
        }
    </style>

    <div class="kp-wrap">

        {{-- Клиенты --}}
        <div class="kp-card">
            <div class="kp-card-title">Список клиентов</div>
            <select id="clientEmailInput" class="kp-select">
                <option value="">— Выберите клиента —</option>
                @foreach($clients as $client)
                    <option
                        value="{{ $client['email'] }}"
                        data-name="{{ $client['name'] }}"
                        data-phone="{{ $client['phone'] ?? '' }}"
                        data-currency="{{ $client['currency'] ?? '' }}"
                    >
                        {{ $client['name'] }}
                        @if($client['email']) ({{ $client['email'] }}) @endif
                    </option>
                @endforeach
            </select>
            <div id="clientEmailStatus" class="client-email-status"></div>
        </div>

        {{-- Валюта + Период --}}
        <div class="kp-card">
            <div class="kp-settings-row">
                <div class="kp-setting-group">
                    <div class="kp-setting-label">Период оплаты</div>
                    <div class="period-selector" id="periodSelector">
                        <button class="period-btn active" data-months="6">6 мес</button>
                        <button class="period-btn" data-months="12">
                            12 мес
                            <span class="discount-badge">-15%</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Тарифы --}}
        <div class="kp-card">
            <div class="kp-section-title">Выберите тариф</div>
            <div class="tariffs-grid" id="tariffsGrid"></div>
        </div>

        {{-- Доп. пользователи --}}
        <div class="extra-users-section" id="extraUsersSection" style="display: none;">
            <div class="kp-section-title" style="margin-bottom: 14px;">Дополнительные пользователи</div>
            <div class="extra-users-control">
                <div class="users-info">
                    <span>В тариф включено: <strong id="includedUsers">0</strong> пользователей</span>
                    <span>Доп. пользователь: <strong id="extraUserPrice">0</strong>/мес</span>
                </div>
                <div class="quantity-control">
                    <button class="qty-btn minus" id="usersMinusBtn">−</button>
                    <input type="number" class="qty-input" id="extraUsersInput" value="0" min="0">
                    <button class="qty-btn plus" id="usersPlusBtn">+</button>
                </div>
            </div>
        </div>

        {{-- Дополнительные услуги --}}
        <div class="kp-card">
            <div class="kp-section-title">Дополнительные услуги</div>
            <div class="services-grid" id="servicesGrid">
                <div style="color: #9ca3af; font-size: 14px;">Выберите тариф для отображения услуг</div>
            </div>
        </div>

        {{-- ИИ-Агент --}}
        <div class="kp-card" id="aiTariffCard" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0;">
                <div class="kp-section-title" style="margin-bottom:0;">ИИ-Агент</div>
                <label class="service-toggle" style="margin-bottom:0;">
                    <input type="checkbox" id="aiEnabledCheckbox">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <div id="aiTariffBody" style="display:none;margin-top:20px;">

                {{-- Карточки планов --}}
                <div class="tariffs-grid" id="aiPlansGrid"></div>

                {{-- Период --}}
                <div id="aiPeriodRow" style="display:none;margin-top:20px;">
                    <div class="kp-setting-group">
                        <div class="kp-setting-label">Доп. месяцы (необязательно)</div>
                        <div class="period-selector" id="aiPeriodSelector"></div>
                    </div>
                </div>

                <div id="aiCurrentMonthRow" style="display:none;margin-top:20px;">
                    <div class="kp-setting-group">
                        <div class="kp-setting-label">Текущий месяц</div>
                        <p style="font-size:12px;color:#6b7280;margin:0 0 8px;line-height:1.4;">
                            Пропорция за оставшиеся дни текущего месяца.
                        </p>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <input type="number" id="aiCurrentMonthInput" min="0" step="0.01" value="0" readonly
                                   style="width:160px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;background:#f3f4f6;color:#111827;">
                            <span id="aiCurrentMonthCurrency" style="font-size:14px;color:#374151;font-weight:600;"></span>
                        </div>
                    </div>
                </div>

                <div id="aiBalanceTopupRow" style="display:none;margin-top:20px;">
                    <div class="kp-setting-group">
                        <div class="kp-setting-label">Баланс ИИ</div>
                        <p style="font-size:12px;color:#6b7280;margin:0 0 8px;line-height:1.4;">
                            Произвольное пополнение кошелька ИИ (необязательно).
                        </p>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <input type="number" id="aiBalanceTopupInput" min="0" step="0.01" value=""
                                   placeholder="0"
                                   style="width:160px;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;">
                            <span id="aiBalanceTopupCurrency" style="font-size:14px;color:#374151;font-weight:600;"></span>
                        </div>
                    </div>
                </div>

                {{-- ИИ-Агент отображается в общем итого --}}
            </div>
        </div>

        {{-- Итого --}}
        <div class="summary-card">
            <div class="summary-title">Итого</div>
            <div class="summary-content">
                <div class="summary-items" id="summaryItems"></div>
                <div class="summary-divider"></div>
                <div class="summary-totals">
                    <div class="total-row period">
                        <div class="period-details" id="periodDetails"></div>
                        <span class="total-value monthly" id="periodMonthlyTotal"></span>
                        <span class="total-value" id="periodTotal">0</span>
                    </div>
                    <div class="total-row grand">
                        <span>ОБЩАЯ СУММА:</span>
                        <span class="total-value" id="grandTotal">0</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Сохранить --}}
        <div class="actions-section">
            <button class="action-btn primary" id="saveBtn">Сохранить и оплатить</button>
        </div>

        <div class="footer"><p>© 2025 SHAMCRM</p></div>

    </div>

    {{-- Modals --}}
    <div class="modal-overlay" id="successModal">
        <div class="modal-content success-modal">
            <div class="success-icon">✓</div>
            <h3>КП успешно сохранено!</h3>
            <p>Коммерческое предложение сохранено в системе</p>
            <button class="action-btn primary" id="closeSuccessBtn">ОК</button>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p>Обработка...</p>
    </div>

    <div class="modal-overlay" id="createClientModal">
        <div class="modal-content create-client-modal-content">
            <div class="modal-header">
                <h3>Создать клиента</h3>
                <button class="modal-close" id="closeCreateClientModalBtn">×</button>
            </div>
            <div class="modal-body">
                <div class="create-client-form">
                    <label class="create-client-field">
                        <span class="create-client-label">ФИО</span>
                        <input type="text" id="newClientName" class="create-client-input" placeholder="Введите ФИО">
                    </label>
                    <label class="create-client-field">
                        <span class="create-client-label">Телефон</span>
                        <input type="text" id="newClientPhone" class="create-client-input" placeholder="+992...">
                    </label>
                    <label class="create-client-field">
                        <span class="create-client-label">Почта</span>
                        <input type="email" id="newClientEmail" class="create-client-input" placeholder="client@example.com">
                    </label>
                    <div id="newClientStatus" class="client-email-status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="action-btn secondary" id="cancelCreateClientBtn">Отмена</button>
                <button class="action-btn primary" id="saveCreateClientBtn">Сохранить</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="{{ asset('kp/app.js') }}?v={{ time() }}"></script>

    <script>
    // ── ИИ-Агент блок ──────────────────────────────────────────────────────
    (function () {
        const plans = window.AI_TARIFF_PLANS || [];
        const card  = document.getElementById('aiTariffCard');

        if (!plans.length) {
            if (card) card.style.display = 'none';
            return;
        }

        const checkbox       = document.getElementById('aiEnabledCheckbox');
        const body           = document.getElementById('aiTariffBody');
        const plansGrid      = document.getElementById('aiPlansGrid');
        const periodRow      = document.getElementById('aiPeriodRow');
        const periodSelector = document.getElementById('aiPeriodSelector');

        let selectedPlanId = null;
        let selectedMonths = null;

        // ── Цена только в валюте клиента; нет цены — 0 (без конвертации) ──
        const getAiPrice = (plan) => {
            const cpg = window.cpGenerator;
            const cur = cpg
                ? (typeof cpg.normalizeCurrencyCode === 'function'
                    ? cpg.normalizeCurrencyCode(cpg.state?.currency)
                    : String(cpg.state?.currency || '').toUpperCase())
                : '';
            if (!cur) return 0;
            const byC = plan?.prices_by_currency || {};
            const direct = byC[cur] ?? byC[String(cur).toLowerCase()] ?? byC[String(cur).toUpperCase()];
            const amount = Number(direct);
            return Number.isFinite(amount) && amount > 0 ? amount : 0;
        };

        const getAiCurrency = (_plan) => {
            const cpg = window.cpGenerator;
            if (!cpg) return '';
            if (typeof cpg.normalizeCurrencyCode === 'function') {
                return cpg.normalizeCurrencyCode(cpg.state?.currency) || '';
            }
            return String(cpg.state?.currency || '').toUpperCase();
        };

        // ── Обновление цен на карточках при смене валюты ──
        const refreshCardPrices = () => {
            plansGrid.querySelectorAll('.tariff-card').forEach(c => {
                const plan = plans.find(p => String(p.id) === String(c.dataset.planId));
                if (!plan) return;
                const bestPer = (plan.periods || []).slice().sort((a, b) => b.discount_percent - a.discount_percent)[0];
                const disc = bestPer && bestPer.discount_percent > 0
                    ? `<span class="discount-badge" style="margin-left:6px;">-${bestPer.discount_percent}%</span>` : '';
                const p = getAiPrice(plan), cur = getAiCurrency(plan);
                const el = c.querySelector('.ai-price-area');
                if (!el) return;
                el.innerHTML = p > 0
                    ? `<div class="tariff-price"><span class="price-value">${p}</span><span class="price-period"> ${cur}/мес ${disc}</span></div>`
                    : `<div style="font-size:12px;color:#9ca3af;margin-top:4px;">Цена не задана</div>`;
            });
            // Пересобрать CP_AI_ITEM без updateSummary (иначе стек переполнится).
            if (checkbox.checked && selectedPlanId) {
                setAiItem({ refreshSummary: false });
            }
        };

        // Хук для cpGenerator чтобы обновлять цены при updateSummary
        if (window.cpGenerator) {
            window.cpGenerator._refreshAiCardPrices = refreshCardPrices;
        } else {
            document.addEventListener('DOMContentLoaded', () => {
                if (window.cpGenerator) window.cpGenerator._refreshAiCardPrices = refreshCardPrices;
            });
        }

        // ── Рендер карточек тарифов ──
        plans.forEach(plan => {
            const c = document.createElement('div');
            c.className = 'tariff-card';
            c.dataset.planId = plan.id;

            const bestPer = (plan.periods || []).slice().sort((a, b) => b.discount_percent - a.discount_percent)[0];
            const disc = bestPer && bestPer.discount_percent > 0
                ? `<span class="discount-badge" style="margin-left:6px;">-${bestPer.discount_percent}%</span>` : '';
            const p = getAiPrice(plan), cur = getAiCurrency(plan);
            const priceHtml = p > 0
                ? `<div class="tariff-price"><span class="price-value">${p}</span><span class="price-period"> ${cur}/мес ${disc}</span></div>`
                : `<div style="font-size:12px;color:#9ca3af;margin-top:4px;">Цена не задана</div>`;

            c.innerHTML = `
                <div class="tariff-select-indicator"></div>
                <div class="tariff-name">${plan.name}</div>
                <div class="ai-price-area">${priceHtml}</div>`;

            c.addEventListener('click', () => {
                plansGrid.querySelectorAll('.tariff-card').forEach(x => x.classList.remove('selected'));
                c.classList.add('selected');
                selectedPlanId = plan.id;
                selectedMonths = null;
                renderPeriods(plan);
                renderSummary();
            });

            plansGrid.appendChild(c);
        });

        const currentMonthRow = document.getElementById('aiCurrentMonthRow');
        const currentMonthInput = document.getElementById('aiCurrentMonthInput');
        const currentMonthCurrency = document.getElementById('aiCurrentMonthCurrency');
        const topupRow = document.getElementById('aiBalanceTopupRow');
        const topupInput = document.getElementById('aiBalanceTopupInput');
        const topupCurrency = document.getElementById('aiBalanceTopupCurrency');

        const suggestCurrentMonth = (unit) => {
            const price = Number(unit) || 0;
            if (price <= 0) return 0;
            const cpg = window.cpGenerator;
            if (cpg && typeof cpg.suggestAiBalanceTopup === 'function') {
                return cpg.suggestAiBalanceTopup(price);
            }
            const now = new Date();
            const daysInMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate();
            const daysLeft = Math.max(1, daysInMonth - now.getDate() + 1);
            return +(price * (daysLeft / daysInMonth)).toFixed(2);
        };

        // ── Тумблер вкл/выкл ──
        checkbox.addEventListener('change', () => {
            body.style.display = checkbox.checked ? 'block' : 'none';
            if (!checkbox.checked) {
                selectedPlanId = null;
                selectedMonths = null;
                plansGrid.querySelectorAll('.tariff-card').forEach(c => c.classList.remove('selected'));
                periodRow.style.display = 'none';
                if (currentMonthRow) currentMonthRow.style.display = 'none';
                if (topupRow) topupRow.style.display = 'none';
                window.CP_AI_ITEM = null;
                if (window.cpGenerator && typeof window.cpGenerator.updateSummary === 'function') {
                    window.cpGenerator.updateSummary();
                }
            }
        });

        function renderPeriods(plan) {
            periodSelector.innerHTML = '';
            if (!plan.periods || !plan.periods.length) {
                periodRow.style.display = 'none';
                return;
            }
            periodRow.style.display = 'block';
            const cpg = window.cpGenerator;
            plan.periods.forEach(per => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'period-btn';
                btn.dataset.months = per.months;
                let label = '+' + per.months + ' мес';
                if (per.discount_percent > 0) {
                    label += ' <span class="discount-badge">-' + per.discount_percent + '%</span>';
                }
                const giftMonths = cpg && typeof cpg.getAiGiftMonthsForPeriod === 'function'
                    ? cpg.getAiGiftMonthsForPeriod(per.months) : 0;
                if (giftMonths > 0) {
                    label += ' <span class="gift-badge">+' + giftMonths + ' подарок</span>';
                }
                btn.innerHTML = label;
                btn.addEventListener('click', () => {
                    const already = btn.classList.contains('active');
                    periodSelector.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                    if (already) {
                        selectedMonths = null;
                    } else {
                        btn.classList.add('active');
                        selectedMonths = per.months;
                    }
                    renderSummary();
                });
                periodSelector.appendChild(btn);
            });
        }

        if (topupInput) {
            topupInput.addEventListener('input', () => { setAiItem(); });
        }

        function setAiItem(options = {}) {
            const refreshSummary = options.refreshSummary !== false;
            const cpgGate = window.cpGenerator;
            if (cpgGate && typeof cpgGate.isAiAgentAllowedForSelectedTariff === 'function'
                && !cpgGate.isAiAgentAllowedForSelectedTariff()) {
                window.CP_AI_ITEM = null;
                if (checkbox) checkbox.checked = false;
                if (body) body.style.display = 'none';
                if (currentMonthRow) currentMonthRow.style.display = 'none';
                if (topupRow) topupRow.style.display = 'none';
                if (refreshSummary && cpgGate && typeof cpgGate.updateSummary === 'function') {
                    cpgGate.updateSummary();
                }
                return;
            }
            if (checkbox.checked && selectedPlanId) {
                const plan = plans.find(p => p.id === selectedPlanId);
                const unit = getAiPrice(plan);
                const cur = getAiCurrency(plan);
                const currentMonth = suggestCurrentMonth(unit);
                if (currentMonthRow) currentMonthRow.style.display = 'block';
                if (topupRow) topupRow.style.display = 'block';
                if (currentMonthInput) currentMonthInput.value = currentMonth;
                if (currentMonthCurrency) currentMonthCurrency.textContent = cur || '';
                if (topupCurrency) topupCurrency.textContent = cur || '';

                const months = selectedMonths ? Number(selectedMonths) : 0;
                let discountPct = 0, original = 0, total = 0;
                if (months > 0) {
                    const per = plan?.periods.find(p => Number(p.months) === months);
                    discountPct = per?.discount_percent ?? 0;
                    original = +(unit * months).toFixed(4);
                    total = +(original * (1 - discountPct / 100)).toFixed(4);
                }
                const giftMonths = months > 0 && cpgGate && typeof cpgGate.getAiGiftMonthsForPeriod === 'function'
                    ? cpgGate.getAiGiftMonthsForPeriod(months) : 0;
                const giftOriginal = giftMonths > 0 ? +(unit * giftMonths).toFixed(4) : 0;
                const partnerPct = cpgGate && typeof cpgGate.getPartnerPackPercent === 'function'
                    ? cpgGate.getPartnerPackPercent() : 0;
                const topup = Math.max(0, +(Number(topupInput?.value) || 0).toFixed(4));
                window.CP_AI_ITEM = {
                    plan_id: selectedPlanId,
                    plan_name: plan?.name || '',
                    period_months: months,
                    gift_months: giftMonths,
                    gift_original_price: giftOriginal,
                    unit_price: unit,
                    discount_percent: discountPct,
                    partner_percent: partnerPct,
                    original_price: original,
                    total_price: total,
                    current_month_amount: currentMonth,
                    balance_topup: topup,
                    currency: cur,
                };
            } else {
                window.CP_AI_ITEM = null;
                if (currentMonthRow) currentMonthRow.style.display = 'none';
                if (topupRow) topupRow.style.display = 'none';
            }
            if (refreshSummary && window.cpGenerator && typeof window.cpGenerator.updateSummary === 'function') {
                window.cpGenerator.updateSummary();
            }
        }

        function renderSummary() { setAiItem({ refreshSummary: true }); }

        function syncAiAgentGate() {
            if (window.cpGenerator && typeof window.cpGenerator.syncAiAgentAvailability === 'function') {
                window.cpGenerator.syncAiAgentAvailability();
            }
        }
        syncAiAgentGate();
        document.addEventListener('DOMContentLoaded', () => {
            // cpGenerator создаётся на DOMContentLoaded — догоняем после его init.
            setTimeout(syncAiAgentGate, 0);
            setTimeout(syncAiAgentGate, 50);
        });
    })();
    </script>

@endsection
