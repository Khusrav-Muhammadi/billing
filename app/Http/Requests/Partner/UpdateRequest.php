<?php

namespace App\Http\Requests\Partner;

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
            'name' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->partner)],
            'phone' => ['required', 'string', Rule::unique('users', 'phone')->ignore($this->partner)],
            'login' => ['sometimes', 'string', Rule::unique('users', 'login')->ignore($this->partner)],
            'address' => ['nullable', 'string'],
            'partner_status_id' => ['nullable', Rule::exists('partner_statuses', 'id')],
            'partner_id' => ['nullable']
        ];
    }
}
