<?php

namespace App\Http\Requests\Tariff;

use Illuminate\Foundation\Http\FormRequest;

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
            'lead_count' => ['required'],
            'user_count' => ['required'],
            'project_count' => ['required'],
        ];
    }
}
