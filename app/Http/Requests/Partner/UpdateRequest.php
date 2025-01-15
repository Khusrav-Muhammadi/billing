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
            'name' => ['required'],
            'company' => ['nullable'],
            'email' => ['required'],
            'phone' => ['required'],
            'address' => ['required'],
            'manager_id' => ['required', Rule::exists('users', 'id')],
        ];
    }
}
