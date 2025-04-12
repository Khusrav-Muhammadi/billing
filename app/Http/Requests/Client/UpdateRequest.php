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
            'tariff_id' => ['nullable', Rule::exists('tariffs','id')],
            'country_id' => ['nullable', Rule::exists('countries','id')],
            'sale_id' => [''],
            'is_demo' => [''],
            'email' => ['nullable', 'email'],
            'client_type' => ['nullable'],
            'contact_person' => ['nullable'],
            'partner_id' => ['nullable'],
        ];
    }
}
