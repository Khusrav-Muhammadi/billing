<?php

namespace App\Http\Requests\CurrencyRate;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rate' => ['required', 'numeric', 'gt:0'],
            'rate_date' => ['required', 'date'],
        ];
    }
}
