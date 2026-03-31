<?php

namespace App\Http\Requests\Partner;

use App\Enums\PartnerStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(PartnerStatusEnum::values())],
        ];
    }
}

