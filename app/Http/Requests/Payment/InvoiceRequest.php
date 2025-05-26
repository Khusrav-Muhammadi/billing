<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tariff_name' => ['required', Rule::exists('tariffs','name')],
            'organization_id' => ['required', Rule::exists('organizations','id')]
        ];
    }
}
