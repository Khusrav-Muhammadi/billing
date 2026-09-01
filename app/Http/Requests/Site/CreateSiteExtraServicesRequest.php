<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSiteExtraServicesRequest extends FormRequest
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
            'period_months' => ['required', 'integer', Rule::in([3, 6, 12])],
            'services' => ['required', 'array', 'min:1'],
            'services.*.id' => ['required', 'integer', 'exists:tariffs,id'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'extra_users' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'date' => ['nullable', 'date'],
            'return_url' => ['nullable', 'url'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Укажите organization_id.',
            'payment_type.required' => 'Укажите тип оплаты.',
            'payment_type.in' => 'Тип оплаты должен быть visa, alif или invoice.',
            'period_months.required' => 'Укажите период оплаты.',
            'period_months.in' => 'Период должен быть 3, 6 или 12 месяцев.',
            'services.required' => 'Выберите доп. услуги.',
            'services.min' => 'Выберите хотя бы одну доп. услугу.',
        ];
    }
}
