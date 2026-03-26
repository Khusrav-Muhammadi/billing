<?php

namespace App\Http\Controllers;

use App\Http\Requests\Partner\StoreRequest;
use App\Models\Client;
use App\Models\Currency;
use App\Models\Partner;
use App\Models\Tariff;
use App\Models\User;

class ConnectedClientServiceController extends Controller
{
    public function index()
    {
        $currencies = Currency::all()->keyBy('symbol_code');

        $tariffs = Tariff::where('is_tariff', true)
            ->with(['prices.currency'])
            ->get()
            ->filter(fn($t) => $t->prices->isNotEmpty());

        $services = Tariff::where('is_tariff', false)
            ->with(['prices.currency'])
            ->get()
            ->filter(fn($t) => $t->prices->isNotEmpty());

        $clients = Client::select('id', 'name', 'email', 'phone', 'currency_id')
            ->with('currency')
            ->orderBy('name')
            ->get();

        $partners = User::role();

        // Базовый конфиг — цены без client_id (для всех)
        $config = $this->buildConfig($currencies, $tariffs, $services, null);

        // Персональные цены по клиентам — { client_id: { tariff_key: { currency: price } } }
        $clientPrices = $this->buildClientPrices($tariffs, $services);

        return view('kp.index', [
            'config'       => $config,
            'clientPrices' => $clientPrices,
            'clients'      => $clients->map(fn($c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'email'    => $c->email ?? '',
                'phone'    => $c->phone ?? '',
                'currency' => $c->currency?->symbol_code,
            ]),
        ]);
    }

    private function buildConfig($currencies, $tariffs, $services, $clientId = null): array
    {
        $currenciesForJs = [];
        foreach ($currencies as $symbolCode => $currency) {
            $currenciesForJs[$symbolCode] = [
                'symbol' => $currency->symbol_code,
                'name'   => $currency->name,
            ];
        }

        $tariffsForJs = [];
        foreach ($tariffs as $tariff) {
            // Берём только общие цены (client_id = null)
            $prices = [];
            foreach ($tariff->prices->whereNull('client_id') as $price) {
                $symbol = $price->currency?->symbol_code;
                if ($symbol) {
                    $prices[$symbol] = (float) $price->sum;
                }
            }

            if (empty($prices)) continue;

            $key = \Str::slug($tariff->name);

            $tariffsForJs[$key] = [
                'id'              => $tariff->id,
                'name'            => $tariff->name,
                'users'           => $tariff->user_count ?? 0,
                'prices'          => $prices,
                'prices12Months'  => array_map(fn($p) => round($p * 0.85, 2), $prices),
                'extraUserPrice'  => array_map(fn($p) => round($p * 0.10, 2), $prices),
                'includedServices' => [],
                'features'        => [],
            ];
        }

        // Услуги аналогично
        $servicesForJs = [];
        foreach ($services as $service) {
            $prices = [];
            foreach ($service->prices->whereNull('client_id') as $price) {
                $symbol = $price->currency?->symbol_code;
                if ($symbol) {
                    $prices[$symbol] = (float) $price->sum;
                }
            }

            if (empty($prices)) continue;

            $key = \Str::slug($service->name);

            $servicesForJs[$key] = [
                'id'          => $service->id,
                'name'        => $service->name,
                'description' => '',
                'type'        => 'monthly',
                'prices'      => $prices,
                'hasChannels' => false,
            ];
        }

        return [
            'currencies'     => $currenciesForJs,
            'paymentPeriods' => [
                ['months' => 6,  'discount' => 0,  'label' => '6 месяцев'],
                ['months' => 12, 'discount' => 15, 'label' => '12 месяцев (скидка 15%)'],
            ],
            'tariffs'  => $tariffsForJs,
            'services' => $servicesForJs,
            'company'  => [
                'name'    => 'SHAMCRM',
                'phone'   => '+998785557416',
                'email'   => 'info@shamcrm.com',
                'website' => 'shamcrm.com',
            ],
        ];
    }

// Собираем персональные цены для каждого клиента
    private function buildClientPrices($tariffs, $services): array
    {
        $result = [];

        foreach ($tariffs as $tariff) {
            $key = \Str::slug($tariff->name);

            foreach ($tariff->prices->whereNotNull('client_id') as $price) {
                $clientId = $price->client_id;
                $symbol   = $price->currency?->symbol_code;
                if (!$symbol) continue;

                $result[$clientId]['tariffs'][$key][$symbol] = (float) $price->sum;
            }
        }

        foreach ($services as $service) {
            $key = \Str::slug($service->name);

            foreach ($service->prices->whereNotNull('client_id') as $price) {
                $clientId = $price->client_id;
                $symbol   = $price->currency?->symbol_code;
                if (!$symbol) continue;

                $result[$clientId]['services'][$key][$symbol] = (float) $price->sum;
            }
        }

        return $result;
    }
}
