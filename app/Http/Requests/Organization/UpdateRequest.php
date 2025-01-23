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
            'INN' => ['nullable'],
            'address' => ['required'],
            'business_type_id' => ['nullable', Rule::exists('business_types', 'id')],
        ];
    }
}
