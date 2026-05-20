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
            'user_count' => ['nullable', 'integer', 'min:0'],
            'project_count' => ['nullable', 'integer', 'min:0'],
            'end_date' => ['nullable', 'date'],
            'can_increase' => ['nullable', 'boolean'],
            'is_external' => ['nullable', 'boolean'],
            'is_one_time' => ['nullable', 'boolean'],
            'one_time_label' => ['nullable', 'string', 'max:255'],
            'is_tariff' => ['nullable', 'boolean'],
            'is_extra_user' => ['nullable', 'boolean'],
            'partner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereRaw('LOWER(role) = ?', ['partner']))],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['integer', Rule::exists('tariffs', 'id')],
            'included_services_qty' => ['nullable', 'array'],
            'included_services_qty.*' => ['integer', 'min:1'],
            'excluded_organization_ids' => ['nullable', 'array'],
            'excluded_organization_ids.*' => ['integer', Rule::exists('organizations', 'id')],
            'parent_tariff_id' => [
                Rule::requiredIf(fn () => (bool) $this->input('is_extra_user')),
                'nullable',
                'integer',
                'exists:tariffs,id'
            ],
        ];
    }
}
