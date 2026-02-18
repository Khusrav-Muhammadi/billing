@extends('layouts.app')

@section('title')
    Оплаты
@endsection

@section('content')
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h4 class="card-title mb-0">Оплата услуг</h4>
        </div>
        <button type="button" class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createModal">
            Создать
        </button>
        <div class="table-responsive mt-3">
            <table class="table table-hover align-middle">
                <thead>
                <tr>
                    <th style="width:70px;">№</th>
                    <th>ФИО</th>
                    <th style="width:180px;">Телефон</th>
                    <th>Почта</th>
                    <th style="width:140px;">Сумма</th>
                </tr>
                </thead>

                <tbody>
                @foreach($clients as $client)
                    <tr class="js-client-row" style="cursor:pointer"
                        data-bs-toggle="modal" data-bs-target="#showModal"
                        data-name="{{ $client->name }}"
                        data-phone="{{ $client->phone }}"
                        data-email="{{ $client->email }}"
                        data-sum="{{ $client->sum }}"
                        data-items='@json($client->paymentItems->map(fn($i)=>["service_name"=>$i->service_name,"price"=>$i->price]))'>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $client->name }}</td>
                        <td>{{ $client->phone }}</td>
                        <td class="text-break">{{ $client->email }}</td>
                        <td>
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                                {{ $client->sum }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination-wrapper">
            {{ $clients->links() }}
        </div>
    </div>

    {{-- =========================
        MODAL: ПРОСМОТР (как Create)
    ========================== --}}
    <div class="modal fade" id="showModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Просмотр оплаты</h5>
                        <small class="text-muted">Данные клиента и список услуг</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <fieldset class="border rounded p-3 mb-4">
                        <legend class="float-none w-auto px-2 mb-0" style="font-size: 14px;">
                            Данные клиента
                        </legend>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">ФИО клиента</label>
                                <input type="text" class="form-control" id="show_name" readonly>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Телефон</label>
                                <input type="text" class="form-control" id="show_phone" readonly>
                            </div>

                            <div class="col-md-6 mt-2">
                                <label class="form-label">Почта</label>
                                <input type="email" class="form-control" id="show_email" readonly>
                            </div>
                        </div>
                    </fieldset>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead>
                            <tr>
                                <th>Название услуги</th>
                                <th style="width:200px">Цена</th>
                            </tr>
                            </thead>
                            <tbody id="show_items_tbody">
                            <tr><td class="text-muted" colspan="2">Нет данных</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2">
                        <strong>Итого:</strong>
                        <input type="text" id="show_total" class="form-control" style="max-width:200px" readonly value="0">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>

    {{-- =========================
        MODAL: СОЗДАНИЕ (шаг 1)
    ========================== --}}
    <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <form action="{{ route('client-payment.create') }}" method="POST" id="createForm">
                @csrf

                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">Добавление услуг</h5>
                            <small class="text-muted">Заполните данные и выберите оплату</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <fieldset class="border rounded p-3 mb-4">
                            <legend class="float-none w-auto px-2 mb-0" style="font-size: 14px;">
                                Данные клиента
                            </legend>

                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">ФИО клиента</label>
                                    <input type="text" class="form-control" name="name" placeholder="Введите ФИО клиента" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" class="form-control" name="phone" placeholder="Введите телефон">
                                </div>

                                <div class="col-md-6 mt-2">
                                    <label class="form-label">Почта</label>
                                    <input type="email" class="form-control" name="email" placeholder="Введите почту">
                                </div>
                            </div>
                        </fieldset>

                        <div class="table-responsive mb-3">
                            <table class="table table-bordered align-middle" id="create_services_table">
                                <thead>
                                <tr>
                                    <th>Название услуги</th>
                                    <th style="width:200px">Цена</th>
                                    <th style="width:80px">Удалить</th>
                                </tr>
                                </thead>
                                <tbody id="create_services_tbody">
                                <tr>
                                    <td>
                                        <input type="text" class="form-control js-name" name="data[0][name]" placeholder="Введите название" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control js-price" name="data[0][price]" min="0" value="0" required>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm js-remove-row">✕</button>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-outline-primary mb-3" id="create_add_row">
                            + Добавить строку
                        </button>

                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <strong>Итого:</strong>
                            <input type="text" id="create_total" class="form-control" style="max-width:200px" value="0" readonly>
                        </div>

                        <input type="hidden" name="sum" id="create_sum" value="0">
                        <input type="hidden" name="payment_type" id="create_payment_type" value="">
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                        {{-- НЕ submit: открывает модалку выбора оплаты --}}
                        <button type="button" class="btn btn-primary" id="openPaymentModalBtn">
                            Сохранить
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- =========================
        MODAL: ВЫБОР ОПЛАТЫ (шаг 2) - ПОЛНОСТЬЮ НОВЫЙ ДИЗАЙН
    ========================== --}}
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Подтверждение оплаты</h5>
                        <small class="text-muted">Выберите сервис и продолжайте</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Клиент</div>
                                <div class="fw-semibold" id="pay_client_name">—</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">К оплате</div>
                                <div class="fw-bold fs-5" id="pay_client_sum">0</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="flex-fill">
                            <input class="btn-check" type="radio" name="pay_radio" id="pay_alif" autocomplete="off" value="alif">
                            <label class="pay-tile border rounded p-3 w-100" for="pay_alif">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">Alif</div>
                                        <div class="text-muted small">Alif Bank</div>
                                    </div>
                                    <span class="badge bg-primary">A</span>
                                </div>
                                <div class="small text-muted mt-2">Быстрый платёж через Alif</div>
                            </label>
                        </div>

                        <div class="flex-fill">
                            <input class="btn-check" type="radio" name="pay_radio" id="pay_octo" autocomplete="off" value="octo">
                            <label class="pay-tile border rounded p-3 w-100" for="pay_octo">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold">Octo Bank</div>
                                        <div class="text-muted small">Octo</div>
                                    </div>
                                    <span class="badge bg-dark">O</span>
                                </div>
                                <div class="small text-muted mt-2">Оплата через Octo Bank</div>
                            </label>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Назад</button>
                    <button type="button" class="btn btn-primary" id="pay_confirm_btn" disabled>
                        Продолжить
                    </button>
                </div>

            </div>
        </div>
    </div>

    {{-- styles for payment tiles --}}
    <style>
        .pay-tile { cursor: pointer; transition: .15s ease; display:block; background:#fff; }
        .pay-tile:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(0,0,0,.08); }
        .btn-check:checked + .pay-tile { border: 2px solid #0d6efd !important; background: #f4f8ff; }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            // ==========================
            // SHOW MODAL (view)
            // ==========================
            const showModalEl = document.getElementById('showModal');
            showModalEl.addEventListener('show.bs.modal', function (event) {
                const tr = event.relatedTarget;

                document.getElementById('show_name').value  = tr.dataset.name  || '';
                document.getElementById('show_phone').value = tr.dataset.phone || '';
                document.getElementById('show_email').value = tr.dataset.email || '';
                document.getElementById('show_total').value = tr.dataset.sum   || '0';

                const tbody = document.getElementById('show_items_tbody');
                let items = [];
                try { items = JSON.parse(tr.dataset.items || '[]'); } catch(e) { items = []; }

                if (!items.length) {
                    tbody.innerHTML = `<tr><td class="text-muted" colspan="2">Нет данных</td></tr>`;
                    return;
                }

                tbody.innerHTML = items.map(it => `
                    <tr>
                        <td><input type="text" class="form-control" value="${(it.service_name ?? '')}" readonly></td>
                        <td><input type="number" class="form-control" value="${(it.price ?? 0)}" readonly></td>
                    </tr>
                `).join('');
            });

            // ==========================
            // CREATE MODAL (rows + total)
            // ==========================
            const createForm = document.getElementById('createForm');
            const createTbody = document.getElementById('create_services_tbody');
            const addRowBtn = document.getElementById('create_add_row');
            const totalInput = document.getElementById('create_total');
            const sumHidden = document.getElementById('create_sum');

            let rowIndex = createTbody.querySelectorAll('tr').length;

            function calcCreateTotal() {
                let total = 0;
                createTbody.querySelectorAll('.js-price').forEach(inp => {
                    total += parseFloat(inp.value) || 0;
                });
                totalInput.value = total;
                sumHidden.value = total;
            }

            function makeRow(i) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>
                        <input type="text" class="form-control js-name" name="data[${i}][name]" placeholder="Введите название" required>
                    </td>
                    <td>
                        <input type="number" class="form-control js-price" name="data[${i}][price]" min="0" value="0" required>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm js-remove-row">✕</button>
                    </td>
                `;
                return tr;
            }

            addRowBtn.addEventListener('click', function () {
                createTbody.appendChild(makeRow(rowIndex));
                rowIndex++;
                calcCreateTotal();
            });

            createTbody.addEventListener('click', function (e) {
                const btn = e.target.closest('.js-remove-row');
                if (!btn) return;

                const rows = createTbody.querySelectorAll('tr');
                if (rows.length > 1) {
                    btn.closest('tr').remove();
                    calcCreateTotal();
                } else {
                    const name = createTbody.querySelector('.js-name');
                    const price = createTbody.querySelector('.js-price');
                    if (name) name.value = '';
                    if (price) price.value = 0;
                    calcCreateTotal();
                }
            });

            createTbody.addEventListener('input', function (e) {
                if (e.target.classList.contains('js-price')) calcCreateTotal();
            });

            calcCreateTotal();

            // ==========================
            // PAYMENT MODAL (step 2)
            // ==========================
            const openPaymentBtn = document.getElementById('openPaymentModalBtn');
            const paymentModalEl = document.getElementById('paymentModal');
            const paymentModal = new bootstrap.Modal(paymentModalEl);

            const payClientName = document.getElementById('pay_client_name');
            const payClientSum  = document.getElementById('pay_client_sum');
            const payConfirmBtn = document.getElementById('pay_confirm_btn');

            const paymentTypeInput = document.getElementById('create_payment_type');
            const payRadios = paymentModalEl.querySelectorAll('input[name="pay_radio"]');

            function resetPaymentSelection() {
                payRadios.forEach(r => r.checked = false);
                payConfirmBtn.disabled = true;
                paymentTypeInput.value = '';
            }

            openPaymentBtn.addEventListener('click', function () {
                if (!createForm.checkValidity()) {
                    createForm.reportValidity();
                    return;
                }

                // заполняем инфо
                const clientName = createForm.querySelector('input[name="name"]')?.value || '—';
                const sum = totalInput.value || '0';

                payClientName.textContent = clientName;
                payClientSum.textContent = sum;

                resetPaymentSelection();
                paymentModal.show();
            });

            paymentModalEl.addEventListener('change', function (e) {
                const radio = e.target.closest('input[name="pay_radio"]');
                if (!radio) return;

                paymentTypeInput.value = radio.value; // alif | octo
                payConfirmBtn.disabled = false;
            });

            payConfirmBtn.addEventListener('click', function () {
                if (!paymentTypeInput.value) return;
                createForm.submit();
            });

            // чтобы "не залипало" при закрытии create
            document.getElementById('createModal').addEventListener('hidden.bs.modal', function () {
                paymentTypeInput.value = '';
            });
        });
    </script>
@endsection

