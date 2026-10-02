<div class="card-body" style="border-top: 1px solid #e5e7eb;">
    <h4 class="card-title">Внедрение — цены ИИ-тарифов</h4>

    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAiImplementationPrice">
        Добавить
    </button>

    <div class="table-responsive mt-3">
        <table class="table table-hover">
            <thead>
            <tr>
                <th>№</th>
                <th>ИИ-тариф</th>
                <th>Валюта</th>
                <th>Сумма</th>
                <th>С</th>
                <th>До</th>
                <th>Действие</th>
            </tr>
            </thead>
            <tbody>
            @foreach($aiPrices as $price)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>
                        {{ $price->plan?->name ?? '—' }}
                        @if($price->plan?->category)
                            <span class="text-muted">
                                ({{ \App\Models\Ai\AiTariffPlan::categoryLabels()[$price->plan->category] ?? $price->plan->category }})
                            </span>
                        @endif
                    </td>
                    <td>{{ $price->currency?->symbol_code ?? '—' }}</td>
                    <td>{{ $price->sum }}</td>
                    <td>{{ optional($price->start_date)->format('Y-m-d') }}</td>
                    <td>{{ optional($price->end_date)->format('Y-m-d') }}</td>
                    <td>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#editAiImplementationPrice{{ $price->id }}">
                            <i class="mdi mdi-pencil-box-outline" style="font-size: 30px"></i>
                        </a>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#deleteAiImplementationPrice{{ $price->id }}">
                            <i style="color:red; font-size: 30px" class="mdi mdi-delete"></i>
                        </a>
                    </td>
                </tr>

                <div class="modal fade" id="editAiImplementationPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('implementation-prices.ai.update', $price->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Изменение цены внедрения ИИ</h5>
                                </div>
                                <div class="modal-body">
                                    @include('admin.implementation.ai-price-fields', [
                                        'price' => $price,
                                        'aiPlans' => $aiPlans,
                                        'currencies' => $currencies,
                                    ])
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="submit" class="btn btn-primary">Сохранить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="modal fade" id="deleteAiImplementationPrice{{ $price->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form action="{{ route('implementation-prices.ai.destroy', $price->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Удаление</h5>
                                </div>
                                <div class="modal-body">
                                    Вы уверены что хотите удалить?
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
    </div>
</div>

<div class="modal fade" id="createAiImplementationPrice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('implementation-prices.ai.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавить цену внедрения ИИ</h5>
                </div>
                <div class="modal-body">
                    @include('admin.implementation.ai-price-fields', [
                        'price' => null,
                        'aiPlans' => $aiPlans,
                        'currencies' => $currencies,
                    ])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </div>
        </form>
    </div>
</div>
