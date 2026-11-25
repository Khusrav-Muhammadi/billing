<?php

namespace App\Services\PartnerProgram;

use App\Enums\PartnerStatusEnum;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\Country;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\PartnerExpense;
use App\Models\PartnerProcent;
use App\Models\PartnerStatusHistory;
use App\Models\ProcentPartner;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Логика партнёрского портала: саморегистрация, одобрение, ставки, статистика.
 * Ничего не меняет для существующих партнёров: у них approval_status = 'approved'.
 */
class PartnerProgramService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $country = Country::query()->find($data['country_id']);

            $user = new User();
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'login' => $data['email'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'partner',
                'status' => PartnerStatusEnum::PARTNER->value,
                'partner_status_id' => 1,
                'country_id' => $country?->id,
                'currency_id' => $country?->currency_id,
                'payment_methods' => ['card', 'invoice'],
                'approval_status' => self::STATUS_PENDING,
                'registered_via' => 'partners_portal',
            ]);
            $user->save();

            $user->forceFill(['sham_link' => 'https://shamcrm.com?partner_id=' . $user->id])->save();

            return $user;
        });
    }

    public function approve(User $partner, ?int $authorId = null): User
    {
        return DB::transaction(function () use ($partner, $authorId) {
            $partner->forceFill([
                'approval_status' => self::STATUS_APPROVED,
                'approved_at' => now(),
            ])->save();

            // Стартовые ставки (Серебро) только если ставок ещё нет: ручные ставки не трогаем.
            if (!PartnerProcent::query()->where('partner_id', $partner->id)->exists()) {
                $this->setPercent(
                    $partner,
                    (int) config('partners_portal.start_percent_tariff', 40),
                    (int) config('partners_portal.start_percent_pack', 30),
                    now()->toDateString()
                );
            }

            // Демо партнёра становится его рабочей CRM (NFR): не отключается и принимает тёплые лиды.
            if ($partner->demo_client_id) {
                Client::query()->whereKey($partner->demo_client_id)->update(['nfr' => true]);
            }

            PartnerStatusHistory::query()->create([
                'partner_id' => $partner->id,
                'date' => now()->toDateString(),
                'status' => $partner->status ?: PartnerStatusEnum::PARTNER->value,
                'author_id' => $authorId,
            ]);

            return $partner->refresh();
        });
    }

    public function reject(User $partner): User
    {
        $partner->forceFill(['approval_status' => self::STATUS_REJECTED])->save();

        return $partner->refresh();
    }

    public function setPercent(User $partner, int $tariffPercent, int $packPercent, ?string $date = null): PartnerProcent
    {
        $date = $date ?: now()->toDateString();

        ProcentPartner::query()->updateOrCreate(
            ['partner_id' => $partner->id],
            ['procent_from_tariff' => $tariffPercent, 'procent_from_pack' => $packPercent]
        );

        return PartnerProcent::query()->updateOrCreate(
            ['partner_id' => $partner->id, 'date' => $date],
            ['procent_from_tariff' => $tariffPercent, 'procent_from_pack' => $packPercent]
        );
    }

    /**
     * Валюта партнёра по договору с портала (UZS, USD, TJS): users.currency_id.
     * Из неё генератор КП берёт валюту, в которой партнёр рассчитывается с shamCRM.
     */
    public function setCurrency(User $partner, string $code): User
    {
        $currency = Currency::query()->whereRaw('UPPER(symbol_code) = ?', [strtoupper($code)])->firstOrFail();
        $partner->forceFill(['currency_id' => $currency->id])->save();

        return $partner;
    }

    public function currentPercent(User $partner): array
    {
        $row = PartnerProcent::query()
            ->where('partner_id', $partner->id)
            ->where('date', '<=', now()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        return [
            'tariff' => (int) ($row->procent_from_tariff ?? 0),
            'pack' => (int) ($row->procent_from_pack ?? 0),
            'since' => $row?->date,
        ];
    }

    /**
     * Выручка партнёра (сумма оплаченных КП, grand_total) в сумах, количество продаж,
     * выгода по месяцам из partner_expenses, клиенты.
     */
    public function stats(User $partner, ?string $since = null): array
    {
        $offers = CommercialOffer::query()
            ->where('partner_id', $partner->id)
            ->where('status', 'paid')
            ->when($since, fn ($q) => $q->where('status_date', '>=', $since))
            ->get(['id', 'currency', 'grand_total', 'status_date', 'request_type']);

        $byCurrency = [];
        foreach ($offers as $offer) {
            $code = strtoupper((string) ($offer->currency ?: 'USD'));
            $byCurrency[$code] = ($byCurrency[$code] ?? 0) + (float) $offer->grand_total;
        }

        $turnoverUzs = 0.0;
        $missingRates = [];
        foreach ($byCurrency as $code => $sum) {
            $converted = $this->convert($sum, $code, 'UZS');
            if ($converted === null) {
                $missingRates[] = $code;
                continue;
            }
            $turnoverUzs += $converted;
        }

        $from = now()->startOfMonth()->subMonths(11)->toDateString();

        // Продажи по месяцам за 12 месяцев (дата оплаты): подключения, продления, прочие (доп. услуги, ИИ).
        $salesByMonth = [];
        foreach ($offers as $offer) {
            if (!$offer->status_date || Carbon::parse($offer->status_date)->toDateString() < $from) {
                continue;
            }
            $month = Carbon::parse($offer->status_date)->format('Y-m');
            $kind = match ((string) $offer->request_type) {
                'connection' => 'connections',
                'renewal', 'renewal_no_changes' => 'renewals',
                default => 'other',
            };
            $salesByMonth[$month] ??= ['connections' => 0, 'renewals' => 0, 'other' => 0];
            $salesByMonth[$month][$kind]++;
        }
        ksort($salesByMonth);
        $benefit = PartnerExpense::query()
            ->where('partner_id', $partner->id)
            ->where('date', '>=', $from)
            ->get(['date', 'partner_amount', 'currency_id']);

        $currencyCodes = Currency::query()->pluck('symbol_code', 'id');
        $benefitByMonth = [];
        foreach ($benefit as $row) {
            $month = Carbon::parse($row->date)->format('Y-m');
            $code = strtoupper((string) ($currencyCodes[$row->currency_id] ?? 'USD'));
            $benefitByMonth[$month][$code] = round(($benefitByMonth[$month][$code] ?? 0) + (float) $row->partner_amount, 2);
        }
        ksort($benefitByMonth);

        // Комиссия по годам за всё время (кабинет показывает «в этом году» и «в прошлом году»).
        $benefitByYear = [];
        $yearRows = PartnerExpense::query()
            ->where('partner_id', $partner->id)
            ->selectRaw('YEAR(date) as y, currency_id, SUM(partner_amount) as total')
            ->groupBy('y', 'currency_id')
            ->get();
        foreach ($yearRows as $row) {
            $code = strtoupper((string) ($currencyCodes[$row->currency_id] ?? 'USD'));
            $year = (string) $row->y;
            $benefitByYear[$year][$code] = round(($benefitByYear[$year][$code] ?? 0) + (float) $row->total, 2);
        }
        ksort($benefitByYear);

        $clients = Client::query()->where('partner_id', $partner->id);

        return [
            'partner_id' => $partner->id,
            'approval_status' => $partner->approval_status ?? self::STATUS_APPROVED,
            'percent' => $this->currentPercent($partner),
            'paid_offers_count' => $offers->count(),
            'turnover_by_currency' => array_map(fn ($v) => round($v, 2), $byCurrency),
            'turnover_uzs' => round($turnoverUzs, 2),
            'missing_rates' => $missingRates,
            // Сколько сумов в одной единице валюты: портал показывает оборот и пороги уровней в валюте договора партнёра.
            'rates_to_uzs' => array_filter([
                'USD' => $this->convert(1, 'USD', 'UZS'),
                'TJS' => $this->convert(1, 'TJS', 'UZS'),
            ], static fn ($rate) => $rate !== null && $rate > 0),
            'benefit_by_month' => $benefitByMonth,
            'sales_by_month' => $salesByMonth,
            'benefit_by_year' => $benefitByYear,
            'clients' => [
                'total' => (clone $clients)->where('is_demo', false)->count(),
                'active' => (clone $clients)->where('is_demo', false)->where('is_active', true)->count(),
                'demo' => (clone $clients)->where('is_demo', true)->count(),
            ],
            'first_paid_at' => $offers->min('status_date'),
        ];
    }

    public function serializePartner(User $partner): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'email' => $partner->email,
            'phone' => $partner->phone,
            'address' => $partner->address,
            'country_id' => $partner->country_id,
            'currency_id' => $partner->currency_id,
            // Код валюты партнёра (UZS, USD, TJS…): портал показывает суммы в ней, пока нет договора.
            'currency' => $partner->currency_id ? strtoupper((string) $partner->currency?->symbol_code) : null,
            'status' => $partner->status,
            'approval_status' => $partner->approval_status ?? self::STATUS_APPROVED,
            'registered_via' => $partner->registered_via ?? null,
            'approved_at' => optional($partner->approved_at)->toDateTimeString() ?? $partner->approved_at,
            'sham_link' => $partner->sham_link ?: 'https://shamcrm.com?partner_id=' . $partner->id,
            'percent' => $this->currentPercent($partner),
            'created_at' => optional($partner->created_at)->toDateTimeString(),
        ];
    }

    /**
     * Карточка для просмотра в админке портала. Только show: в списке партнёров эти связи не грузим.
     * Пароли менеджеров и кураторов не отдаём.
     */
    public function partnerProfile(User $partner): array
    {
        $partner->loadMissing('account:id,name');

        $methods = array_values(array_filter((array) ($partner->payment_methods ?? []), fn ($m) => is_string($m) && $m !== ''));
        if ($methods === []) {
            $methods = ['card', 'invoice'];
        }

        $person = fn (User $user) => [
            'id' => (int) $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
        ];

        $managers = User::query()
            ->where('role', 'manager')
            ->where('partner_id', $partner->id)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        $curators = User::query()
            ->join('partner_curators', 'partner_curators.curator_id', '=', 'users.id')
            ->where('partner_curators.partner_id', $partner->id)
            ->where('users.role', 'manager')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.phone', 'users.email']);

        $percents = PartnerProcent::query()
            ->where('partner_id', $partner->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get(['id', 'date', 'procent_from_tariff', 'procent_from_pack'])
            ->map(fn (PartnerProcent $row) => [
                'date' => $row->date ? Carbon::parse($row->date)->format('Y-m-d') : null,
                'tariff' => (int) $row->procent_from_tariff,
                'pack' => (int) $row->procent_from_pack,
            ])
            ->values()
            ->all();

        $statuses = PartnerStatusHistory::query()
            ->with('author:id,name')
            ->where('partner_id', $partner->id)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PartnerStatusHistory $row) => [
                'date' => optional($row->date)->format('Y-m-d'),
                'status' => (string) $row->status,
                'author' => $row->author?->name,
            ])
            ->values()
            ->all();

        return [
            'payment_methods' => $methods,
            'has_implementation' => (bool) $partner->has_implementation,
            'implementation_required' => (bool) $partner->implementation_required,
            'account' => $partner->account ? ['id' => (int) $partner->account->id, 'name' => $partner->account->name] : null,
            'managers' => $managers->map($person)->values()->all(),
            'curators' => $curators->map($person)->values()->all(),
            'percents' => $percents,
            'statuses' => $statuses,
        ];
    }

    /** Курс по последней записи currency_rates (прямой, обратный или через USD). */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($amount == 0.0 || $from === $to) {
            return $amount;
        }

        $rate = $this->rate($from, $to);
        if ($rate === null && $from !== 'USD' && $to !== 'USD') {
            $a = $this->rate($from, 'USD');
            $b = $this->rate('USD', $to);
            $rate = ($a !== null && $b !== null) ? $a * $b : null;
        }

        return $rate === null ? null : $amount * $rate;
    }

    private function rate(string $from, string $to): ?float
    {
        $ids = Currency::query()->whereIn('symbol_code', [$from, $to])->pluck('id', 'symbol_code');
        $fromId = $ids[$from] ?? null;
        $toId = $ids[$to] ?? null;
        if (!$fromId || !$toId) {
            return null;
        }

        $direct = CurrencyRate::query()
            ->where('base_currency_id', $fromId)->where('quote_currency_id', $toId)
            ->orderByDesc('rate_date')->orderByDesc('id')->value('rate');
        if ((float) $direct > 0) {
            return (float) $direct;
        }

        $inverse = CurrencyRate::query()
            ->where('base_currency_id', $toId)->where('quote_currency_id', $fromId)
            ->orderByDesc('rate_date')->orderByDesc('id')->value('rate');

        return (float) $inverse > 0 ? 1 / (float) $inverse : null;
    }
}
