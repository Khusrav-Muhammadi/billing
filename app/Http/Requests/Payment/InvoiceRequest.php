<?php

namespace App\Http\Requests\Payment;

use App\Models\Tariff;
use App\Models\TariffCurrency;
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
            'organization_id' => ['required', Rule::exists('organizations','id')],
            'months' => ['required', 'in:6,12'],
            'tariff_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $id = (int) $value;
                    if ($id <= 0) {
                        $fail('Выбранный тариф не найден.');
                        return;
                    }

                    $exists = TariffCurrency::query()->whereKey($id)->exists()
                        || Tariff::query()->whereKey($id)->exists();

                    if (!$exists) {
                        $fail('Выбранный тариф не найден.');
                    }
                },
            ],
        ];
    }
}
