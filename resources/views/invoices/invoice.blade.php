<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Счет {{ $invoiceData['invoice_number'] }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 16px;
            margin: 0;
            font-weight: bold;
        }

        .invoice-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-info {
            width: 100%;
            margin-bottom: 20px;
        }

        .company-info table {
            width: 100%;
            border-collapse: collapse;
        }

        .company-info td {
            padding: 5px;
            vertical-align: top;
            border: 1px solid #000;
        }

        .company-info .label {
            font-weight: bold;
            width: 30%;
            background-color: #f0f0f0;
        }

        .payment-details {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #000;
        }

        .payment-details h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
        }

        .bank-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .bank-details div {
            width: 48%;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }

        .items-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            text-align: center;
        }

        .items-table .number {
            width: 30px;
            text-align: center;
        }

        .items-table .quantity {
            width: 60px;
            text-align: center;
        }

        .items-table .price {
            width: 100px;
            text-align: right;
        }

        .items-table .total {
            width: 100px;
            text-align: right;
        }

        .total-section {
            margin-top: 20px;
            text-align: right;
        }

        .total-amount {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }

        .payment-note {
            margin-top: 30px;
            font-size: 9px;
            line-height: 1.3;
        }

        .signature-section {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            width: 45%;
            text-align: center;
        }

        .signature-line {
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            height: 30px;
        }

        .amount-words {
            font-style: italic;
            margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>СЧЕТ НА ОПЛАТУ № {{ $invoiceData['invoice_number'] }} от {{ $invoiceData['created_date'] }}</h1>
    <div>Оплатить до {{ $invoiceData['due_date'] }}</div>
</div>

<div class="company-info">
    <table>
        <tr>
            <td class="label">Получатель:</td>
            <td>{{ $companyData['name'] }}</td>
        </tr>
        <tr>
            <td class="label">Адрес:</td>
            <td>{{ $companyData['address'] }}</td>
        </tr>
        <tr>
            <td class="label">ИНН:</td>
            <td>{{ $companyData['inn'] }}</td>
        </tr>
        <tr>
            <td class="label">ОКЭД:</td>
            <td>{{ $companyData['oked'] }}</td>
        </tr>
        <tr>
            <td class="label">Р/с:</td>
            <td>{{ $companyData['account'] }}</td>
        </tr>
        <tr>
            <td class="label">Банк:</td>
            <td>{{ $companyData['bank'] }}</td>
        </tr>
        <tr>
            <td class="label">МФО:</td>
            <td>{{ $companyData['mfo'] }}</td>
        </tr>
    </table>
</div>

<div class="company-info">
    <table>
        <tr>
            <td class="label">Плательщик:</td>
            <td>{{ $organization['legal_name'] }}</td>
        </tr>
        <tr>
            <td class="label">Адрес:</td>
            <td>{{ $organization['legal_address'] }}</td>
        </tr>
        <tr>
            <td class="label">ИНН:</td>
            <td>{{ $organization['inn'] }}</td>
        </tr>
        <tr>
            <td class="label">Телефон:</td>
            <td>{{ $organization['phone'] }}</td>
        </tr>
        <tr>
            <td class="label">Директор:</td>
            <td>{{ $organization['director'] }}</td>
        </tr>
        <tr>
            <td class="label">Email:</td>
            <td>{{ $organization['email'] }}</td>
        </tr>
    </table>
</div>

<div class="payment-details">
    <h3>Назначение платежа:</h3>
    <p>Оплата по счету №{{ $invoiceData['invoice_number'] }} от {{ $invoiceData['created_date'] }} за услуги ShamCRM.</p>
</div>

<table class="items-table">
    <thead>
    <tr>
        <th class="number">№</th>
        <th>Наименование услуги</th>
        <th class="quantity">Кол-во</th>
        <th class="price">Цена, {{ $currency }}</th>
        <th class="total">Сумма, {{ $currency }}</th>
    </tr>
    </thead>
    <tbody>
    @foreach($invoiceItems as $index => $item)
        <tr>
            <td class="number">{{ $index + 1 }}</td>
            <td>{{ $item->name }}</td>
            <td class="quantity">{{ $item->amount }}</td>
            <td class="price">{{ number_format($item->unit_price ?? ($item->price / $item->amount), 2, ',', ' ') }}</td>
            <td class="total">{{ number_format($item->price, 2, ',', ' ') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="total-section">
    <div class="total-amount">
        <strong>Итого к оплате: {{ number_format($totalAmount, 2, ',', ' ') }} {{ $currency }}</strong>
    </div>
{{--    <div class="amount-words">--}}
{{--      123  {{  $currency }}--}}
{{--    </div>--}}
</div>

<div class="payment-note">
    <p><strong>Внимание!</strong> Датой оплаты по данному счету является дата поступления денежных средств на расчетный счет получателя.</p>
    <p>Оплата настоящего счета означает согласие с условиями предоставления услуг.</p>
    <p>Наличие в платежно-расчетном документе ссылки на счет №{{ $invoiceData['invoice_number'] }} обязательно. В случае неуказания номера счета ваш платеж не будет обработан или будет зачтен получателем по своему усмотрению в счет оплаты любого заказа.</p>
</div>
</body>
</html>
