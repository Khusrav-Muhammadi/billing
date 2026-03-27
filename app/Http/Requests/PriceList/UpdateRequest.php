<?php

namespace App\Http\Requests\PriceList;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
            'client_id' => ['nullable', Rule::exists('clients','id')],
            'start_date' => ['nullable', 'date'],
            'date' => ['required', 'date'],
            'sum' => ['required'],
            'currency_id' => ['required', Rule::exists('currencies','id')],
        ];
    }
}
