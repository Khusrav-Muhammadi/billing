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
            'address' => ['required'],
            'login'  => ['required', 'unique:users,login'],
            'password'  => ['required'],
            'role'  => ['required'],
            'phone' => ['required', 'unique:users,phone']
        ];
    }
}
