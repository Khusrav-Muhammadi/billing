@extends('layouts.app')

@section('title') Счет на оплату @endsection

@section('content')
    @php
        $createdAt = $payment->created_at ? \Carbon\Carbon::parse($payment->created_at) : now();
        $invoiceDate = $createdAt->format('d.m.Y');
        $dueDate = $createdAt->copy()->addDays(7)->format('d.m.Y');
        $currencyLabel = 'сум';
        $totalSum = (float) $payment->sum;
        $totalFormatted = number_format($totalSum, 2, ',', ' ');
        $items = $payment->paymentItems ?? collect();
        $organizationOrderNumber = trim((string) ($organizationOrderNumber ?? ''));
        $invoiceOrganization = $offer?->organization;
        $signatureUrl = $signatureUrl ?? asset('assets/images/invoice/imzo.png');
        $stampUrl = $stampUrl ?? asset('assets/images/invoice/pechat.png');
        $customer = [
            'legal_name' => (string) ($invoiceOrganization?->legal_name ?: $invoiceOrganization?->name ?: ($payment->name ?? '')),
            'INN' => (string) ($invoiceOrganization?->INN ?? ''),
            'email' => (string) ($invoiceOrganization?->email ?: ($payment->email ?? '')),
            'phone' => (string) ($invoiceOrganization?->phone ?: ($payment->phone ?? '')),
        ];
    @endphp

    <div class="invoice-wrapper">
        @if(empty($hideInvoiceActions))
            <div class="invoice-actions">
                <a href="{{ route('client-payment.index') }}" class="btn btn-light">Назад</a>
                <form action="{{ route('client-payment.invoice.email', $payment) }}" method="POST" class="m-0">
                    @csrf
                    <button class="btn btn-success" type="submit">Отправить на почту</button>
                </form>
                <button class="btn btn-primary" onclick="window.print()">Печать / Скачать PDF</button>
            </div>
        @endif

        <div class="invoice-page">
            <h2 class="invoice-title">Счет на оплату № {{ $payment->id }} от {{ $invoiceDate }}</h2>

            <div class="invoice-block">
                <div><strong>Поставщик:</strong> "SOFTTECH GROUP" MCHJ ИНН: 311680486 Адрес: ГОРОД ТАШКЕНТ, ЯККАСАРАЙСКИЙ РАЙОН, Bog'saroy MFY, Mirobod ko'chasi, 10-uy</div>
            </div>

            <div class="invoice-block">
                <div><strong>Банковские реквизиты:</strong></div>
                <ul class="invoice-list">
                    <li>Банк получателя: КАПИТАЛБАНК "КАПИТАЛ 24" ЧАКАНА БИЗНЕС ФИЛИАЛИ</li>
                    <li>МФО: 01158</li>
                    <li>Расчетный счет: 2020800080715938001</li>
                </ul>
            </div>
            <div class="invoice-block invoice-divider">
                <div class="customer-details"
                     data-update-url="{{ $invoiceOrganization && empty($hideInvoiceActions) ? route('client-payment.invoice.customer.update', $payment) : '' }}">
                    <strong>Покупатель:</strong>
                    <span class="editable-field" data-field="legal_name" data-label="Покупатель" data-value="{{ $customer['legal_name'] }}" tabindex="0">
                        <span class="editable-display">"<span class="editable-value">{{ $customer['legal_name'] !== '' ? $customer['legal_name'] : '—' }}</span>"</span>
                        @if($invoiceOrganization && empty($hideInvoiceActions))
                            <button type="button" class="edit-field-btn" title="Изменить покупателя" aria-label="Изменить покупателя">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        @endif
                    </span>

                    <span class="editable-field" data-field="INN" data-label="ИНН" data-value="{{ $customer['INN'] }}" tabindex="0">
                        <span class="editable-display">
                            <span class="customer-field-label">ИНН:</span>
                            <span class="editable-value">{{ $customer['INN'] !== '' ? $customer['INN'] : '—' }}</span>
                        </span>
                        @if($invoiceOrganization && empty($hideInvoiceActions))
                            <button type="button" class="edit-field-btn" title="Изменить ИНН" aria-label="Изменить ИНН">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        @endif
                    </span>

                    <span class="editable-field" data-field="email" data-label="Почта" data-value="{{ $customer['email'] }}" tabindex="0">
                        <span class="editable-display">
                            <span class="customer-field-label">Почта:</span>
                            <span class="editable-value">{{ $customer['email'] !== '' ? $customer['email'] : '—' }}</span>
                        </span>
                        @if($invoiceOrganization && empty($hideInvoiceActions))
                            <button type="button" class="edit-field-btn" title="Изменить почту" aria-label="Изменить почту">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        @endif
                    </span>

                    <span class="editable-field" data-field="phone" data-label="Телефон" data-value="{{ $customer['phone'] }}" tabindex="0">
                        <span class="editable-display">
                            <span class="customer-field-label">Телефон:</span>
                            <span class="editable-value">{{ $customer['phone'] !== '' ? $customer['phone'] : '—' }}</span>
                        </span>
                        @if($invoiceOrganization && empty($hideInvoiceActions))
                            <button type="button" class="edit-field-btn" title="Изменить телефон" aria-label="Изменить телефон">
                                <i class="mdi mdi-pencil"></i>
                            </button>
                        @endif
                    </span>
                </div>
            </div>
            <table class="invoice-table">
                <thead>
                <tr>
                    <th style="width: 50px;">№</th>
                    <th>Товары (работы, услуги)</th>
                    <th style="width: 80px;">Кол-во</th>
                    <th style="width: 120px;">Цена</th>
                    <th style="width: 120px;">Сумма</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $index => $item)
                    @php
                        $price = (float) ($item->price ?? 0);
                        $priceFormatted = number_format($price, 2, ',', ' ');
                        $serviceName = (string) ($item->service_name ?? '');
                        if (str_starts_with($serviceName, 'Внедрение и обучение')) {
                            $serviceName = preg_replace('/\s*\(скидка\s*[\d.,]+\s*%\)\s*/u', '', $serviceName) ?: $serviceName;
                        }
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $serviceName }}</td>
                        <td>1</td>
                        <td>{{ $priceFormatted }}</td>
                        <td>{{ $priceFormatted }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="invoice-empty">Нет данных</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            <div class="invoice-total">
             <!--   <div><strong>Итого:</strong> {{ $totalFormatted }} Без налога (НДС): —</div> -->
                <div><strong>Всего к оплате:</strong> {{ $totalFormatted }} ({{ $currencyLabel }})</div>
            </div>

            <div class="invoice-divider"></div>

            @if($offer->partner_id && $offer->partner_id != 11)
            <div class="invoice-block">
                <div><strong>Условия:</strong></div>
                <ul class="invoice-list">
                    <li>Оплата данного счёта означает согласие с условиями предоставления услуг. Подтверждаю, что клиент ознакомлен с услугами SHAMCRM и
                        <a href="https://shamcrm.com/agreement" style="color: blue">условиями оферты</a>.
                        При отказе от услуги денежные средства не возвращаются.</li>
                    <li>В назначении платежа обязательно указать ID организации: {{ $organizationOrderNumber !== '' ? $organizationOrderNumber : '—' }}.</li>
                </ul>
            </div>
            @else
                <div class="invoice-block">
                    <div><strong>Условия:</strong></div>
                    <ul class="invoice-list">
                        <li>Оплата данного счёта означает согласие с условиями предоставления услуг и подтверждаю, что ознакомлен (а) с услугами SHAMCRM и    <a href="https://shamcrm.com/agreement" style="color: blue">условиями оферты</a>.</li>
                        <li>В назначении платежа обязательно указать ID организации: {{ $organizationOrderNumber !== '' ? $organizationOrderNumber : '—' }}.</li>
                    </ul>
                </div>
            @endif



            <div class="invoice-sign">
                <div class="invoice-sign-row">
                    <span class="invoice-sign-label">Руководитель:</span>
                    <span class="invoice-sign-field">
                        <span class="invoice-sign-line"></span>
                        <img src="{{ $signatureUrl }}" class="invoice-signature-img" alt="Подпись руководителя">
                    </span>
                    <span>/ Ахмедов М.Р.</span>
                </div>
                <div class="invoice-stamp-row">
                    <img src="{{ $stampUrl }}" class="invoice-stamp-img" alt="Печать организации">
                </div>
            </div>
        </div>
    </div>

    <style>
        .invoice-wrapper {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            font-family: "DejaVu Sans", Arial, sans-serif;
        }
        .invoice-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 12px;
        }
        .invoice-page {
            background: #fff;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .invoice-title {
            text-align: center;
            margin-bottom: 18px;
            font-size: 20px;
            font-weight: 700;
        }
        .invoice-block {
            font-size: 14px;
            margin-bottom: 12px;
            line-height: 1.45;
        }
        .customer-details {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 12px;
        }
        .editable-field {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            min-height: 28px;
            padding: 2px 4px;
            border-radius: 6px;
            cursor: text;
        }
        .editable-field:hover {
            background: #f9fafb;
        }
        .editable-field:focus {
            outline: 2px solid #bfdbfe;
            outline-offset: 1px;
        }
        .editable-display {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .customer-field-label {
            color: #4b5563;
            font-weight: 600;
        }
        .editable-value {
            color: #111827;
        }
        .edit-field-btn,
        .save-field-btn,
        .cancel-field-btn {
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            border-radius: 6px;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            line-height: 1;
            font-size: 15px;
            transition: background-color .15s ease, color .15s ease, border-color .15s ease;
        }
        .edit-field-btn {
            opacity: .55;
        }
        .editable-field:hover .edit-field-btn,
        .editable-field:focus .edit-field-btn {
            opacity: 1;
        }
        .edit-field-btn:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #2563eb;
        }
        .save-field-btn {
            color: #15803d;
        }
        .save-field-btn:hover {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .cancel-field-btn {
            color: #991b1b;
        }
        .cancel-field-btn:hover {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .editable-input {
            width: min(320px, 64vw);
            height: 28px;
            padding: 4px 8px;
            border: 1px solid #93c5fd;
            border-radius: 6px;
            color: #111827;
            outline: none;
        }
        .editable-editor {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .editable-field.is-saving {
            opacity: .65;
            pointer-events: none;
        }
        .invoice-list {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        .invoice-divider {
            border-top: 1px solid #9ca3af;
            margin: 12px 0;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 8px;
        }
        .invoice-table th,
        .invoice-table td {
            border: 1px solid #111827;
            padding: 6px 8px;
            vertical-align: top;
        }
        .invoice-empty {
            text-align: center;
            color: #6b7280;
        }
        .invoice-total {
            margin-top: 12px;
            font-size: 14px;
            font-weight: 600;
        }
        .invoice-sign {
            margin-top: 18px;
            font-size: 14px;
            white-space: nowrap;
        }
        .invoice-sign-row {
            display: inline-block;
            min-height: 96px;
            vertical-align: bottom;
            white-space: nowrap;
        }
        .invoice-sign-label {
            display: inline-block;
            vertical-align: bottom;
            margin-bottom: 8px;
        }
        .invoice-sign-field {
            position: relative;
            display: inline-block;
            width: 150px;
            height: 86px;
            vertical-align: bottom;
        }
        .invoice-sign-line {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 14px;
            border-bottom: 1px solid #111827;
        }
        .invoice-signature-img {
            position: absolute;
            left: 20px;
            bottom: 16px;
            width: 92px;
            height: auto;
            z-index: 2;
        }
        .invoice-stamp-img {
            width: 82px;
            height: 82px;
            opacity: .82;
        }
        .invoice-stamp-row {
            display: inline-block;
            min-height: 96px;
            margin-left: 0;
            vertical-align: bottom;
        }
        @media (max-width: 767px) {
            .invoice-sign { white-space: normal; }
            .invoice-sign-row {
                white-space: normal;
            }
            .invoice-stamp-row {
                display: block;
                margin-left: 0;
            }
        }
        @media print {
            .btn, .nav, .sidebar, .navbar, .invoice-actions, .edit-field-btn { display: none !important; }
            .invoice-wrapper { padding: 0; }
            .invoice-page { border: none; }
            .invoice-signature-img,
            .invoice-stamp-img {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .invoice-sign {
                white-space: nowrap !important;
                display: flex;
                align-items: flex-end;
                gap: 20px;
            }
            .invoice-sign-row {
                white-space: nowrap !important;
                flex-shrink: 0;
            }
            .invoice-stamp-row {
                white-space: nowrap !important;
                display: inline-block !important;
                flex-shrink: 0;
                margin-left: 0 !important;
            }
        }
    </style>
@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wrapper = document.querySelector('.customer-details');
            if (!wrapper || !wrapper.dataset.updateUrl) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            const setFieldValue = (fieldNode, value) => {
                const normalized = (value || '').trim();
                fieldNode.dataset.value = normalized;
                fieldNode.querySelector('.editable-value').textContent = normalized || '—';
            };

            let activeField = null;

            const closeEditor = (fieldNode) => {
                fieldNode.querySelector('.editable-editor')?.remove();
                fieldNode.querySelectorAll('.editable-display, .edit-field-btn').forEach((node) => {
                    node.style.display = '';
                });
                if (activeField === fieldNode) {
                    activeField = null;
                }
            };

            const openEditor = (fieldNode) => {
                if (fieldNode.querySelector('.editable-editor')) {
                    return;
                }

                if (activeField && activeField !== fieldNode) {
                    closeEditor(activeField);
                }
                activeField = fieldNode;

                fieldNode.querySelectorAll('.editable-display, .edit-field-btn').forEach((node) => {
                    node.style.display = 'none';
                });

                const editor = document.createElement('span');
                editor.className = 'editable-editor';
                editor.innerHTML = `
                    <input class="editable-input" type="text" aria-label="${fieldNode.dataset.label || 'Поле'}">
                    <button type="button" class="save-field-btn" title="Сохранить" aria-label="Сохранить">
                        <i class="mdi mdi-check"></i>
                    </button>
                    <button type="button" class="cancel-field-btn" title="Отменить" aria-label="Отменить">
                        <i class="mdi mdi-close"></i>
                    </button>
                `;

                const input = editor.querySelector('.editable-input');
                fieldNode.appendChild(editor);
                input.value = fieldNode.dataset.value || '';
                input.focus();
                input.select();

                const save = async () => {
                    const value = input.value.trim();
                    fieldNode.classList.add('is-saving');

                    try {
                        const response = await fetch(wrapper.dataset.updateUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                field: fieldNode.dataset.field,
                                value,
                            }),
                        });

                        const payload = await response.json();
                        if (!response.ok || !payload.success) {
                            throw new Error(payload.message || 'Не удалось сохранить');
                        }

                        const customer = payload.customer || {};
                        Object.entries(customer).forEach(([field, fieldValue]) => {
                            const node = wrapper.querySelector(`.editable-field[data-field="${field}"]`);
                            if (node) {
                                setFieldValue(node, fieldValue);
                            }
                        });
                        closeEditor(fieldNode);
                    } catch (error) {
                        alert(error.message || 'Не удалось сохранить');
                    } finally {
                        fieldNode.classList.remove('is-saving');
                    }
                };

                editor.querySelector('.save-field-btn').addEventListener('click', save);
                editor.querySelector('.cancel-field-btn').addEventListener('click', () => closeEditor(fieldNode));
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        save();
                    }
                    if (event.key === 'Escape') {
                        closeEditor(fieldNode);
                    }
                });
            };

            wrapper.addEventListener('click', (event) => {
                const fieldNode = event.target.closest('.editable-field');
                if (!fieldNode || event.target.closest('.editable-editor')) {
                    return;
                }

                openEditor(fieldNode);
            });

            wrapper.addEventListener('keydown', (event) => {
                const fieldNode = event.target.closest('.editable-field');
                if (!fieldNode || fieldNode.querySelector('.editable-editor')) {
                    return;
                }

                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openEditor(fieldNode);
                }
            });
        });
    </script>
@endsection