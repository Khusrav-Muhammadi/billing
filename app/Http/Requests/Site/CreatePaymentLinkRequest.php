<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'payment_type' => ['required', 'string', Rule::in(['alif', 'visa', 'octo', 'invoice'])],
            'tariff_id' => ['required', 'integer', 'exists:tariffs,id'],
            'extra_users' => ['required', 'integer', 'min:0', 'max:10000'],
            'period_months' => ['required', 'integer', Rule::in([3, 6, 12])],
            'date' => ['nullable', 'date'],
            'services' => ['nullable', 'array'],
            'services.*' => ['nullable'],
            'services.*.id' => ['nullable', 'integer'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'return_url' => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Укажите organization_id.',
            'payment_type.required' => 'Укажите тип оплаты.',
            'payment_type.in' => 'Тип оплаты должен быть visa, alif или invoice.',
            'tariff_id.required' => 'Выберите тариф.',
            'extra_users.required' => 'Укажите количество доп. пользователей.',
            'period_months.required' => 'Укажите период оплаты.',
            'period_months.in' => 'Период должен быть 3, 6 или 12 месяцев.',
        ];
    }
}
