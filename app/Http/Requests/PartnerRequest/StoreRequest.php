<?php

namespace App\Http\Requests\PartnerRequest;

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
            'client_type' => ['required'],
            'name' => ['required'],
            'phone' => ['required'],
            'email' => ['required'],
            'address' => ['required'],
            'is_demo' => ['nullable'],
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
            'date' => ['required'],
            'sub_domain' => ['required', Rule::unique('clients','sub_domain')],
        ];
    }
}
