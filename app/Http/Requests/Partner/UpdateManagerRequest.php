<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagerRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->user)],
            'phone' => ['required', 'string', Rule::unique('users', 'phone')->ignore($this->user)],
            'partner_id' => ['nullable'],
            'password' => ['nullable']
        ];
    }
}
