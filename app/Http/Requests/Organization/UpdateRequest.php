<?php

namespace App\Http\Requests\Organization;

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
            'INN' => ['required'],
            'license' => ['required'],
            'address' => ['required'],
            'client_id' => ['required', Rule::exists('clients','id')],
            'sale_id' => ['nullable', Rule::exists('sales','id')],
        ];
    }
}
