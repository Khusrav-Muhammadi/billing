<?php

namespace App\Http\Requests\ImplementationDiscountCap;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyRule = Schema::hasColumn('implementation_discount_caps', 'currency_code')
            ? ['required', 'string', 'max:10', 'in:USD,UZS,TJS']
            : ['nullable', 'string', 'max:10'];

        return [
            'period_type' => ['required', 'in:standard,months_12'],
            'currency_code' => $currencyRule,
            'max_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
