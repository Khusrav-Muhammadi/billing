<?php

namespace App\Http\Requests\PartnerStatuses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartnerStatusStoreRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required'],
            'organization_connect_percent' => ['required'],
            'connect_amount' => ['required'],
            'tariff_price_percent' => ['required'],
        ];
    }
}
