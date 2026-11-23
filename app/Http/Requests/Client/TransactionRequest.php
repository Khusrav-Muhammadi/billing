<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations','id')],
            'date' => ['required', 'date'],
            // Только пополнение: сумма положительная и с разумным верхом (раньше принималось что угодно, в том числе минус).
            'sum' => ['required', 'numeric', 'gt:0', 'max:1000000000000'],
        ];
    }
}
