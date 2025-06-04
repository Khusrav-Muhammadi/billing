<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LegalInfoRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'legal_name' => ['required'],
            'legal_address' => ['required'],
            'INN' => ['required'],
            'phone' => ['required'],
            'director' => ['required'],
        ];
    }
}
