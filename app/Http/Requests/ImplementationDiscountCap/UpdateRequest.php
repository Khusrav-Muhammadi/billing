<?php

namespace App\Http\Requests\ImplementationDiscountCap;

use App\Models\ImplementationDiscountCap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
            'period_type' => ['required', Rule::in(array_keys(ImplementationDiscountCap::PERIOD_TYPES))],
            'currency_code' => $currencyRule,
            'max_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
