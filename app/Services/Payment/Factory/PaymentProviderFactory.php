<?php

namespace App\Services\Payment\Factory;


use App\Services\Payment\AlifPayProvider;
use App\Services\Payment\Contracts\PaymentProviderInterface;
use App\Services\Payment\Enums\PaymentProviderType;
use App\Services\Payment\InvoiceProvider;
use App\Services\Payment\OctoBankProvider;
use InvalidArgumentException;

class PaymentProviderFactory
{
    private array $providers = [];

    public function __construct()
    {
        $this->registerProvider(PaymentProviderType::ALIF, AlifPayProvider::class);
        $this->registerProvider(PaymentProviderType::OCTOBANK, OctoBankProvider::class);
        $this->registerProvider(PaymentProviderType::INVOICE, InvoiceProvider::class);
    }

    public function registerProvider(PaymentProviderType $paymentType, string $serviceClass): void
    {
        $this->providers[$paymentType->value] = $serviceClass;
    }

    public function create(string $providerName): PaymentProviderInterface
    {
        if (!isset($this->providers[$providerName])) {
            throw new InvalidArgumentException("Payment provider '{$providerName}' not found");
        }

        $serviceClass = $this->providers[$providerName];
        return app($serviceClass);
    }

    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }
}
