<?php

namespace App\Http\Requests\Tariff;

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
            'price' => ['nullable'],
            'user_count' => ['nullable', 'integer', 'min:0'],
            'project_count' => ['nullable', 'integer', 'min:0'],
            'end_date' => ['nullable', 'date'],
            'can_increase' => ['nullable', 'boolean'],
            'is_tariff' => ['nullable', 'boolean'],
            'is_extra_user' => ['nullable', 'boolean'],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['integer', Rule::exists('tariffs', 'id')],
            'included_services_qty' => ['nullable', 'array'],
            'included_services_qty.*' => ['integer', 'min:1'],
            'parent_tariff_id' => [
                Rule::requiredIf(fn () => (bool) $this->input('is_extra_user')),
                'nullable',
                'integer',
                'exists:tariffs,id'
            ],
        ];
    }
}
