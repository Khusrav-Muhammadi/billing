<table class="table table-hover">
    <thead>
    <tr>
        <th>№</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Email</th>
        <th>Партнер</th>
        <th>Кол-во организация</th>
        <th>Тариф</th>
        <th>Кол-во пользователей</th>
        <th>Поддомен</th>
        <th>Баланс</th>
        <th>Статус</th>
        <th>Тип подключения</th>
        <th>Срок действия</th>
        <th>Последняя активность</th>
        <th>Действие</th>
    </tr>
    </thead>
    <tbody>
    @foreach($clients as $client)
        <tr style="cursor: pointer" data-href="{{ route('client.show', $client->id) }}" onclick="window.location.href=this.dataset.href">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $client->name }}</td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->email }}</td>
            <td>{{ $client->partner?->name }}</td>
            <td>{{ $client->organizations_count }}</td>
            <td>{{ $client->tariff?->name }}</td>
            <td>{{ $client->total_users }}</td>
            <td>{{ $client->sub_domain }}</td>
            <td>{{ $client->balance }}</td>
            <td>
                @if($client->is_active)
                    <p style="color: #00bb00">Активный</p>
                @else
                    <p style="color: red">Неактивный</p>
                @endif
            </td>
            <td>
                @if($client->is_demo)
                    Демо версия
                @else
                    Боевая версия
                @endif
            </td>
            <td>{{ $client->srok_daystviya }}</td>
            <td>{{ $client->last_activity }}</td>
            <td>
                <a href="{{ route('client.edit', $client->id) }}">
                    <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                </a>
                <a href="#" data-bs-toggle="modal" data-bs-target="#deleteClient{{ $client->id }}">
                    <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                </a>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
