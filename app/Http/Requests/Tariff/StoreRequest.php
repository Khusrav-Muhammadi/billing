<?php

namespace App\Http\Requests\Tariff;

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
            'name' => ['required'],
            'price' => ['required'],
            'user_count' => ['nullable', 'integer', 'min:0'],
            'project_count' => ['nullable', 'integer', 'min:0'],
            'is_tariff' => ['nullable', 'boolean'],
            'is_extra_user' => ['nullable', 'boolean'],
            'parent_tariff_id' => [
                Rule::requiredIf(fn () => (bool) $this->input('is_extra_user')),
                'nullable',
                'integer',
                'exists:tariffs,id'
            ],
        ];
    }
}
