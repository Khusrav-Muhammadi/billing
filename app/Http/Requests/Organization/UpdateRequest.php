<?php

namespace App\Http\Requests\Organization;

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
            'phone' => ['required'],
            'email' => ['nullable', 'email'],
            'INN' => ['nullable'],
            'address' => ['required'],
            'business_type_id' => ['nullable', Rule::exists('business_types', 'id')],
            'ai_gift_promo_used' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ai_gift_promo_used')) {
            $this->merge([
                'ai_gift_promo_used' => $this->boolean('ai_gift_promo_used'),
            ]);
        }
    }
}
