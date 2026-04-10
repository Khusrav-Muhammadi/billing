<?php

namespace App\Http\Requests\CurrencyRate;

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
            'base_currency_id' => [
                'nullable',
                'integer',
                Rule::exists('currencies', 'id')->where(function ($query) {
                    $query->whereRaw('UPPER(symbol_code) IN (?, ?)', ['USD', 'UZS']);
                }),
            ],
            'rate' => ['required', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date'],
        ];
    }
}
