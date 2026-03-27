<?php

namespace App\Http\Requests\PriceList;

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
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
            'organization_id' => ['nullable', Rule::exists('organizations','id')],
            'start_date' => ['required', 'date'],
            'date' => ['nullable', 'date'],
            'sum' => ['required'],
            'currency_id' => ['required', Rule::exists('currencies','id')],
        ];
    }
}
