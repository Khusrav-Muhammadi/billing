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
            'user_count' => ['nullable', 'integer', 'min:0'],
            'project_count' => ['nullable', 'integer', 'min:0'],
            // GB хранилища (тариф — включено, услуга add_storage — за единицу).
            'storage_gb' => ['nullable', 'integer', 'min:0'],
            // Тип услуги для CRM (add_user, add_channel, add_storage ...). Раньше правился только в БД.
            'type' => ['nullable', 'string', 'max:64'],
            'end_date' => ['nullable', 'date'],
            'can_increase' => ['nullable', 'boolean'],
            'is_external' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_one_time' => ['nullable', 'boolean'],
            'one_time_label' => ['nullable', 'string', 'max:255'],
            'is_tariff' => ['nullable', 'boolean'],
            'is_extra_user' => ['nullable', 'boolean'],
            'partner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereRaw('LOWER(role) = ?', ['partner']))],
            'included_services' => ['nullable', 'array'],
            'included_services.*' => ['integer', Rule::exists('tariffs', 'id')],
            'included_services_qty' => ['nullable', 'array'],
            'included_services_qty.*' => ['integer', 'min:1'],
            'included_services_paid' => ['nullable', 'array'],
            'included_services_paid.*' => ['nullable', 'boolean'],
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
