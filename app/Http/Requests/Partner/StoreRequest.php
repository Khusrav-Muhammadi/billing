<?php

namespace App\Http\Requests\Partner;

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
            'email' => ['required', 'unique:users,email'],
            'address' => ['nullable'],
            'login'  => ['nullable', 'unique:users,login'],
            'password'  => [ 'nullable'],
            'role'  => ['nullable'],
            'phone' => ['required', 'unique:users,phone'],
            'partner_id' => ['nullable']
        ];
    }
}
