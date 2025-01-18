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
            'price' => ['required'],
            'user_count' => ['required'],
            'project_count' => ['required'],
        ];
    }
}
