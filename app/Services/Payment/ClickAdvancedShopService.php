<?php

namespace App\Services\Payment;

use App\Enums\PaymentType;
use App\Models\ClickBalancePayment;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\Organization;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClickAdvancedShopService
{
    // фиксированная сумма оплаты, заменить расчётом задолженности после согласования правила.
    private const FIXED_AMOUNT = 1000000;

    public function handle(array $payload): array
    {
        $serviceId = $payload['service_id'] ?? null;
        if ((!is_int($serviceId) && !is_string($serviceId))
            || (string) $serviceId !== (string) config('services.click_advanced.service_id')) {
            return $this->result([], -8, 'Неверный service_id');
        }

        $action = $payload['action'] ?? null;

        if ($action === 0) {
            $params = $payload['params'] ?? null;

            $account = $this->account(is_array($params) ? ($params['account'] ?? null) : null);

            try {
                $organization = $account ? $this->organization($account) : null;

                return $organization
                    ? $this->result([], 0, 'Успешно', ['params' => [
                        'account' => $account,
                        'amount' => self::FIXED_AMOUNT,
                        'organization_name' => $organization->legal_name ?: $organization->name,
                        'TIN' => (string) $organization->INN,
                    ]])
                    : $this->result([], -5, 'Организация не найдена');

            } catch (Throwable $e) {
                $this->logFailure($e, 0, 0);

                return $this->result([], -7, 'Проверка организации недоступна');
            }
        }

        if ($action !== 1 && $action !== 2) {
            return $this->result([], -3, 'Действие не поддерживается');
        }

        $ids = [
            'click_paydoc_id' => $payload['click_paydoc_id'] ?? null,
            'attempt_trans_id' => $payload['attempt_trans_id'] ?? null,
        ];

        if ((string) config('services.click_advanced.secret_key') === '') {
            return $this->result($ids, -7, 'Оплата временно недоступна');
        }

        $paydocId = $this->positiveId($ids['click_paydoc_id']);

        $attemptId = $this->positiveId($ids['attempt_trans_id']);

        $params = $payload['params'] ?? null;

        if (!$paydocId || !$attemptId || !is_array($params) || array_is_list($params)) {
            return $this->result($ids, -8, 'Некорректный запрос');
        }

        $signatureError = $this->signatureError($payload, $params);

        if ($signatureError !== 0) {
            return $this->result($ids, $signatureError, $signatureError === -1 ? 'Ошибка подписи' : 'Некорректный запрос');
        }

        $account = $this->account($params['account'] ?? null);

        if (!$account) {
            return $this->result($ids, -5, 'Организация не найдена');
        }

        $amount = $this->amount($params['amount'] ?? null);

        if ($amount !== (self::FIXED_AMOUNT . '.00')) {
            return $this->result($ids, -2, 'Некорректная сумма');
        }

        try {
            return $action === 1
                ? $this->prepare($ids, $paydocId, $attemptId, $account, $amount)
                : $this->confirm($payload, $ids, $paydocId, $attemptId, $account, $amount);

        } catch (Throwable $e) {
            $this->logFailure($e, $paydocId, $attemptId);

            return $this->result($ids, -7, 'Не удалось обработать платёж');
        }
    }

    

    private function signatureError(array $payload, array $params): int
    {
        $signTime = $payload['sign_time'] ?? null;

        $signString = $payload['sign_string'] ?? null;

        $parsedTime = is_string($signTime)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $signTime)
            : false;

        if (!$parsedTime || $parsedTime->format('Y-m-d H:i:s') !== $signTime
            || !is_string($signString) || !preg_match('/^[0-9a-f]{32}$/i', $signString)) {
            return -8;
        }

        $signed = (string) $payload['click_paydoc_id']
            . (string) $payload['attempt_trans_id']
            . (string) $payload['service_id']
            . (string) config('services.click_advanced.secret_key');

        foreach ($params as $value) {
            if (!is_string($value) && !is_int($value) && !(is_float($value) && is_finite($value))) {
                return -8;
            }
            $signed .= (string) $value;
        }

        $signed .= (string) $payload['action'] . $signTime;

        return hash_equals(md5($signed), strtolower($signString)) ? 0 : -1;
    }

    private function prepare(array $ids, int $paydocId, int $attemptId, string $account, string $amount): array
    {
        $existing = ClickBalancePayment::query()
            ->where('click_paydoc_id', $paydocId)
            ->where('attempt_trans_id', $attemptId)
            ->first();

        if ($existing) {
            return $this->preparedResult($ids, $existing, $account, $amount);
        }

        if (ClickBalancePayment::query()->where('paid_paydoc_id', $paydocId)->exists()) {
            return $this->result($ids, -4, 'Платёж уже проведён');
        }

        $previous = ClickBalancePayment::query()->where('click_paydoc_id', $paydocId)->first();

        if ($previous && $previous->account !== $account) {
            return $this->result($ids, -5, 'Организация не совпадает');
        }
        if ($previous && $previous->amount !== $amount) {
            return $this->result($ids, -2, 'Сумма не совпадает');
        }

        $organization = $this->organization($account);
        if (!$organization) {
            return $this->result($ids, -5, 'Организация не найдена');
        }

        try {
            $payment = ClickBalancePayment::query()->create([
                'click_paydoc_id' => $paydocId,
                'attempt_trans_id' => $attemptId,
                'organization_id' => $organization->id,
                'account' => $account,
                'amount' => $amount,
                'currency_id' => $organization->client->country->currency_id,
                'status' => 'prepared',
            ]);

            return $this->preparedResult($ids, $payment, $account, $amount);

        } catch (Throwable $e) {
            try {
                $existing = ClickBalancePayment::query()
                    ->where('click_paydoc_id', $paydocId)
                    ->where('attempt_trans_id', $attemptId)
                    ->first();

                if ($existing) {
                    return $this->preparedResult($ids, $existing, $account, $amount);
                }
            } catch (Throwable) {
        
            }

            $this->logFailure($e, $paydocId, $attemptId);

            return $this->result($ids, -7, 'Не удалось подготовить платёж');
        }
    }

    private function preparedResult(array $ids, ClickBalancePayment $payment, string $account, string $amount): array
    {
        if (ClickBalancePayment::query()
            ->where('paid_paydoc_id', $payment->click_paydoc_id)
            ->where('id', '!=', $payment->id)
            ->exists()) {

            return $this->result($ids, -4, 'Платёж уже проведён');
        }

        if ($payment->account !== $account) {
            return $this->result($ids, -5, 'Организация не совпадает');
        }

        if ($payment->amount !== $amount) {
            return $this->result($ids, -2, 'Сумма не совпадает');
        }

        if ($payment->status === 'cancelled') {
            return $this->result($ids, -9, 'Платёж отменён');
        }

        return $this->result($ids, 0, 'Успешно', [
            'merchant_prepare_id' => (string) $payment->id,
            'params' => ['account' => $account],
        ]);
    }

    private function confirm(array $payload, array $ids, int $paydocId, int $attemptId, string $account, string $amount): array
    {
        $prepareId = filter_var($payload['merchant_prepare_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $providerError = $payload['error'] ?? 0;

        if ($prepareId === false || !is_int($prepareId) || !is_int($providerError) || $providerError > 0) {
            return $this->result($ids, -8, 'Некорректный запрос');
        }

        try {
            return DB::transaction(function () use ($ids, $paydocId, $attemptId, $prepareId, $account, $amount, $providerError) {
                $payment = ClickBalancePayment::query()
                    ->where('click_paydoc_id', $paydocId)
                    ->where('attempt_trans_id', $attemptId)
                    ->lockForUpdate()
                    ->first();

                if (!$payment || (int) $payment->id !== $prepareId) {
                    return $this->result($ids, -6, 'Транзакция не найдена');
                }

                if ($payment->account !== $account) {
                    return $this->result($ids, -5, 'Организация не совпадает');
                }

                if ($payment->amount !== $amount) {
                    return $this->result($ids, -2, 'Сумма не совпадает');
                }

                if ($payment->status === 'cancelled') {
                    return $this->result($ids, -9, 'Платёж отменён');
                }

                if ($payment->status !== 'prepared' && $payment->status !== 'paid') {
                    return $this->result($ids, -8, 'Некорректный статус платежа');
                }

                if ($providerError < 0) {
                    if ($payment->status === 'paid') {
                        $reversal = ClientBalance::query()->create([
                            'date' => now(),
                            'organization_id' => $payment->organization_id,
                            'sum' => $payment->amount,
                            'currency_id' => $payment->currency_id,
                            'type' => 'outcome',
                            'source' => PaymentType::CLICK,
                        ]);
                        
                        $payment->reversal_balance_id = $reversal->id;
                    }

                    $payment->status = 'cancelled';
                    $payment->provider_error = $providerError;
                    $payment->cancelled_at = now();
                    $payment->save();

                    return $this->result($ids, -9, 'Платёж отменён');
                }

                if ($payment->status === 'paid') {
                    return $this->confirmedResult($ids, $payment);
                }

                if (!Organization::query()->whereKey($payment->organization_id)->exists()) {
                    return $this->result($ids, -7, 'Организация недоступна');
                }

                $payment->paid_paydoc_id = $paydocId;

                $payment->save();

                $income = ClientBalance::query()->create([
                    'date' => now(),
                    'organization_id' => $payment->organization_id,
                    'sum' => $payment->amount,
                    'currency_id' => $payment->currency_id,
                    'type' => 'income',
                    'source' => PaymentType::CLICK,
                ]);

                $payment->income_balance_id = $income->id;
                $payment->status = 'paid';
                $payment->paid_at = now();
                $payment->save();

                return $this->confirmedResult($ids, $payment);

            }, 3);
        } catch (Throwable $e) {
            try {
                $paid = ClickBalancePayment::query()->where('paid_paydoc_id', $paydocId)->first();

                if ($paid) {
                    if ((int) $paid->attempt_trans_id !== $attemptId) {
                        return $this->result($ids, -4, 'Платёж уже проведён');
                    }
                    
                    if ((int) $paid->id === $prepareId && $paid->account === $account && $paid->amount === $amount) {
                        return $paid->status === 'paid'
                            ? $this->confirmedResult($ids, $paid)
                            : $this->result($ids, -9, 'Платёж отменён');
                    }
                }
            } catch (Throwable) {
                
            }

            $this->logFailure($e, $paydocId, $attemptId);

            return $this->result($ids, -7, 'Не удалось зачислить платёж');
        }
    }

    private function confirmedResult(array $ids, ClickBalancePayment $payment): array
    {
        return $this->result($ids, 0, 'Успешно', [
            'merchant_confirm_id' => (string) $payment->id,
            'params' => (object) [],
        ]);
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

    private function account(mixed $value): ?string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function amount(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $raw = (string) $value;
        if (!preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $raw, $matches)) {
            return null;
        }

        $normalized = $matches[1] . '.' . str_pad($matches[2] ?? '', 2, '0');

        return $normalized === '0.00' ? null : $normalized;
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_int($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function result(array $ids, int $error, string $note, array $extra = []): array
    {
        return $ids + $extra + ['error' => $error, 'error_note' => $note];
    }

    private function logFailure(Throwable $e, int $paydocId, int $attemptId): void
    {
        Log::error('CLICK balance payment failed', [
            'click_paydoc_id' => $paydocId,
            'attempt_trans_id' => $attemptId,
            'exception' => $e::class,
        ]);
    }

    // меотод был создан для проверки данных в тестовом режиме, но не используется, так как тестовый режим отключен

    // private function validateCallback(array $payload, array $ids, int $paydocId, int $attemptId, string $account, string $amount): array
    // {
    //     $action = $payload['action'];

    //     if ($action === 2) {
    //         $prepareId = filter_var($payload['merchant_prepare_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    //         $providerError = $payload['error'] ?? 0;

    //         if ($prepareId === false || !is_int($providerError) || $providerError > 0) {
    //             return $this->result($ids, -8, 'Некорректный запрос');
    //         }
    //     }

    //     $organization = $this->organization($account);
    //     if (!$organization) {
    //         return $this->result($ids, -5, 'Организация не найдена');
    //     }

    //     Log::info('CLICK Advanced Shop callback validated', [
    //         'action' => $action,
    //         'click_paydoc_id' => $paydocId,
    //         'attempt_trans_id' => $attemptId,
    //         'organization_id' => $organization->id,
    //         'amount' => $amount,
    //     ]);

    //     return $this->result($ids, -7, 'Данные проверены; тестовый режим, платёж не проводится');
    // }
}
