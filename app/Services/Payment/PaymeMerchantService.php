<?php

namespace App\Services\Payment;

use App\Models\CommercialOffer;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\PaymeBalanceTransaction;
use App\Models\Payment;
use App\Services\CommercialOffers\CommercialOfferPaymeSettlement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymeMerchantService
{
    // Проверочный запрос Payme «сколько к оплате»: 1 сум.
    public const AMOUNT_LOOKUP_TIYIN = 100;

    public function __construct(private CommercialOfferPaymeSettlement $settlement)
    {
    }

    public function handle(string $method, array $params): array
    {
        try {
            return match ($method) {
                'CheckPerformTransaction' => $this->checkPerform($params),
                'CreateTransaction' => $this->create($params),
                'PerformTransaction' => $this->perform($params),
                'CancelTransaction' => $this->cancel($params),
                'CheckTransaction' => $this->check($params),
                'GetStatement' => $this->statement($params),
                default => $this->error(-32601, 'Метод не найден', 'Method not found', 'Metod topilmadi', $method),
            };
        } catch (Throwable $e) {
            Log::error('Payme balance transaction failed', [
                'method' => $method,
                'payme_id' => is_string($params['id'] ?? null) ? $params['id'] : null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->error(-32400, 'Системная ошибка', 'System error', 'Tizim xatosi');
        }
    }

    /**
     * Ссылка и сумма для CRM: последнее неоплаченное КП организации в сумах.
     *
     * @return array{checkout_url: string, amount: int|float, amount_tiyin: int, commercial_offer_id: int}|array{error: string}
     */
    public function checkoutDetails(string $orderNumber): array
    {
        $organization = $this->organization($orderNumber);
        if (!$organization) {
            return ['error' => 'organization'];
        }

        $offer = $this->payableOffer($organization);
        if (!$offer) {
            return ['error' => 'offer'];
        }

        $tiyin = self::toTiyin((string) $offer->payable_total);

        return [
            'checkout_url' => $this->checkoutUrl((string) $organization->order_number, $tiyin),
            'amount' => $tiyin % 100 === 0 ? intdiv($tiyin, 100) : round($tiyin / 100, 2),
            'amount_tiyin' => $tiyin,
            'commercial_offer_id' => (int) $offer->id,
        ];
    }

    public function checkoutUrl(string $orderNumber, int $amountTiyin): string
    {
        $account = $this->normalizeOrderNumber($orderNumber);
        $merchantId = (string) config('services.payme_merchant.merchant_id');
        $checkoutBase = rtrim((string) config('services.payme_merchant.checkout_url'), '/');

        if ($account === null || $amountTiyin <= 0 || $merchantId === '' || $checkoutBase === '') {
            throw new \RuntimeException('Ссылка Payme временно недоступна.');
        }

        $params = 'm=' . rawurlencode($merchantId)
            . ';ac.order_number=' . rawurlencode($account)
            . ';a=' . $amountTiyin;

        return $checkoutBase . '/' . base64_encode($params);
    }

    public function acceptsOffer(CommercialOffer $offer): bool
    {
        $organization = Organization::query()->find($offer->organization_id);
        if (!$organization) {
            return false;
        }

        $payable = $this->payableOffer($organization);

        return $payable !== null && (int) $payable->id === (int) $offer->id;
    }

    public static function toTiyin(string|int|float|null $amount): int
    {
        if (is_float($amount)) {
            $normalized = number_format($amount, 4, '.', '');
        } else {
            $normalized = str_replace([' ', ','], ['', '.'], trim((string) $amount));
        }

        if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return 0;
        }

        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }
        if (strlen($whole) > 15) {
            return 0;
        }

        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);
        $tiyin = ((int) $whole * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $tiyin++;
        }

        return $negative ? -$tiyin : $tiyin;
    }

    public static function tiyinToSum(int $tiyin): string
    {
        $sign = $tiyin < 0 ? '-' : '';
        $tiyin = abs($tiyin);

        return $sign . intdiv($tiyin, 100) . '.' . str_pad((string) ($tiyin % 100), 2, '0', STR_PAD_LEFT);
    }

    public function error(int $code, string $ru, string $en, string $uz, ?string $data = null): array
    {
        $error = ['code' => $code, 'message' => ['ru' => $ru, 'uz' => $uz, 'en' => $en]];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return ['error' => $error];
    }

    private function checkPerform(array $params): array
    {
        $resolved = $this->resolvePayable($params);
        if (isset($resolved['error'])) {
            return $resolved['error'];
        }

        $expected = self::toTiyin((string) $resolved['offer']->payable_total);
        $amount = $this->amount($params);

        if (!in_array($amount, [self::AMOUNT_LOOKUP_TIYIN, $expected], true)) {
            return $this->amountError();
        }

        return ['result' => ['allow' => true, 'additional' => ['amount' => $expected, 'amount_raw' => -$expected]]];
    }

    private function create(array $params): array
    {
        $id = $this->paymeId($params);
        $time = $params['time'] ?? null;
        $amount = $this->amount($params);
        $account = $this->account($params);

        if ($id === null || !is_int($time) || $time < 1000000000000 || $time > 9999999999999 || $amount === null) {
            return $this->invalidRequest();
        }

        return DB::transaction(function () use ($params, $id, $time, $amount, $account): array {
            $existing = PaymeBalanceTransaction::query()->where('payme_id', $id)->lockForUpdate()->first();
            if ($existing) {
                return $existing->payme_time === $time && $existing->account === $account
                    && (int) $existing->amount_tiyin === $amount && (int) $existing->state === 1
                        ? ['result' => $this->createdResult($existing)]
                        : $this->stateError();
            }

            $resolved = $this->resolvePayable($params);
            if (isset($resolved['error'])) {
                return $resolved['error'];
            }

            /** @var Organization $organization */
            $organization = $resolved['organization'];
            /** @var CommercialOffer $offer */
            $offer = CommercialOffer::query()->whereKey($resolved['offer']->id)->lockForUpdate()->first();
            $latest = $this->payableOffer($organization);
            if (!$offer || !$latest || (int) $latest->id !== (int) $offer->id) {
                return $this->unpaidOfferError();
            }

            if ($amount !== self::toTiyin((string) $offer->payable_total)) {
                return $this->amountError();
            }

            $currencyId = $this->currencyId($organization);
            if ($currencyId === null) {
                return $this->error(-32400, 'Системная ошибка', 'System error', 'Tizim xatosi');
            }

            $payment = PaymeBalanceTransaction::query()->create([
                'payme_id' => $id,
                'payme_time' => $time,
                'organization_id' => $organization->id,
                'commercial_offer_id' => $offer->id,
                'account' => $account,
                'amount_tiyin' => $amount,
                'currency_id' => $currencyId,
                'state' => 1,
                'create_time' => $this->milliseconds(),
            ]);

            return ['result' => $this->createdResult($payment)];
        }, 3);
    }

    private function perform(array $params): array
    {
        $id = $this->paymeId($params);
        if ($id === null) {
            return $this->invalidRequest();
        }

        $paidOffer = null;
        $paidStatus = null;

        $result = DB::transaction(function () use ($id, &$paidOffer, &$paidStatus): array {
            $payment = PaymeBalanceTransaction::query()->where('payme_id', $id)->lockForUpdate()->first();
            if (!$payment) {
                return $this->notFound();
            }

            if ((int) $payment->state === 2) {
                return ['result' => $this->performedResult($payment)];
            }

            if ((int) $payment->state !== 1) {
                return $this->stateError();
            }

            if ($this->milliseconds() - $payment->payme_time >= 43200000) {
                $this->cancelPending($payment, 4);

                return $this->stateError();
            }

            $offer = $payment->commercial_offer_id
                ? CommercialOffer::query()->whereKey($payment->commercial_offer_id)->lockForUpdate()->first()
                : null;

            if (!$offer || !$this->canSettle($payment, $offer)) {
                $this->cancelPending($payment, 3);

                return $this->stateError();
            }

            $status = $this->settlement->settle($offer, $payment);
            $payment->state = 2;
            $payment->perform_time = $this->milliseconds();
            $payment->save();

            $paidOffer = $offer->fresh();
            $paidStatus = $status->fresh();

            return ['result' => $this->performedResult($payment)];
        }, 3);

        if ($paidOffer && $paidStatus) {
            $this->settlement->notify($paidOffer, $paidStatus);
        }

        return $result;
    }

    private function cancel(array $params): array
    {
        $id = $this->paymeId($params);
        $reason = $params['reason'] ?? null;

        if ($id === null || !is_int($reason) || !in_array($reason, [1, 2, 3, 4, 5, 10], true)) {
            return $this->invalidRequest();
        }

        return DB::transaction(function () use ($id, $reason): array {
            $payment = PaymeBalanceTransaction::query()->where('payme_id', $id)->lockForUpdate()->first();
            if (!$payment) {
                return $this->notFound();
            }

            if ((int) $payment->state < 0) {
                return ['result' => $this->cancelledResult($payment)];
            }

            if ((int) $payment->state === 2) {
                return $this->stateError();
            }

            $this->cancelPending($payment, $reason);

            return ['result' => $this->cancelledResult($payment)];
        }, 3);
    }

    private function check(array $params): array
    {
        $id = $this->paymeId($params);
        if ($id === null) {
            return $this->invalidRequest();
        }

        $payment = PaymeBalanceTransaction::query()->where('payme_id', $id)->first();

        return $payment ? ['result' => $this->details($payment)] : $this->notFound();
    }

    private function statement(array $params): array
    {
        $from = $params['from'] ?? null;
        $to = $params['to'] ?? null;

        if (!is_int($from) || !is_int($to) || $from < 0 || $to < $from) {
            return $this->invalidRequest();
        }

        $transactions = PaymeBalanceTransaction::query()
            ->whereBetween('payme_time', [$from, $to])
            ->orderBy('payme_time')
            ->orderBy('id')
            ->get()
            ->map(fn (PaymeBalanceTransaction $payment): array => [
                'id' => $payment->payme_id,
                'time' => $payment->payme_time,
                'amount' => $payment->amount_tiyin,
                'account' => ['order_number' => $payment->account],
            ] + $this->details($payment))
            ->all();

        return ['result' => ['transactions' => $transactions]];
    }

    /**
     * Последнее сохранённое неоплаченное КП. Если оно не в сумах, Payme его не берёт.
     */
    private function payableOffer(Organization $organization): ?CommercialOffer
    {
        $offer = CommercialOffer::query()
            ->where('organization_id', $organization->id)
            ->whereNotNull('saved_at')
            ->where('payable_total', '>', 0)
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', ['paid', 'canceled']);
            })
            ->whereDoesntHave('offerStatuses', fn ($query) => $query->where('status', 'paid'))
            ->where(function ($query) {
                $query->whereNull('payment_id')
                    ->orWhereDoesntHave('payment', fn ($query) => $query->whereNotNull('paid_at'));
            })
            ->orderByDesc('id')
            ->first();

        if (!$offer || strtoupper((string) $offer->payable_currency) !== 'UZS') {
            return null;
        }

        return self::toTiyin((string) $offer->payable_total) > 0 ? $offer : null;
    }

    /**
     * @return array{organization: Organization, offer: CommercialOffer}|array{error: array}
     */
    private function resolvePayable(array $params): array
    {
        $account = $this->account($params);
        if ($account === null) {
            return ['error' => $this->accountError()];
        }

        $organization = $this->organization($account);
        if (!$organization) {
            return ['error' => $this->accountError()];
        }

        $offer = $this->payableOffer($organization);
        if (!$offer) {
            return ['error' => $this->unpaidOfferError()];
        }

        return ['organization' => $organization, 'offer' => $offer];
    }

    private function canSettle(PaymeBalanceTransaction $payment, ?CommercialOffer $offer): bool
    {
        if (!$offer || !$payment->commercial_offer_id) {
            return false;
        }

        if ((int) $offer->id !== (int) $payment->commercial_offer_id
            || (int) $offer->organization_id !== (int) $payment->organization_id
            || $offer->saved_at === null
            || in_array((string) $offer->status, ['paid', 'canceled'], true)
            || strtoupper((string) $offer->payable_currency) !== 'UZS'
            || $this->isPaid($offer)) {
            return false;
        }

        return self::toTiyin((string) $offer->payable_total) === (int) $payment->amount_tiyin
            && (int) $payment->amount_tiyin > 0;
    }

    private function isPaid(CommercialOffer $offer): bool
    {
        if ((string) $offer->status === 'paid') {
            return true;
        }

        if ($offer->offerStatuses()->where('status', 'paid')->exists()) {
            return true;
        }

        return $offer->payment_id
            && Payment::query()->whereKey($offer->payment_id)->whereNotNull('paid_at')->exists();
    }

    private function organization(string $account): ?Organization
    {
        $orderNumber = $this->normalizeOrderNumber($account);
        if ($orderNumber === null) {
            return null;
        }

        return Organization::query()
            ->with('client.country')
            ->where('order_number', $orderNumber)
            ->first();
    }

    private function normalizeOrderNumber(string $value): ?string
    {
        $value = trim($value);
        if (!preg_match('/^\d{9}$/', $value)) {
            return null;
        }

        return $value;
    }

    private function account(array $params): ?string
    {
        $account = $params['account'] ?? null;
        $value = is_array($account) ? ($account['order_number'] ?? null) : null;

        return is_string($value) ? $this->normalizeOrderNumber($value) : null;
    }

    private function amount(array $params): ?int
    {
        $amount = $params['amount'] ?? null;
        if (is_int($amount)) {
            return $amount;
        }

        if (is_string($amount) && preg_match('/^\d+$/', $amount)) {
            return (int) $amount;
        }

        return null;
    }

    private function currencyId(Organization $organization): ?int
    {
        $currencyId = (int) ($organization->client?->country?->currency_id ?? 0);
        if ($currencyId > 0) {
            return $currencyId;
        }

        $uzs = Currency::query()->where('symbol_code', 'UZS')->value('id');

        return $uzs ? (int) $uzs : null;
    }

    private function paymeId(array $params): ?string
    {
        $id = $params['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function cancelPending(PaymeBalanceTransaction $payment, int $reason): void
    {
        $payment->state = -1;
        $payment->reason = $reason;
        $payment->cancel_time = $this->milliseconds();
        $payment->save();
    }

    private function createdResult(PaymeBalanceTransaction $payment): array
    {
        return ['create_time' => $payment->create_time, 'transaction' => (string) $payment->id, 'state' => 1];
    }

    private function performedResult(PaymeBalanceTransaction $payment): array
    {
        return ['transaction' => (string) $payment->id, 'perform_time' => $payment->perform_time, 'state' => 2];
    }

    private function cancelledResult(PaymeBalanceTransaction $payment): array
    {
        return ['transaction' => (string) $payment->id, 'cancel_time' => $payment->cancel_time, 'state' => $payment->state];
    }

    private function details(PaymeBalanceTransaction $payment): array
    {
        return [
            'create_time' => $payment->create_time,
            'perform_time' => $payment->perform_time,
            'cancel_time' => $payment->cancel_time,
            'transaction' => (string) $payment->id,
            'state' => $payment->state,
            'reason' => $payment->reason,
        ];
    }

    private function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function accountError(): array
    {
        return $this->error(-31050, 'Организация не найдена', 'Organization not found', 'Tashkilot topilmadi', 'order_number');
    }

    private function unpaidOfferError(): array
    {
        return $this->error(
            -31050,
            'Нет неоплаченного подключения в сумах',
            'No unpaid connection in UZS',
            "So'mda to'lanmagan ulanish yo'q",
            'order_number'
        );
    }

    private function amountError(): array
    {
        return $this->error(-31001, 'Неверная сумма', 'Invalid amount', "Noto'g'ri summa");
    }

    private function notFound(): array
    {
        return $this->error(-31003, 'Транзакция не найдена', 'Transaction not found', 'Tranzaksiya topilmadi');
    }

    private function stateError(): array
    {
        return $this->error(-31008, 'Операция недоступна', 'Operation not allowed', 'Amaliyotga ruxsat berilmagan');
    }

    private function invalidRequest(): array
    {
        return $this->error(-32600, 'Некорректный запрос', 'Invalid request', "Noto'g'ri so'rov");
    }
}
