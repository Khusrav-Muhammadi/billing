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
            'business_type_id' => ['required', Rule::exists('business_types','id')],
            'tariff_id' => ['nullable', Rule::exists('tariffs','id')],
            'sale_id' => [''],
            'is_demo' => [''],
        ];
    }
}
