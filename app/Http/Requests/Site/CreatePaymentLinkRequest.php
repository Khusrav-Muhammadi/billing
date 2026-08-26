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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'payment_type' => ['required', 'string', Rule::in(['alif', 'visa', 'octo'])],
            'date' => ['nullable', 'date'],
            'period_months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'tariff_id' => ['nullable', 'integer', 'exists:tariffs,id'],
            'extra_users' => ['nullable', 'integer', 'min:0', 'max:10000'],
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
            'name.required' => 'Укажите имя плательщика.',
            'phone.required' => 'Укажите телефон.',
            'email.required' => 'Укажите email.',
            'payment_type.in' => 'Тип оплаты должен быть visa или alif.',
        ];
    }
}
