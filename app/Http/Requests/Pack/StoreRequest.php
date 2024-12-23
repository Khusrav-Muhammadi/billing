<?php

namespace App\Http\Requests\Pack    ;

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
            'type' => ['required'],
            'amount' => ['required', 'integer'],
            'price' => ['required'],
            'tariff_id' => ['required', Rule::exists('tariffs','id')],
        ];
    }
}
