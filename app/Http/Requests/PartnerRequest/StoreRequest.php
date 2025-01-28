<?php

namespace App\Http\Requests\PartnerRequest;

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
            'partner_id' => ['required', Rule::exists('partners','id')],
            'client_type' => ['required'],
            'name' => ['required'],
            'phone' => ['required'],
            'email' => ['required'],
            'address' => ['required'],
            'organization' => ['required'],
            'is_demo' => ['nullable'],
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
            'date' => ['required'],
        ];
    }
}
