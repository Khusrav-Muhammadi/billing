<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTariffRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
            'organization_id' => ['required', Rule::exists('organizations','id')],
            'month' => ['required'],
            'type' => ['required']
        ];
    }
}
