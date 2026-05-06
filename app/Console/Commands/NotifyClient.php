<?php

namespace App\Console\Commands;

use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\OrganizationConnectionStatus;
use App\Services\Mailing\ResendMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NotifyClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify clients when organization balance is close to depletion.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $notified = 0;
        $date = now();
        $asOfDateTime = $date->copy()->endOfDay();
        $daysInMonth = max(1, $date->daysInMonth);

        Organization::query()
            ->with([
                'client:id,name,email,is_active,is_demo,nfr,country_id',
                'client.country:id,currency_id',
                'client.country.currency:id,name,symbol_code',
            ])
            ->whereHas('connections')
            ->whereHas('client', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('is_demo', false)
                    ->where('nfr', false)
                    ->whereNotNull('email');
            })
            ->orderBy('id')
            ->chunkById(200, function ($organizations) use (&$notified, $date, $asOfDateTime, $daysInMonth): void {
                foreach ($organizations as $organization) {
                    if (!$this->hasActiveConnection((int)$organization->id, $asOfDateTime)) {
                        continue;
                    }

                    $services = $this->activeServices((int)$organization->id, $date);
                    if ($services->isEmpty()) {
                        continue;
                    }

                    $currencyId = $this->resolveCurrencyId($services, (int)($organization->client?->country?->currency_id ?? 0));
                    $services = $this->filterServicesByCurrency($services, $currencyId);
                    if ($services->isEmpty()) {
                        continue;
                    }

                    $dailyRate = $this->calculateDailyRate($services, $daysInMonth);
                    if ($dailyRate <= 0) {
                        continue;
                    }

                    $balance = $this->calculateBalance(
                        (int)$organization->id,
                        $currencyId > 0 ? $currencyId : null,
                        $asOfDateTime
                    );
                    $daysLeft = (int)floor(max(0, $balance) / $dailyRate);

                    if ($daysLeft > 10) {
                        continue;
                    }

                    $email = trim((string)($organization->client?->email ?? ''));
                    if ($email === '') {
                        continue;
                    }

                    app(ResendMailService::class)->sendWithView(
                        to: $email,
                        subject: 'Баланс shamCRM скоро закончится',
                        view: 'mail.notify_client',
                        data: [
                            'organization' => $organization,
                            'client' => $organization->client,
                            'services' => $this->formatServices($services),
                            'daysLeft' => $daysLeft,
                            'balance' => round($balance, 4),
                            'dailyRate' => round($dailyRate, 4),
                            'currencyCode' => $this->currencyCode($currencyId),
                        ],
                        sendInternalCopy: false,
                        logContext: [
                            'organization_id' => $organization->id,
                            'client_id' => $organization->client_id,
                            'action' => 'low_balance_email',
                        ]
                    );

                    $notified++;
                }
            });

        $this->info("Sent notifications: {$notified}");

        return self::SUCCESS;
    }

    private function hasActiveConnection(int $organizationId, Carbon $asOfDateTime): bool
    {
        $latestConnection = OrganizationConnectionStatus::query()
            ->where('organization_id', $organizationId)
            ->where('status_date', '<=', $asOfDateTime->format('Y-m-d H:i:s'))
            ->orderByDesc('status_date')
            ->orderByDesc('updated_at')
            ->first();

        return $latestConnection && (string)$latestConnection->status === 'connected';
    }

    private function calculateBalance(int $organizationId, ?int $currencyId, Carbon $asOfDateTime): float
    {
        $query = ClientBalance::query()
            ->where('organization_id', $organizationId)
            ->where('date', '<=', $asOfDateTime->format('Y-m-d H:i:s'));

        if ($currencyId) {
            $query->where('currency_id', $currencyId);
        }

        $income = (float)(clone $query)->where('type', 'income')->sum('sum');
        $outcome = (float)(clone $query)->where('type', 'outcome')->sum('sum');

        return round($income - $outcome, 4);
    }

    /**
     * @param Collection<int, ConnectedClientServices> $services
     */
    private function calculateDailyRate(Collection $services, int $daysInMonth): float
    {
        $monthlyTotal = (float)$services->sum(fn (ConnectedClientServices $service): float => (float)$service->service_total_amount);

        if ($monthlyTotal <= 0 || $daysInMonth <= 0) {
            return 0.0;
        }

        return round($monthlyTotal / $daysInMonth, 4);
    }

    /**
     * @return Collection<int, ConnectedClientServices>
     */
    private function activeServices(int $organizationId, Carbon $date): Collection
    {
        return ConnectedClientServices::query()
            ->where('client_id', $organizationId)
            ->whereDate('date', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('deactivated_at')
                    ->orWhereDate('deactivated_at', '>=', $date->toDateString());
            })
            ->with('tariff:id,name')
            ->orderBy('id')
            ->get([
                'id',
                'client_id',
                'tariff_id',
                'quantity',
                'service_total_amount',
                'payable_currency_id',
                'offer_currency_id',
                'date',
                'deactivated_at',
            ]);
    }

    /**
     * @param Collection<int, ConnectedClientServices> $services
     */
    private function resolveCurrencyId(Collection $services, int $fallbackCurrencyId): int
    {
        $first = $services->first();

        return (int)($first?->offer_currency_id ?: $first?->payable_currency_id ?: $fallbackCurrencyId);
    }

    /**
     * @param Collection<int, ConnectedClientServices> $services
     * @return Collection<int, ConnectedClientServices>
     */
    private function filterServicesByCurrency(Collection $services, int $currencyId): Collection
    {
        if ($currencyId <= 0) {
            return $services->values();
        }

        $filtered = $services
            ->filter(function (ConnectedClientServices $service) use ($currencyId): bool {
                $serviceCurrencyId = (int)($service->offer_currency_id ?: $service->payable_currency_id ?: 0);

                return $serviceCurrencyId === 0 || $serviceCurrencyId === $currencyId;
            })
            ->values();

        return $filtered->isNotEmpty() ? $filtered : $services->values();
    }

    /**
     * @param Collection<int, ConnectedClientServices> $services
     */
    private function formatServices(Collection $services): array
    {
        return $services
            ->map(fn (ConnectedClientServices $service): array => [
                'name' => (string)($service->tariff?->name ?? 'Услуга'),
                'quantity' => max(1, (int)($service->quantity ?? 1)),
                'monthly_amount' => (float)$service->service_total_amount,
            ])
            ->values()
            ->all();
    }

    private function currencyCode(int $currencyId): string
    {
        if ($currencyId <= 0) {
            return '';
        }

        return (string)(Currency::query()->whereKey($currencyId)->value('symbol_code') ?? '');
    }
}
