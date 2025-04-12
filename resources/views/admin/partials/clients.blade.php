<table class="table table-hover">
    <thead>
    <tr>
        <th>№</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Почта</th>
        <th>Кол-во организация</th>
        <th>Тариф</th>
        <th>Кол-во пользователей</th>
        <th>Баланс</th>
        <th>Срок действия</th>
        <th>Последняя активность</th>
        <th class="text-center">Статус</th>
        <th>Тип подключения</th>
        <th>Партнер</th>
        <th>Страна</th>
        <th>Поддомен</th>
    </tr>
    </thead>
    <tbody>
    @foreach($clients as $client)
        <tr style="cursor: pointer" data-href="{{ route('client.show', $client->id) }}" onclick="window.location.href=this.dataset.href">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $client->name }}</td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->email }}</td>
            <td class="text-center">{{ $client->organizations_count }}</td>
            <td>{{ $client->tariff?->name }}</td>
            <td class="text-center">{{ $client->is_demo ? 1 : $client->total_users }}</td>
            <td class="text-center">{{ $client->balance }}</td>
            <td class="text-center">{{ $client->validate_date?->format('d.m.Y') }}</td>
            <td>{{ $client->last_activity?->format('d.m.Y H:i') }}</td>
            <td class="text-center">
                @if($client->is_active)
                    <p style="color: #00bb00">Активный</p>
                @else
                    <p style="color: red">Неактивный</p>
                @endif
            </td>
            <td class="text-center">
                @if($client->is_demo)
                    Демо версия
                @else
                    Боевая версия
                @endif
            </td>
            <td>{{ $client->partner?->name }}</td>
            <td>{{ $client->country?->name }}</td>
            <td>{{ $client->sub_domain }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
