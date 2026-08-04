<?php

namespace App\Services\Ai;

use App\Models\Ai\AiBalance;
use App\Models\Ai\AiBalanceTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Top-up of free ai_balance (wallet), not limited_balance.
 * Reserved prepaid months are unaffected — top-up increases spendable portion.
 */
class AiBalanceTopUpService
{
    public function topUp(int $organizationId, float $amount, ?string $description = null): AiBalance
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Top-up amount must be positive.');
        }

        return DB::transaction(function () use ($organizationId, $amount, $description): AiBalance {
            /** @var AiBalance $balance */
            $balance = AiBalance::query()
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->firstOrFail();

            $balance->ai_balance = round((float) $balance->ai_balance + $amount, 4);
            $balance->save();

            AiBalanceTransaction::query()->create([
                'organization_id' => $balance->organization_id,
                'currency_id' => $balance->currency_id,
                'type' => AiBalanceTransaction::TYPE_TOPUP,
                'target_balance' => AiBalanceTransaction::TARGET_AI_BALANCE,
                'amount' => $amount,
                'description' => $description ?: 'Пополнение ИИ-баланса',
            ]);

            // If agent was off only due to empty spendable/limited — billing cycle
            // or explicit check can re-enable. Re-enable immediately if usable funds exist.
            if (! $balance->is_agent_enabled && $balance->availableForUsageAmount() > 0) {
                $balance->is_agent_enabled = true;
                $balance->scheduled_activation_at = null;
                $balance->save();
                \App\Jobs\Ai\AiAgentToggleJob::dispatchSync(
                    organizationId: (int) $balance->organization_id,
                    enabled: true
                );
            }

            Log::info('AiBalanceTopUpService: topped up', [
                'organization_id' => $organizationId,
                'amount' => $amount,
                'ai_balance' => $balance->ai_balance,
                'spendable' => $balance->spendableWalletAmount(),
                'reserved' => $balance->reservedWalletAmount(),
            ]);

            return $balance->fresh();
        });
    }
}
