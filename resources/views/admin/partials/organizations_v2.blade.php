<table class="table table-hover">
    <thead>
    <tr>
        <th>#</th>
        <th>ID</th>
        <th>Имя</th>
        <th>Телефон</th>
        <th>Страна</th>
        <th>Дата создания</th>
        <th>Почта</th>
        <th>Тариф</th>
        <th>Кол-во пользователей</th>
        <th>Баланс</th>
        <th>Срок действие</th>
        <th>Последняя активность</th>
        <th>Статус</th>
        <th>Партнер</th>
        <th>Поддомен</th>
        <th>Действие</th>
    </tr>
    </thead>
    <tbody>
    @foreach($organizations as $organization)
        <tr class="organization-row"
            data-href="{{ route('organization_v2.show', $organization->id) }}"
            style="cursor: pointer;">
            <td>{{ $loop->iteration }}</td>
            <td>{{ $organization->order_number ?? '-' }}</td>
            <td>{{ $organization->name }}</td>
            <td>{{ $organization->phone }}</td>
            <td>{{ $organization->client?->country?->name }}</td>
            <td>{{ optional($organization->created_at)->format('d.m.Y H:i') }}</td>
            <td>{{ $organization->client?->email }}</td>
            <td>{{ $organization->client?->tariffPrice?->tariff->name }}</td>
            <td>{{ $organization->client?->tariffPrice?->tariff?->user_count ?? '-' }}</td>
            <td>
                {{ number_format((float) ($organization->real_balance ?? 0), 2, '.', ' ') }}
                {{ $organization->client?->country?->currency?->symbol_code ?? '' }}
            </td>
            <td>{{ $organization->client?->validate_date }}</td>
            <td>{{ $organization->client?->last_activity }}</td>
            <td class="text-center">
                @if($organization->client?->is_active)
                    <p style="color: #00bb00">Активный</p>
                @else
                    <p style="color: red">Неактивный</p>
                @endif
            </td>
            <td>{{ $organization->client?->partner?->name }}</td>
            <td>{{ $organization->client?->sub_domain }}</td>
            <td>
                <a href="{{ route('organization_v2.show', $organization->id) }}"><i class="mdi mdi-eye" style="font-size: 30px"></i></a>
                <a href="{{ route('organization.edit', $organization->id) }}"><i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i></a>
                <a href="" data-bs-toggle="modal" data-bs-target="#deleteClient{{$organization->id}}"><i style="color:red; font-size: 30px" class="mdi mdi-delete"></i></a>
            </td>
        </tr>
        <div class="modal fade" id="deleteClient{{$organization->id}}" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <form action="{{ route('organization.destroy', $organization->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLabel">Удалить организацию</h5>
                        </div>
                        <div class="modal-body">
                            Вы уверены что хотите удалить эти данные?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-danger">Удалить</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
    </tbody>
</table>
