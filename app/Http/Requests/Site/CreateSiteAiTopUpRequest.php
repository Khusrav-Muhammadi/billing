<?php

namespace App\Http\Requests\Site;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateSiteAiTopUpRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_id.required' => 'Укажите organization_id.',
            'payment_type.required' => 'Укажите тип оплаты.',
            'payment_type.in' => 'Тип оплаты должен быть visa, alif или invoice.',
            'amount.required' => 'Укажите сумму пополнения.',
            'amount.min' => 'Сумма должна быть больше 0.',
        ];
    }
}
