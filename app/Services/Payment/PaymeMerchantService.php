<?php

namespace App\Services\Payment;

use App\Enums\PaymentType;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\PaymeBalanceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymeMerchantService
{
    // фиксированная сумма пополнения, в UZS и в тийинах (1 UZS = 100 тийинов)
    public const TOP_UP_UZS = 1000000;
    public const TOP_UP_TIYIN = self::TOP_UP_UZS * 100;

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
            ]);

            return $this->error(-32400, 'Системная ошибка', 'System error', 'Tizim xatosi');
        }
    }

    public function checkoutUrl(string $account): ?string
    {
        if (!$this->organization($account)) {
            return null;
        }

        $merchantId = (string) config('services.payme_merchant.merchant_id');
        $checkoutBase = rtrim((string) config('services.payme_merchant.checkout_url'), '/');

        if ($merchantId === '' || $checkoutBase === '') {
            throw new \RuntimeException('Payme checkout is not configured');
        }

        $params = 'm=' . rawurlencode($merchantId)
            . ';ac.order_number=' . rawurlencode($account)
            . ';a=' . self::TOP_UP_TIYIN;

        return $checkoutBase . '/' . base64_encode($params);
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
        $account = $this->account($params);

        if ($account === null || !$this->organization($account)) {
            return $this->accountError();
        }

        return ($params['amount'] ?? null) === self::TOP_UP_TIYIN
            ? ['result' => ['allow' => true]]
            : $this->amountError();
    }

    private function create(array $params): array
    {
        $id = $this->paymeId($params);

        $time = $params['time'] ?? null;

        if ($id === null || !is_int($time) || $time < 1000000000000 || $time > 9999999999999) {
            return $this->invalidRequest();
        }

        $account = $this->account($params);
        $amount = $params['amount'] ?? null;
        $existing = PaymeBalanceTransaction::query()->where('payme_id', $id)->first();

        if ($existing) {
            return $existing->payme_time === $time && $existing->account === $account
                && $existing->amount_tiyin === $amount && $existing->state === 1
                    ? ['result' => $this->createdResult($existing)]
                    : $this->stateError();
        }

        if ($account === null || !($organization = $this->organization($account))) {
            return $this->accountError();
        }

        if ($amount !== self::TOP_UP_TIYIN) {
            return $this->amountError();
        }

        try {
            $payment = PaymeBalanceTransaction::query()->create([
                'payme_id' => $id,
                'payme_time' => $time,
                'organization_id' => $organization->id,
                'account' => $account,
                'amount_tiyin' => $amount,
                'currency_id' => $organization->client->country->currency_id,
                'state' => 1,
                'create_time' => $this->milliseconds(),
            ]);
        } catch (Throwable $e) {

            $payment = PaymeBalanceTransaction::query()->where('payme_id', $id)->first();

            if (!$payment) {
                throw $e;
            }

            return $payment->payme_time === $time && $payment->account === $account
                && $payment->amount_tiyin === $amount && $payment->state === 1
                    ? ['result' => $this->createdResult($payment)]
                    : $this->stateError();
        }

        return ['result' => $this->createdResult($payment)];
    }

    private function perform(array $params): array
    {
        $id = $this->paymeId($params);

        if ($id === null) {
            return $this->invalidRequest();
        }

        return DB::transaction(function () use ($id): array {
            $payment = PaymeBalanceTransaction::query()->where('payme_id', $id)->lockForUpdate()->first();

            if (!$payment) {
                return $this->notFound();
            }

            if ($payment->state === 2) {
                return ['result' => $this->performedResult($payment)];
            }

            if ($payment->state !== 1) {
                return $this->stateError();
            }

            if ($this->milliseconds() - $payment->payme_time >= 43200000) {
                $payment->state = -1;
                $payment->reason = 4;
                $payment->cancel_time = $this->milliseconds();
                $payment->save();

                return $this->stateError();
            }
            
            if (!Organization::query()->whereKey($payment->organization_id)->exists()) {
                return $this->accountError();
            }

            $income = ClientBalance::query()->create([
                'date' => now(),
                'organization_id' => $payment->organization_id,
                'sum' => $this->sum($payment->amount_tiyin),
                'currency_id' => $payment->currency_id,
                'type' => 'income',
                'source' => PaymentType::PAYME,
            ]);

            $payment->income_balance_id = $income->id;
            $payment->state = 2;
            $payment->perform_time = $this->milliseconds();
            $payment->save();

            return ['result' => $this->performedResult($payment)];
        }, 3);
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

            if ($payment->state < 0) {
                return ['result' => $this->cancelledResult($payment)];
            }

            if ($payment->state === 2) {
                $reversal = ClientBalance::query()->create([
                    'date' => now(),
                    'organization_id' => $payment->organization_id,
                    'sum' => $this->sum($payment->amount_tiyin),
                    'currency_id' => $payment->currency_id,
                    'type' => 'outcome',
                    'source' => PaymentType::PAYME,
                ]);
                $payment->reversal_balance_id = $reversal->id;
                $payment->state = -2;
            } else {
                $payment->state = -1;
            }

            $payment->reason = $reason;
            $payment->cancel_time = $this->milliseconds();
            $payment->save();

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

    private function organization(string $account): ?Organization
    {
        $organization = Organization::query()->with('client.country')
            ->where('order_number', $account)->first();

        $currencyId = Currency::query()->where('symbol_code', 'UZS')->value('id');

        if (!$organization || $organization->order_number !== $account || !$currencyId
            || (int) ($organization->client?->country?->currency_id ?? 0) !== (int) $currencyId) {
            return null;
        }

        $hasTariff = ConnectedClientServices::query()
            ->where('client_id', $organization->id)
            ->where('status', true)
            ->whereNull('deactivated_at')
            ->whereDate('date', '<=', now()->toDateString())
            ->whereHas('tariff', fn ($query) => $query
                ->where('is_tariff', true)
                ->where('is_extra_user', false)
                ->where('is_external', false))
            ->where(function ($query) use ($currencyId) {
                $query->where('offer_currency_id', $currencyId)
                    ->orWhere(function ($query) use ($currencyId) {
                        $query->whereNull('offer_currency_id')
                            ->where(function ($query) use ($currencyId) {
                                $query->where('payable_currency_id', $currencyId)
                                    ->orWhereNull('payable_currency_id');
                            });
                    });
            })
            ->exists();

        return $hasTariff ? $organization : null;
    }

    private function account(array $params): ?string
    {
        $account = $params['account'] ?? null;
        $value = is_array($account) ? ($account['order_number'] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function paymeId(array $params): ?string
    {
        $id = $params['id'] ?? null;

        return is_string($id) ? $id : null;
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

    private function sum(int $tiyin): string
    {
        return intdiv($tiyin, 100) . '.' . str_pad((string) ($tiyin % 100), 2, '0', STR_PAD_LEFT);
    }

    private function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private function accountError(): array
    {
        return $this->error(-31050, 'Организация не найдена', 'Organization not found', 'Tashkilot topilmadi', 'order_number');
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
