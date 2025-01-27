<table class="table table-hover">
    <thead>
    <tr>
        <th>№</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Email</th>
        <th>Поддомен</th>
        <th>Партнер</th>
        <th>Кол-во организация</th>
        <th>Тариф</th>
        <th>Кол-во пользователей</th>
        <th>Тип подключения</th>
        <th class="text-center">Статус</th>
        <th>Баланс</th>
        <th>Срок действия</th>
        <th>Последняя активность</th>
    </tr>
    </thead>
    <tbody>
    @foreach($clients as $client)
        <tr style="cursor: pointer" data-href="{{ route('client.show', $client->id) }}" onclick="window.location.href=this.dataset.href">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $client->name }}</td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->email }}</td>
            <td>{{ $client->sub_domain }}</td>
            <td>{{ $client->partner?->name }}</td>
            <td class="text-center">{{ $client->organizations_count }}</td>
            <td>{{ $client->tariff?->name }}</td>
            <td class="text-center">{{ $client->total_users }}</td>
            <td class="text-center">
                @if($client->is_demo)
                    Демо версия
                @else
                    Боевая версия
                @endif
            </td>
            <td class="text-center">
                @if($client->is_active)
                    <p style="color: #00bb00">Активный</p>
                @else
                    <p style="color: red">Неактивный</p>
                @endif
            </td>
            <td class="text-center">{{ $client->balance }}</td>
            <td class="text-center">{{ $client->validity_period }}</td>
            <td>{{ $client->last_activity }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
