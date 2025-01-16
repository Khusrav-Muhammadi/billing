<?php

namespace App\Http\Requests\Client;

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
            'name' => ['required'],
            'phone' => ['required'],
            'sub_domain' => ['required'],
            'business_type_id' => ['required', Rule::exists('business_types','id')],
            'sale_id' => ['nullable', Rule::exists('sales','id')],
            'tariff_id' => ['nullable', Rule::exists('tariffs','id')],
        ];
    }
}
