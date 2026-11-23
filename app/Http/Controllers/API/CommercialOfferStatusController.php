<?php

namespace App\Http\Controllers\API;

use App\Events\CommercialOfferAiTopUpPaidStatusEvent;
use App\Events\CommercialOfferExtraServicesPaidStatusEvent;
use App\Events\CommercialOfferPaidStatusEvent;
use App\Events\CommercialOfferRenewalNoChangePaidStatusEvent;
use App\Events\CommercialOfferRenewalPaidStatusEvent;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialOfferStatusController extends Controller
{
    public function index(int $offer): JsonResponse
    {
        $ownedOffer = $this->ownedOffersQuery()
            ->with([
                'offerStatuses' => function ($query) {
                    $query->select([
                        'commercial_offer_statuses.id',
                        'commercial_offer_statuses.commercial_offer_id',
                        'commercial_offer_statuses.status',
                        'commercial_offer_statuses.status_date',
                        'commercial_offer_statuses.payment_method',
                        'commercial_offer_statuses.account_id',
                        'commercial_offer_statuses.payment_order_number',
                        'commercial_offer_statuses.author_id',
                        'commercial_offer_statuses.created_at',
                    ]);
                },
                'offerStatuses.author:id,name',
                'offerStatuses.account:id,name,currency_id',
                'offerStatuses.account.currency:id,symbol_code,name',
            ])
            ->findOrFail($offer);

        return response()->json([
            'statuses' => $ownedOffer->offerStatuses,
        ]);
    }

    public function store(Request $request, int $offer): JsonResponse
    {
        // Статус оплаты подтверждает только бухгалтерия/админ: «оплачено» запускает активацию, тариф в CRM,
        // доход и комиссию партнёра. Раньше любой вошедший мог отметить любое КП оплаченным без денег.
        if (!$this->isAdmin()) {
            return response()->json(['message' => 'Статус оплаты меняет только администратор.'], 403);
        }

        $this->ownedOffersQuery()->findOrFail($offer);

        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,canceled'],
            'status_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:card,invoice,cash'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id', 'required_if:payment_method,invoice'],
            'payment_order_number' => ['nullable', 'string', 'max:100', 'required_if:payment_method,invoice'],
        ]);

        if (!in_array((string) $validated['payment_method'], ['invoice', 'cash'], true)) {
            return response()->json([
                'message' => 'Для этого способа оплаты статус меняется автоматически.',
            ], 422);
        }

        $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
        if ($validated['payment_method'] !== 'invoice') {
            $accountId = null;
        }

        $paymentOrderNumber = isset($validated['payment_order_number'])
            ? trim((string) $validated['payment_order_number'])
            : null;

        if ($validated['payment_method'] !== 'invoice' || $paymentOrderNumber === '') {
            $paymentOrderNumber = null;
        }

        // Блокируем КП на время записи: два одновременных «оплачено» не должны пройти оба.
        [$ownedOffer, $statusRecord] = DB::transaction(function () use ($offer, $validated, $accountId, $paymentOrderNumber) {
            /** @var CommercialOffer $ownedOffer */
            $ownedOffer = $this->ownedOffersQuery()->lockForUpdate()->findOrFail($offer);

            // Повторное «оплачено» снова начисляло доход и комиссию (ключ начислений включает дату статуса).
            if ((string) $validated['status'] === 'paid' && $this->isAlreadyPaid($ownedOffer)) {
                throw ValidationException::withMessages([
                    'status' => 'Это КП уже отмечено оплаченным. Повторно отметить нельзя.',
                ]);
            }

            /** @var CommercialOfferStatus $statusRecord */
            $statusRecord = $ownedOffer->offerStatuses()->create([
                'status' => $validated['status'],
                'status_date' => $validated['status_date'],
                'payment_method' => $validated['payment_method'],
                'account_id' => $accountId,
                'payment_order_number' => $paymentOrderNumber,
                'author_id' => Auth::id(),
            ]);

            $ownedOffer->update([
                'status' => $validated['status'],
            ]);

            return [$ownedOffer, $statusRecord];
        });

        // Клиент включается только после оплаты, а не при любом статусе (pending/canceled тоже включали).
        if ((string) $validated['status'] === 'paid') {
            $organization = Organization::query()->find($ownedOffer->organization_id);
            if ($organization && $organization->client_id) {
                $client = Client::query()->find($organization->client_id);
                if ($client) {
                    $client->update([
                        'is_active' => 1,
                        'is_demo' => 0,
                    ]);
                }
            }
        }

        if ((string) $validated['status'] === 'paid') {
            $freshOffer = $ownedOffer->fresh();
            $freshStatus = $statusRecord->fresh();
            $requestType = trim((string) ($freshOffer?->request_type ?: 'connection'));

            if ($requestType === 'connection_extra_services') {
                CommercialOfferExtraServicesPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType === 'renewal') {
                CommercialOfferRenewalPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType === 'renewal_no_changes') {
                CommercialOfferRenewalNoChangePaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType === 'ai_topup') {
                CommercialOfferAiTopUpPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } else {
                CommercialOfferPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            }
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }

    /** Админ видит все КП, остальные — только свои (созданные ими или где они партнёр). */
    private function ownedOffersQuery(): Builder
    {
        if ($this->isAdmin()) {
            return CommercialOffer::query();
        }

        $userId = (int) Auth::id();

        return CommercialOffer::query()
            ->where(function (Builder $query) use ($userId) {
                $query->where('created_by', $userId)
                    ->orWhere('partner_id', $userId);
            });
    }

    private function isAdmin(): bool
    {
        return mb_strtolower(trim((string) (Auth::user()?->role ?? ''))) === 'admin';
    }

    private function isAlreadyPaid(CommercialOffer $offer): bool
    {
        return (string) $offer->status === 'paid'
            || $offer->offerStatuses()->where('status', 'paid')->exists();
    }
}

