<?php

namespace App\Http\Requests\Payment;

use App\Services\Billing\Enum\PaymentOperationType;
use App\Services\Payment\Enums\PaymentProviderType;
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
            'operation_type' => ['required', Rule::enum(PaymentOperationType::class)],
            'provider' => ['required', Rule::enum(PaymentProviderType::class)],
            'tariff_name' => ['required', Rule::exists('tariffs','name')],
            'organization_id' => ['required', Rule::exists('organizations','id')]
        ];
    }
}
