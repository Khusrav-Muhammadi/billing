<table class="table table-hover">
    <thead>
    <tr>
        <th>№</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Почта</th>
        <th>Дата создания</th>
        <th>Срок действия</th>
        <th>Последняя активность</th>
        <th>Страна</th>
        <th>Поддомен</th>
    </tr>
    </thead>
    <tbody>
    @foreach($clients as $client)
        <tr style="cursor: pointer" data-href="{{ route('sub_domain.show', $client->id) }}" onclick="window.location.href=this.dataset.href">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $client->name }}</td>
            <td>{{ $client->phone }}</td>
            <td>{{ $client->email }}</td>
            <td class="text-center">{{ $client->created_at?->format('d.m.Y H:i') }}</td>
            <td class="text-center">{{ $client->validate_date?->format('d.m.Y') }}</td>
            <td>{{ $client->last_activity?->format('d.m.Y H:i') }}</td>
            <td>{{ $client->country?->name }}</td>
            <td>{{ $client->sub_domain }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
