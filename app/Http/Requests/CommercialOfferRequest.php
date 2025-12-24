<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommercialOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_name' => 'required|string|max:255',
            'manager_name' => 'required|string|max:255',
            'date' => 'required|date_format:d.m.Y',

            'tariff' => 'required|array',
            'tariff.name' => 'required|string|max:255',
            'tariff.period' => 'required|string|max:100',
            'tariff.period_months' => 'required|integer|min:1',
            'tariff.users_count' => 'required|integer|min:1',
            'tariff.monthly_price' => 'required|numeric|min:0',
            'tariff.currency' => 'nullable|string|max:10',

            'features' => 'required|array|min:1',
            'features.*.title' => 'required|string|max:255',
            'features.*.subtitle' => 'nullable|string|max:255',
            'features.*.included' => 'required|boolean',

            'additional_services' => 'nullable|array',
            'additional_services.*.name' => 'required|string|max:255',
            'additional_services.*.monthly_price' => 'required|numeric|min:0',
            'additional_services.*.quantity' => 'nullable|integer|min:1', // для каналов и т.д.

            'one_time_payments' => 'nullable|array',
            'one_time_payments.*.name' => 'required|string|max:255',
            'one_time_payments.*.price' => 'required|numeric|min:0',

            // Contacts (optional, defaults available)
            'contacts' => 'nullable|array',
            'contacts.phone' => 'nullable|string|max:50',
            'contacts.website' => 'nullable|string|max:100',
            'contacts.telegram' => 'nullable|string|max:100',

            // Validity
            'validity_days' => 'required|integer|min:1|max:365',
        ];
    }

    public function messages(): array
    {
        return [
            'client_name.required' => 'Имя клиента обязательно',
            'manager_name.required' => 'Имя менеджера обязательно',
            'date.required' => 'Дата обязательна',
            'date.date_format' => 'Формат даты должен быть ДД.ММ.ГГГГ',
            'tariff.name.required' => 'Название тарифа обязательно',
            'tariff.period.required' => 'Срок тарифа обязателен',
            'tariff.period_months.required' => 'Количество месяцев обязательно',
            'tariff.monthly_price.required' => 'Ежемесячная стоимость тарифа обязательна',
            'features.required' => 'Список функций обязателен',
            'features.*.title.required' => 'Название функции обязательно',
            'validity_days.required' => 'Срок действия предложения обязателен',
        ];
    }

    /**
     * Get validated data with calculations
     */
    public function getOfferData(): array
    {
        $data = $this->validated();
        $currency = $data['tariff']['currency'] ?? '$';
        $periodMonths = $data['tariff']['period_months'];

        // Apply default contacts
        $data['contacts'] = array_merge([
            'phone' => '+998 78 555 7416',
            'website' => 'shamcrm.com',
            'telegram' => '@shamcrm_uz',
        ], $data['contacts'] ?? []);

        // Ensure arrays exist
        $data['additional_services'] = $data['additional_services'] ?? [];
        $data['one_time_payments'] = $data['one_time_payments'] ?? [];

        // Calculate costs
        $calculations = $this->calculateCosts($data, $periodMonths, $currency);
        $data['calculations'] = $calculations;
        $data['currency'] = $currency;

        return $data;
    }

    /**
     * Calculate all costs
     */
    private function calculateCosts(array $data, int $periodMonths, string $currency): array
    {
        $tariffMonthly = $data['tariff']['monthly_price'];

        // Calculate additional services monthly total
        $additionalServicesMonthly = 0;
        $additionalServicesDetails = [];

        foreach ($data['additional_services'] as $service) {
            $quantity = $service['quantity'] ?? 1;
            $serviceTotal = $service['monthly_price'] * $quantity;
            $additionalServicesMonthly += $serviceTotal;

            $additionalServicesDetails[] = [
                'name' => $service['name'],
                'monthly_price' => $service['monthly_price'],
                'quantity' => $quantity,
                'monthly_total' => $serviceTotal,
            ];
        }

        // Calculate one-time payments total
        $oneTimeTotal = 0;
        foreach ($data['one_time_payments'] as $payment) {
            $oneTimeTotal += $payment['price'];
        }

        // Monthly total (tariff + additional services)
        $monthlyTotal = $tariffMonthly + $additionalServicesMonthly;

        // Period total (monthly * months)
        $periodTotal = $monthlyTotal * $periodMonths;

        // Grand total (period + one-time)
        $grandTotal = $periodTotal + $oneTimeTotal;

        // Format summary string like in screenshot
        $summaryParts = [];
        $summaryParts[] = sprintf('Тариф "%s" %s', $data['tariff']['name'], $this->formatPrice($tariffMonthly, $currency));

        foreach ($additionalServicesDetails as $service) {
            $summaryParts[] = sprintf('%s %s', $service['name'], $this->formatPrice($service['monthly_total'], $currency));
        }

        $summaryString = implode(' + ', $summaryParts) .
            sprintf(' = %s × %d мес', $this->formatPrice($monthlyTotal, $currency), $periodMonths);

        return [
            'tariff_monthly' => $tariffMonthly,
            'tariff_monthly_formatted' => $this->formatPrice($tariffMonthly, $currency),

            'additional_services' => $additionalServicesDetails,
            'additional_services_monthly' => $additionalServicesMonthly,
            'additional_services_monthly_formatted' => $this->formatPrice($additionalServicesMonthly, $currency),

            'monthly_total' => $monthlyTotal,
            'monthly_total_formatted' => $this->formatPrice($monthlyTotal, $currency),

            'period_months' => $periodMonths,
            'period_total' => $periodTotal,
            'period_total_formatted' => $this->formatPrice($periodTotal, $currency),

            'one_time_total' => $oneTimeTotal,
            'one_time_total_formatted' => $this->formatPrice($oneTimeTotal, $currency),

            'grand_total' => $grandTotal,
            'grand_total_formatted' => $this->formatPrice($grandTotal, $currency),

            'summary_string' => $summaryString,
        ];
    }

    /**
     * Format price with currency
     */
    private function formatPrice(float $price, string $currency): string
    {
        $formatted = number_format($price, 2, ',', ' ');

        // Remove ,00 if whole number
        if (str_ends_with($formatted, ',00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return $currency . $formatted;
    }
}
