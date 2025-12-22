@extends('layouts.app')

@section('title')
    Оплаты
@endsection
@section('content')
    <form id="filterForm">
{{--        <div class="row mb-3">--}}
{{--            <div class="col-md-2">--}}
{{--                <select name="demo" class="form-control">--}}
{{--                    <option value="">Тип подключения</option>--}}
{{--                    <option value="1">Демо</option>--}}
{{--                    <option value="0">Боевая</option>--}}
{{--                </select>--}}
{{--            </div>--}}
{{--            <div class="col-md-2">--}}
{{--                <select name="status" class="form-control">--}}
{{--                    <option value="">Статус</option>--}}
{{--                    <option value="1">Активный</option>--}}
{{--                    <option value="0">Неактивный</option>--}}
{{--                </select>--}}
{{--            </div>--}}
{{--            <div class="col-md-2">--}}
{{--                <select name="tariff" class="form-control">--}}
{{--                    <option value="">Тариф</option>--}}
{{--                    @foreach($tariffs as $tariff)--}}
{{--                        <option value="{{ $tariff->id }}">{{ $tariff->name }}</option>--}}
{{--                    @endforeach--}}
{{--                </select>--}}
{{--            </div>--}}
{{--            <div class="col-md-2">--}}
{{--                <select name="partner" class="form-control">--}}
{{--                    <option value="">Партнер</option>--}}
{{--                    @foreach($partners as $partner)--}}
{{--                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>--}}
{{--                    @endforeach--}}
{{--                </select>--}}
{{--            </div>--}}
{{--            <div class="col-md-2">--}}
{{--                <select name="manager" class="form-control">--}}
{{--                    <option value="">Менеджер</option>--}}
{{--                    @foreach($managers as $manager)--}}
{{--                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>--}}
{{--                    @endforeach--}}
{{--                </select>--}}
{{--            </div>--}}
{{--            <div class="col-md-2">--}}
{{--                <input type="text" name="search" class="form-control" placeholder="Поиск">--}}
{{--            </div>--}}
{{--        </div>--}}
    </form>

    <div class="card-body">
        <h4 class="card-title">Оплата услуг</h4>
        <a href="#" type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create">Создать</a>
        <div class="table-responsive">

            <table class="table table-hover">
                <thead>
                <tr>
                    <th>№</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Почта</th>
                    <th>Сумма</th>
                </tr>
                </thead>
                <tbody>
                @foreach($clients as $client)
                    <tr style="cursor: pointer" data-href="{{ route('client.show', $client->id) }}" onclick="window.location.href=this.dataset.href">
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $client->name }}</td>
                        <td>{{ $client->phone }}</td>
                        <td>{{ $client->email }}</td>
                        <td>{{ $client->sum }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="modal fade" id="create" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <form action="" method="POST" id="clientPaymentForm">
                        @csrf

                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Добавление услуг</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">

                                <!-- БЛОК ДАННЫХ КЛИЕНТА (отделён от таблицы) -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h6 class="mb-3">Данные клиента</h6>

                                        <div class="mb-3">
                                            <label class="form-label">ФИО клиента</label>
                                            <input type="text" class="form-control" name="name" placeholder="Введите ФИО клиента" required>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Телефон</label>
                                            <input type="text" class="form-control" name="phone" placeholder="Введите телефон">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Почта</label>
                                            <input type="email" class="form-control" name="email" placeholder="Введите почту">
                                        </div>
                                    </div>
                                </div>

                                <!-- ТАБЛИЦА УСЛУГ -->
                                <div class="table-responsive mb-3">
                                    <table class="table table-bordered align-middle" id="servicesTable">
                                        <thead>
                                        <tr>
                                            <th>Название услуги</th>
                                            <th style="width:200px">Цена</th>
                                            <th style="width:80px">Удалить</th>
                                        </tr>
                                        </thead>

                                        <tbody id="servicesTbody">
                                        <!-- первая строка -->
                                        <tr>
                                            <td>
                                                <input type="text" class="form-control name-input"
                                                       name="data[0][name]" placeholder="Введите название" required>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control price-input"
                                                       name="data[0][price]" min="0" value="0" required>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <button type="button" class="btn btn-outline-primary mb-3" id="addRowBtn">
                                    + Добавить строку
                                </button>

                                <!-- ИТОГО под таблицей -->
                                <div class="d-flex justify-content-end align-items-center gap-2">
                                    <strong>Итого:</strong>
                                    <input type="text" id="totalPrice" class="form-control"
                                           style="max-width:200px" value="0" readonly>
                                </div>

                                <!-- sum для бэка -->
                                <input type="hidden" name="sum" id="sumHidden" value="0">
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="submit" class="btn btn-primary">Сохранить</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    const tbody = document.getElementById("servicesTbody");
                    const addRowBtn = document.getElementById("addRowBtn");
                    const totalInput = document.getElementById("totalPrice");
                    const sumHidden = document.getElementById("sumHidden");

                    let index = tbody.querySelectorAll("tr").length; // следующий индекс

                    function calcTotal() {
                        let total = 0;
                        tbody.querySelectorAll(".price-input").forEach(inp => {
                            total += parseFloat(inp.value) || 0;
                        });
                        totalInput.value = total;
                        sumHidden.value = total;
                    }

                    function createRow(i) {
                        const tr = document.createElement("tr");
                        tr.innerHTML = `
            <td>
                <input type="text" class="form-control name-input"
                       name="data[${i}][name]" placeholder="Введите название" required>
            </td>
            <td>
                <input type="number" class="form-control price-input"
                       name="data[${i}][price]" min="0" value="0" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm remove-row">✕</button>
            </td>
        `;
                        return tr;
                    }

                    // Добавить строку
                    addRowBtn.addEventListener("click", function () {
                        tbody.appendChild(createRow(index));
                        index++;
                        calcTotal();
                    });

                    // Удаление строки
                    tbody.addEventListener("click", function (e) {
                        if (e.target.classList.contains("remove-row")) {
                            const rows = tbody.querySelectorAll("tr");
                            if (rows.length > 1) {
                                e.target.closest("tr").remove();
                                calcTotal();
                            } else {
                                // если последняя строка — просто очищаем
                                tbody.querySelector(".name-input").value = "";
                                tbody.querySelector(".price-input").value = 0;
                                calcTotal();
                            }
                        }
                    });

                    // Пересчёт при вводе цены
                    tbody.addEventListener("input", function (e) {
                        if (e.target.classList.contains("price-input")) {
                            calcTotal();
                        }
                    });

                    // начальный подсчет
                    calcTotal();
                });
            </script>



        </div>
        <div class="pagination-wrapper">
            {{$clients->links()}}
        </div>
    </div>
@endsection
