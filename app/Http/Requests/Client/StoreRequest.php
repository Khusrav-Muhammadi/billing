<?php

namespace App\Http\Requests\Client;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required'],
            'phone' => ['required', Rule::unique('clients', 'phone')],
            'sub_domain' => ['required', Rule::unique('clients', 'sub_domain')],
            'tariff_id' => ['required', Rule::exists('tariffs', 'id')],
            'sale_id' => ['nullable'],
            'is_demo' => ['nullable'],
            'partner_id' => ['nullable', Rule::exists('users', 'id')],
            'country_id' => ['nullable', Rule::exists('countries', 'id')],
            'contact_person' => ['nullable', 'string'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:clients'],
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'partner_request_id' => ['nullable'],
            'nfr' => ['']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Поле "ФИО" обязательно для заполнения.',
            'phone.required' => 'Поле "Телефон" обязательно для заполнения.',
            'phone.unique' => 'Поле "Телефон" уже зарегистриварон.',
            'sub_domain.required' => 'Поле "Поддомен" обязательно для заполнения.',
            'sub_domain.unique' => 'Поле "Поддомен" уже зарегистриварон.',
            'tariff_id.required' => 'Поле "Тариф" обязательно для выбора.',
            'country_id.required' => 'Поле "Страна" обязательно для выбора.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.unique' => 'Данный адрес электронной почты уже зарегистрирован.',
            'client_type.required' => 'Тип клиента обязательно для заполнения',
            'business_type_id.required' => 'Поле "Тип бизнеса" обязательно для заполнения.'
        ];
    }
}
