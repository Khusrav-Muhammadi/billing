<?php

namespace App\Http\Requests\Partner;

use App\Enums\PartnerStatusEnum;
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
            'email' => ['required', 'unique:users,email'],
            'address' => ['nullable'],
            'login'  => ['nullable', 'unique:users,login'],
            'password'  => [ 'nullable'],
            'role'  => ['nullable'],
            'status' => ['nullable', 'string', Rule::in(PartnerStatusEnum::values())],
            'phone' => ['required', 'unique:users,phone'],
            'partner_id' => ['nullable'],
            'procent_from_tariff' => ['required', 'integer', 'min:0', 'max:100'],
            'procent_from_pack' => ['required', 'integer', 'min:0', 'max:100'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', Rule::in(['card', 'invoice', 'cash'])],
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')],
            'currency_id' => [
                'required',
                'integer',
                Rule::exists('currencies', 'id')->where(function ($query) {
                    $query->whereRaw('UPPER(symbol_code) IN (?, ?)', ['USD', 'UZS']);
                }),
            ],
        ];
    }
}
