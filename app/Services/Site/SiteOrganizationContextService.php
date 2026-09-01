<?php

namespace App\Services\Site;

use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class SiteOrganizationContextService
{
    public function resolve(int $organizationId): array
    {
        $organization = Organization::query()
            ->with(['client.country.currency'])
            ->find($organizationId);

        if (!$organization) {
            throw ValidationException::withMessages([
                'organization_id' => 'Организация не найдена.',
            ]);
        }

        $country = $organization->client?->country;
        if (!$country) {
            throw ValidationException::withMessages([
                'organization_id' => 'У организации нет страны. Проверьте клиента и country_id.',
            ]);
        }

        $currencyCode = strtoupper(trim((string) ($country->currency?->symbol_code ?? '')));
        if ($currencyCode === '') {
            throw ValidationException::withMessages([
                'organization_id' => 'У страны организации не задана валюта.',
            ]);
        }

        $isUzbekistan = $this->isUzbekistan($country);
        $paymentMethods = $isUzbekistan
            ? [
                ['code' => 'alif', 'label' => 'Alif'],
                ['code' => 'invoice', 'label' => 'Счет'],
            ]
            : [
                ['code' => 'visa', 'label' => 'Visa'],
            ];

        return [
            'organization' => $organization,
            'country' => $country,
            'currency' => $currencyCode,
            'is_uzbekistan' => $isUzbekistan,
            'payment_methods' => $paymentMethods,
            'payment_method_codes' => array_column($paymentMethods, 'code'),
            'payer' => $this->resolvePayer($organization),
        ];
    }

    public function resolvePayer(Organization $organization): array
    {
        $client = $organization->client;
        $name = trim((string) ($organization->name ?: $client?->name ?: ''));
        $phone = trim((string) ($organization->phone ?: $client?->phone ?: ''));
        $email = trim((string) ($organization->email ?: $client?->email ?: ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'У организации нет названия.';
        }
        if ($phone === '') {
            $errors['phone'] = 'У организации нет телефона.';
        }
        if ($email === '') {
            $errors['email'] = 'У организации нет email.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ];
    }

    public function assertPaymentTypeAllowed(array $context, string $paymentType): void
    {
        $normalized = $this->normalizePaymentType($paymentType);
        $allowed = $context['payment_method_codes'] ?? [];

        if (!in_array($normalized, $allowed, true)) {
            throw ValidationException::withMessages([
                'payment_type' => sprintf(
                    'Для этой страны доступны только: %s.',
                    implode(', ', $allowed)
                ),
            ]);
        }
    }

    public function normalizePaymentType(string $type): string
    {
        return match (strtolower(trim($type))) {
            'alif' => 'alif',
            'invoice', 'счет', 'счёт', 'schet' => 'invoice',
            'visa', 'octo' => 'visa',
            default => $type,
        };
    }

    private function isUzbekistan(object $country): bool
    {
        if ((int) ($country->id ?? 0) === 2) {
            return true;
        }

        $name = mb_strtolower(trim((string) ($country->name ?? '')), 'UTF-8');

        return str_contains($name, 'узбек') || str_contains($name, 'uzbekistan');
    }
}
