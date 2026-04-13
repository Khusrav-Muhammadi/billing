<?php

namespace App\Http\Controllers;

use App\Events\CommercialOfferPaidStatusEvent;
use App\Events\CommercialOfferExtraServicesPaidStatusEvent;
use App\Events\CommercialOfferRenewalNoChangePaidStatusEvent;
use App\Events\CommercialOfferRenewalPaidStatusEvent;
use App\Models\Account;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferItem;
use App\Models\CommercialOfferStatus;
use App\Models\ConnectedClientServices;
use App\Models\Organization;
use App\Models\PartnerProcent;
use App\Models\Tariff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    private const OFFER_STATUS_DRAFT = 'draft';
    private const REQUEST_TYPES = [
        'connection' => 'Подключение',
        'connection_extra_services' => 'Подключение доп услуг',
        'renewal' => 'Продление (изменение)',
        'renewal_no_changes' => 'Продление',
    ];

    public function index()
    {
        $offers = CommercialOffer::query()
            ->with([
                'tariff:id,name',
                'organization:id,name',
                'partner:id,name,account_id,payment_methods',
                'payment:id,payment_type',
                'items:id,commercial_offer_id,total_price,partner_percent',
                'latestOfferStatus' => function ($query) {
                    $query->select([
                        'commercial_offer_statuses.id',
                        'commercial_offer_statuses.commercial_offer_id',
                        'commercial_offer_statuses.status',
                        'commercial_offer_statuses.status_date',
                        'commercial_offer_statuses.payment_method',
                        'commercial_offer_statuses.account_id',
                        'commercial_offer_statuses.payment_order_number',
                        'commercial_offer_statuses.author_id',
                    ]);
                },
                'offerStatuses:id,commercial_offer_id,status,status_date,payment_method,account_id,payment_order_number,author_id,created_at',
                'offerStatuses.author:id,name',
                'offerStatuses.account:id,name,currency_id',
                'offerStatuses.account.currency:id,symbol_code,name',
            ])
            ->orderByDesc('id')
            ->paginate(20);

        $accounts = Account::query()
            ->with('currency:id,symbol_code,name')
            ->orderBy('name')
            ->get(['id', 'name', 'currency_id']);

        return view('admin.applications.index', compact('offers', 'accounts'));
    }

    public function create(Request $request)
    {
        $requestType = $this->normalizeRequestType((string)$request->query('request_type', 'connection'));

        return $this->renderCreatePage($requestType);
    }

    public function createConnection()
    {
        return $this->renderCreatePage('connection');
    }

    public function createConnectionExtraServices()
    {
        return $this->renderCreatePage('connection_extra_services');
    }

    public function createRenewal()
    {
        return $this->renderCreatePage('renewal');
    }

    public function createRenewalNoChanges()
    {
        return $this->renderCreatePage('renewal_no_changes');
    }

    public function store(Request $request)
    {
        return redirect()->route('application.create');
    }

    public function storeCommercialOffer(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'offer_id' => ['nullable', 'integer', 'exists:commercial_offers,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'payload' => ['required', 'string'],
        ]);

        $payload = json_decode((string)$validated['payload'], true);
        if (!is_array($payload)) {
            throw ValidationException::withMessages([
                'payload' => 'Некорректный формат данных КП.',
            ]);
        }

        $organization = Organization::query()
            ->with('client:id,phone,email,partner_id')
            ->findOrFail((int)$validated['organization_id']);

        $offer = DB::transaction(function () use ($validated, $payload, $organization) {
            $offerId = isset($validated['offer_id']) ? (int)$validated['offer_id'] : null;

            if ($offerId) {
                $offer = CommercialOffer::query()
                    ->lockForUpdate()
                    ->findOrFail($offerId);

                if ($offer->locked_at) {
                    throw ValidationException::withMessages([
                        'offer_id' => 'КП уже заблокировано после генерации ссылки оплаты и не может быть изменено.',
                    ]);
                }
            } else {
                $offer = new CommercialOffer();
                $offer->created_by = Auth::id();
            }

            $requestType = $this->normalizeRequestType((string)data_get($payload, 'request_type', 'connection'));

            $partnerId = $this->toNullableInt(data_get($payload, 'partner_id'));
            if (!$partnerId) {
                $partnerId = $this->toNullableInt($organization->client?->partner_id);
            }
            $partner = null;
            if ($partnerId) {
                $partner = User::query()
                    ->where('id', $partnerId)
                    ->whereRaw('LOWER(role) = ?', ['partner'])
                    ->with('currency:id,symbol_code')
                    ->select('id', 'name', 'phone', 'email', 'currency_id')
                    ->first();
            }
            $partnerCurrencyCode = $this->resolvePartnerBillingCurrency($partner);

            $selectedTariffKey = (string)(data_get($payload, 'selected_tariff_key') ?? '');
            $selectedTariffId = $this->toNullableInt(data_get($payload, 'selected_tariff_id'));
            if (!$selectedTariffId) {
                $selectedTariffId = $this->extractTariffIdFromKey($selectedTariffKey);
            }

            $tariff = null;
            if ($selectedTariffId) {
                $tariff = Tariff::query()->select('id', 'name')->find($selectedTariffId);
            }

            $payerType = (string)(data_get($payload, 'payer.type') ?? ($partner ? 'partner' : 'client'));
            if (!in_array($payerType, ['client', 'partner'], true)) {
                $payerType = $partner ? 'partner' : 'client';
            }

            $clientName = (string)($organization->name ?? data_get($payload, 'client_name', ''));
            $clientPhone = (string)($organization->phone ?: ($organization->client?->phone ?: data_get($payload, 'client_phone', '')));
            $clientEmail = (string)($organization->email ?: ($organization->client?->email ?: data_get($payload, 'client_email', '')));

            $partnerName = (string)(($partner?->name) ?: data_get($payload, 'partner_name', ''));
            $partnerPhone = (string)(($partner?->phone) ?: data_get($payload, 'partner_phone', ''));
            $partnerEmail = (string)(($partner?->email) ?: data_get($payload, 'partner_email', ''));
            $statusDate = $this->toNullableDate(data_get($payload, 'status_date'));
            $pricingDate = $this->toNullableDate(data_get($payload, 'pricing_date'));
            $periodMonths = max(1, (int)data_get($payload, 'period_months', 6));
            $periodDiscountPercent = $this->resolvePeriodDiscountPercent($periodMonths);
            $offerCurrencyCode = $this->toCurrencyCode(data_get($payload, 'currency', 'USD'));
            $payableCurrencyCode = $partnerCurrencyCode
                ?: $this->toCurrencyCode(data_get($payload, 'payable_currency', $offerCurrencyCode));
            $cardPaymentType = (string)data_get($payload, 'card_payment_type', '');
            if ($cardPaymentType === '') {
                $cardPaymentType = $payableCurrencyCode === 'UZS' ? 'alif' : 'octo';
            }
            $conversionRate = $this->toNullableDecimal(data_get($payload, 'conversion_rate'));

            $partnerPercents = [
                'tariff' => 0.0,
                'pack' => 0.0,
            ];
            if ($partner) {
                $asOf = $statusDate ?: now()->toDateString();
                $row = PartnerProcent::query()
                    ->where('partner_id', (int)$partner->id)
                    ->whereDate('date', '<=', $asOf)
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->first(['procent_from_tariff', 'procent_from_pack']);

                if ($row) {
                    $partnerPercents = [
                        'tariff' => (float)max(0, min(100, (float)($row->procent_from_tariff ?? 0))),
                        'pack' => (float)max(0, min(100, (float)($row->procent_from_pack ?? 0))),
                    ];
                }
            }

            $offer->fill([
                'organization_id' => $organization->id,
                'partner_id' => $partner?->id,
                'tariff_id' => $tariff?->id,
                'status' => self::OFFER_STATUS_DRAFT,
                'request_type' => $requestType,
                'saved_at' => now(),
                'status_date' => $statusDate,
                'pricing_date' => $pricingDate,
                'currency' => $offerCurrencyCode,
                'payable_currency' => $payableCurrencyCode,
                'card_payment_type' => $cardPaymentType,
                'period_months' => $periodMonths,
                'extra_users' => max(0, (int)data_get($payload, 'extra_users', 0)),
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $clientEmail,
                'partner_name' => $partnerName !== '' ? $partnerName : null,
                'partner_phone' => $partnerPhone !== '' ? $partnerPhone : null,
                'partner_email' => $partnerEmail !== '' ? $partnerEmail : null,
                'payer_type' => $payerType,
                'manager_name' => (string)data_get($payload, 'manager_name', ''),
                'original_total' => $this->toDecimal(data_get($payload, 'original_total', data_get($payload, 'grand_total', 0))),
                'monthly_total' => $this->toDecimal(data_get($payload, 'monthly_total', 0)),
                'period_total' => $this->toDecimal(data_get($payload, 'period_total', data_get($payload, 'grand_total', 0))),
                'grand_total' => $this->toDecimal(data_get($payload, 'grand_total', 0)),
                'payable_total' => $this->toDecimal(data_get($payload, 'payable_total', data_get($payload, 'grand_total', 0))),
                'conversion_rate' => $conversionRate,
            ]);

            $offer->save();

            $rows = data_get($payload, 'items', []);
            if (!is_array($rows)) {
                $rows = [];
            }

            $offer->items()->delete();
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $tariffId = $this->toNullableInt(data_get($row, 'tariff_id'));
                if (!$tariffId) {
                    $tariffId = $this->extractTariffIdFromAnyKey(data_get($row, 'service_key'));
                }
                if (!$tariffId) {
                    continue;
                }

                $itemTariff = Tariff::query()->select('id', 'is_tariff', 'is_extra_user')->find($tariffId);
                if (!$itemTariff) {
                    continue;
                }

                $quantity = max(1, (float)data_get($row, 'quantity', 1));
                $months = max(1, (int)data_get($row, 'months', $periodMonths));
                $totalPrice = $this->toDecimal(data_get($row, 'total_price', data_get($row, 'price', 0)));
                if ($totalPrice <= 0) {
                    continue;
                }

                $isTariffLine = (bool)($itemTariff->is_tariff) && !(bool)($itemTariff->is_extra_user);
                $discountPercent = $isTariffLine ? $periodDiscountPercent : 0.0;
                $partnerPercent = $partner
                    ? ($isTariffLine ? (float)($partnerPercents['tariff'] ?? 0) : (float)($partnerPercents['pack'] ?? 0))
                    : 0.0;

                $unitPrice = $this->toDecimal(data_get($row, 'unit_price'));
                $unitPrice = $this->normalizeMonthlyUnitPrice($unitPrice, $totalPrice, $quantity, $months, $discountPercent);

                $offer->items()->create([
                    'tariff_id' => (int)$itemTariff->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'months' => $months,
                    'discount_percent' => $discountPercent,
                    'partner_percent' => $partnerPercent,
                    'total_price' => $totalPrice,
                ]);
            }

            if ($offer->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'payload' => 'В КП нет позиций для сохранения.',
                ]);
            }

            // История статусов должна формироваться только через поток оплаты.
            if ($offer->payment_id === null && $offer->offerStatuses()->exists()) {
                $offer->offerStatuses()->delete();
            }

            return $offer;
        });

        return redirect()
            ->route('application.show', $offer)
            ->with('success', 'КП успешно сохранено.');
    }


    private function normalizeMonthlyUnitPrice(
        float $unitPrice,
        float $totalPrice,
        float $quantity,
        int   $months,
        float $discountPercent = 0.0
    ): float
    {
        $qty = max(1.0, (float)$quantity);
        $m = max(1, (int)$months);

        $periodPerUnitNet = $qty > 0 ? ($totalPrice / $qty) : $totalPrice;
        $monthlyPerUnitNetFromTotal = $m > 0 ? ($periodPerUnitNet / $m) : $periodPerUnitNet;

        $discount = round(max(0.0, min(100.0, (float)$discountPercent)), 4);
        $discountFactor = 1 - ($discount / 100);
        if ($discountFactor <= 0 || $discountFactor > 1) {
            $discountFactor = 1;
        }

        $monthlyPerUnitGrossFromTotal = $discountFactor > 0
            ? ($monthlyPerUnitNetFromTotal / $discountFactor)
            : $monthlyPerUnitNetFromTotal;

        $provided = (float)$unitPrice;
        if ($provided <= 0) {
            return $this->toDecimal($monthlyPerUnitGrossFromTotal);
        }

        $tolerance = 0.05;

        // Case 1: unit_price is period net per unit.
        $expectedPeriodNet = $provided * $qty;
        if ($expectedPeriodNet > 0 && abs($totalPrice - $expectedPeriodNet) / $expectedPeriodNet <= $tolerance) {
            $monthlyNet = $provided / $m;
            $monthlyGross = $discountFactor > 0 ? ($monthlyNet / $discountFactor) : $monthlyNet;
            return $this->toDecimal($monthlyGross);
        }

        // Case 2: unit_price is period gross per unit (net total = gross * discountFactor).
        $expectedPeriodNetFromGross = $provided * $qty * $discountFactor;
        if ($expectedPeriodNetFromGross > 0 && abs($totalPrice - $expectedPeriodNetFromGross) / $expectedPeriodNetFromGross <= $tolerance) {
            return $this->toDecimal($provided / $m);
        }

        // Case 3: unit_price is monthly net per unit.
        $expectedPeriodNetFromMonthlyNet = $provided * $qty * $m;
        if ($expectedPeriodNetFromMonthlyNet > 0 && abs($totalPrice - $expectedPeriodNetFromMonthlyNet) / $expectedPeriodNetFromMonthlyNet <= $tolerance) {
            $monthlyGross = $discountFactor > 0 ? ($provided / $discountFactor) : $provided;
            return $this->toDecimal($monthlyGross);
        }

        // Case 4: unit_price is monthly gross per unit (net total = gross * months * discountFactor).
        $expectedPeriodNetFromMonthlyGross = $provided * $qty * $m * $discountFactor;
        if ($expectedPeriodNetFromMonthlyGross > 0 && abs($totalPrice - $expectedPeriodNetFromMonthlyGross) / $expectedPeriodNetFromMonthlyGross <= $tolerance) {
            return $this->toDecimal($provided);
        }

        return $this->toDecimal($monthlyPerUnitGrossFromTotal);
    }

    private function resolvePeriodDiscountPercent(int $periodMonths): float
    {
        $months = max(1, (int)$periodMonths);
        if ($months === 12) {
            return 15.0;
        }
        if ($months === 8) {
            return 50.0;
        }
        return 0.0;
    }

    public function storeCommercialOfferClient(Request $request): RedirectResponse
    {
        return $this->storeCommercialOffer($request);
    }

    public function showCommercialOffer(CommercialOffer $offer)
    {
        $offer->load([
            'items:id,commercial_offer_id,tariff_id,quantity,unit_price,months,total_price',
            'items.tariff:id,name,is_tariff,is_extra_user',
            'tariff:id,name',
            'organization:id,name,phone,email',
            'partner:id,name,phone,email,payment_methods',
            'payment:id,payment_type',
            'latestOfferStatus' => function ($query) {
                $query->select([
                    'commercial_offer_statuses.id',
                    'commercial_offer_statuses.commercial_offer_id',
                    'commercial_offer_statuses.status',
                    'commercial_offer_statuses.status_date',
                    'commercial_offer_statuses.payment_method',
                    'commercial_offer_statuses.account_id',
                    'commercial_offer_statuses.payment_order_number',
                    'commercial_offer_statuses.author_id',
                ]);
            },
        ]);

        $allowedMethods = $this->normalizeAllowedPaymentMethods($offer->partner?->payment_methods);
        $cardPaymentType = $offer->card_payment_type ?: ($offer->payable_currency === 'UZS' ? 'alif' : 'octo');

        return view('admin.applications.show', compact('offer', 'allowedMethods', 'cardPaymentType'));
    }

    public function storeOfferStatus(Request $request, CommercialOffer $offer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,canceled'],
            'status_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:card,invoice,cash'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id', 'required_if:payment_method,invoice'],
            'payment_order_number' => ['nullable', 'string', 'max:100', 'required_if:payment_method,invoice'],
        ]);

        $accountId = isset($validated['account_id']) ? (int)$validated['account_id'] : null;
        if ($validated['payment_method'] !== 'invoice') {
            $accountId = null;
        }

        $paymentOrderNumber = isset($validated['payment_order_number'])
            ? trim((string)$validated['payment_order_number'])
            : null;

        if ($validated['payment_method'] !== 'invoice' || $paymentOrderNumber === '') {
            $paymentOrderNumber = null;
        }

        $statusRecord = $offer->offerStatuses()->create([
            'status' => $validated['status'],
            'status_date' => $validated['status_date'],
            'payment_method' => $validated['payment_method'],
            'account_id' => $accountId,
            'payment_order_number' => $paymentOrderNumber,
            'author_id' => Auth::id(),
        ]);

        $offer->update([
            'status' => $validated['status']
        ]);

        $organization = Organization::query()->find($offer->organization_id);

        $client = Client::find($organization->client_id);
        $client->update([
            'is_active' => 1,
            'is_demo' => 0,
        ]);

        if ((string)$validated['status'] === 'paid') {
            $freshOffer = $offer->fresh();
            $this->syncClientPartnerFromPaidOffer($freshOffer);
            $freshStatus = $statusRecord->fresh();
            $requestType = $this->normalizeRequestType((string)($freshOffer?->request_type ?: 'connection'));

            if ($requestType === 'connection_extra_services') {
                CommercialOfferExtraServicesPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType == 'renewal') {
                CommercialOfferRenewalPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType == 'renewal_no_changes') {
                CommercialOfferRenewalNoChangePaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } else {
                CommercialOfferPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            }
        }

        return redirect()
            ->route('application.index')
            ->with('success', 'Статус подключения сохранен.');
    }

    public function editOfferStatus(Request $request, CommercialOfferStatus $status): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,canceled'],
            'status_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:card,invoice,cash'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id', 'required_if:payment_method,invoice'],
            'payment_order_number' => ['nullable', 'string', 'max:100', 'required_if:payment_method,invoice'],
        ]);

        $accountId = isset($validated['account_id']) ? (int)$validated['account_id'] : null;
        if ($validated['payment_method'] !== 'invoice') {
            $accountId = null;
        }

        $paymentOrderNumber = isset($validated['payment_order_number'])
            ? trim((string)$validated['payment_order_number'])
            : null;

        if ($validated['payment_method'] !== 'invoice' || $paymentOrderNumber === '') {
            $paymentOrderNumber = null;
        }

        $offer = $status->offer;
        
        if ((string)$status->status === 'paid') {
            $oldDate = \App\Support\RegistryDateTimeResolver::resolve($offer, $status);
            
            \App\Models\ClientPaymentRegistry::query()->where('commercial_offer_id', $offer->id)->delete();
            \App\Models\ConnectedClientServices::query()->where('commercial_offer_id', $offer->id)->delete();
            \App\Models\OrganizationConnectionStatus::query()->where('commercial_offer_id', $offer->id)->delete();
            
            \App\Models\ClientBalance::query()
                ->where('organization_id', $offer->organization_id)
                ->where('type', 'income')
                ->where('date', $oldDate)
                ->delete();
                
            \App\Models\PartnerExpense::query()
                ->where('client_id', $offer->organization_id)
                ->where('date', $oldDate)
                ->delete();
                
            \App\Models\DiscountExpense::query()
                ->where('client_id', $offer->organization_id)
                ->where('date', $oldDate)
                ->delete();
        }

        $status->update([
            'status' => $validated['status'],
            'status_date' => $validated['status_date'],
            'payment_method' => $validated['payment_method'],
            'account_id' => $accountId,
            'payment_order_number' => $paymentOrderNumber,
            'author_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);
        
        $offer->update(['status' => $validated['status']]);

        if ((string)$validated['status'] === 'paid') {
            $freshOffer = $offer->fresh();
            $this->syncClientPartnerFromPaidOffer($freshOffer);
            $freshStatus = $status->fresh();
            $requestType = $this->normalizeRequestType((string)($freshOffer?->request_type ?: 'connection'));

            if ($requestType === 'connection_extra_services') {
                \App\Events\CommercialOfferExtraServicesPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType == 'renewal') {
                \App\Events\CommercialOfferRenewalPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } elseif ($requestType == 'renewal_no_changes') {
                \App\Events\CommercialOfferRenewalNoChangePaidStatusEvent::dispatch($freshOffer, $freshStatus);
            } else {
                \App\Events\CommercialOfferPaidStatusEvent::dispatch($freshOffer, $freshStatus);
            }
        }

        return redirect()
            ->route('application.index')
            ->with('success', 'Статус подключения сохранен.');
    }


    private function syncClientPartnerFromPaidOffer(?CommercialOffer $offer): void
    {
        if (!$offer || !$offer->organization_id || !$offer->partner_id) {
            return;
        }

        $organization = Organization::query()
            ->with('client:id,partner_id')
            ->find((int)$offer->organization_id);

        $client = $organization?->client;
        if (!$client) {
            return;
        }

        $partnerId = (int)$offer->partner_id;
        if ((int)$client->partner_id === $partnerId) {
            return;
        }

        $client->partner_id = $partnerId;
        $client->save();
    }

    public function edit(int $id)
    {
        $offer = CommercialOffer::query()->findOrFail($id);

        if ($offer->locked_at) {
            return redirect()
                ->route('application.show', $offer)
                ->withErrors(['error' => 'Это КП уже заблокировано после генерации ссылки оплаты.']);
        }

        return $this->renderCreatePage($this->resolveRequestTypeFromOffer($offer), $offer);
    }

    public function update(int $id, Request $request)
    {
        return redirect()->route('application.edit', $id);
    }

    public function destroy(int $id): RedirectResponse
    {
        $offer = CommercialOffer::query()->findOrFail($id);
        if ($offer->locked_at) {
            return redirect()->back()->withErrors(['error' => 'Нельзя удалить КП после генерации ссылки оплаты.']);
        }

        $offer->delete();

        return redirect()->route('application.index')->with('success', 'КП удалено.');
    }

    public function getCommercialOfferState(CommercialOffer $offer): JsonResponse
    {
        $offer->loadMissing([
            'items:id,commercial_offer_id,tariff_id,quantity,unit_price,months,discount_percent,partner_percent,total_price',
            'items.tariff:id,name,is_tariff,is_extra_user,can_increase',
            'tariff:id,name',
            'tariff.includedServices:id,can_increase',
        ]);

        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'locked' => $offer->locked_at !== null,
                'payload' => $this->buildOfferPayload($offer),
            ],
        ]);
    }

    private function renderCreatePage(string $requestType, ?CommercialOffer $offer = null)
    {
        $normalizedRequestType = $this->normalizeRequestType($requestType);
        $view = match ($normalizedRequestType) {
            'connection_extra_services' => 'admin.applications.create-connection-extra-services',
            'renewal' => 'admin.applications.create-renewal',
            'renewal_no_changes' => 'admin.applications.create-renewal-no-changes',
            default => 'admin.applications.create',
        };

        return view($view, [
            'offer' => $offer,
            'requestType' => $normalizedRequestType,
            'requestTypeLabel' => self::REQUEST_TYPES[$normalizedRequestType],
        ]);
    }

    private function normalizeRequestType(?string $requestType): string
    {
        $normalized = trim((string)$requestType);
        if (!array_key_exists($normalized, self::REQUEST_TYPES)) {
            return 'connection';
        }

        return $normalized;
    }

    private function resolveRequestTypeFromOffer(CommercialOffer $offer): string
    {
        return $this->normalizeRequestType((string)($offer->request_type ?: 'connection'));
    }

    public function getConnectionContext(Organization $organization): JsonResponse
    {
        $rows = ConnectedClientServices::query()
            ->where('client_id', $organization->id)
            ->where('status', true)
            ->whereNotNull('date')
            ->whereDate('date', '<=', now()->toDateString())
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get([
                'id',
                'partner_id',
                'tariff_id',
                'commercial_offer_id',
                'date',
            ]);

        if ($rows->isEmpty()) {
            return response()->json([
                'has_successful_connection' => false,
                'has_active_connected_service' => false,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'order_number' => $organization->order_number,
                ],
                'message' => 'У этой организации нет подключения, сначала сделайте подключение.',
                'connection_create_url' => route('application.create.connection', [
                    'organization_id' => $organization->id,
                ]),
            ]);
        }

        $tariffIds = $rows->pluck('tariff_id')
            ->filter()
            ->map(fn($value) => (int)$value)
            ->unique()
            ->values()
            ->all();

        $tariffsById = Tariff::query()
            ->whereIn('id', $tariffIds)
            ->get(['id', 'name', 'is_tariff', 'is_extra_user', 'can_increase'])
            ->keyBy('id');

        $connectionRow = $rows->first(function ($row) use ($tariffsById) {
            $tariff = $tariffsById->get((int)$row->tariff_id);
            if (!$tariff) {
                return false;
            }

            return (bool)$tariff->is_tariff && !(bool)$tariff->is_extra_user;
        });

        if (!$connectionRow) {
            return response()->json([
                'has_successful_connection' => false,
                'has_active_connected_service' => true,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'order_number' => $organization->order_number,
                ],
                'message' => 'У этой организации нет подключения, сначала сделайте подключение.',
                'connection_create_url' => route('application.create.connection', [
                    'organization_id' => $organization->id,
                ]),
            ]);
        }

        $selectedTariffId = (int)$connectionRow->tariff_id;
        $quantitiesByOfferTariff = $this->buildConnectedServiceQuantitiesMap($rows);
        $selectedServices = $this->buildSelectedServicesFromConnectedRows(
            $selectedTariffId,
            $rows,
            $tariffsById->all(),
            $quantitiesByOfferTariff
        );
        $extraUsers = $this->resolveExtraUsersFromConnectedRows(
            $rows,
            $tariffsById->all(),
            $quantitiesByOfferTariff
        );

        $partnerId = (int)($organization->client?->partner_id ?? 0);
        if ($partnerId <= 0) {
            $partnerId = null;
        }

        $partnerName = null;
        if ($partnerId !== null) {
            $partnerName = User::query()
                ->where('id', $partnerId)
                ->whereRaw('LOWER(role) = ?', ['partner'])
                ->value('name');
        }

        return response()->json([
            'has_successful_connection' => true,
            'has_active_connected_service' => true,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'order_number' => $organization->order_number,
            ],
            'connection_offer' => [
                'id' => $connectionRow->commercial_offer_id ? (int)$connectionRow->commercial_offer_id : null,
                'selected_tariff_key' => $selectedTariffId ? ('tariff-' . (int)$selectedTariffId) : null,
                'partner_id' => $partnerId,
                'partner_name' => $partnerName,
                'extra_users' => $extraUsers,
                'selected_services' => $selectedServices,
            ],
        ]);
    }

    private function buildConnectedServiceQuantitiesMap($rows): array
    {
        $offerIds = collect($rows)
            ->pluck('commercial_offer_id')
            ->filter()
            ->map(fn($value) => (int)$value)
            ->unique()
            ->values()
            ->all();

        if (empty($offerIds)) {
            return [];
        }

        $items = CommercialOfferItem::query()
            ->whereIn('commercial_offer_id', $offerIds)
            ->get(['commercial_offer_id', 'tariff_id', 'quantity']);

        $map = [];
        foreach ($items as $item) {
            $offerId = (int)$item->commercial_offer_id;
            $tariffId = (int)$item->tariff_id;
            $key = $offerId . ':' . $tariffId;
            $map[$key] = ($map[$key] ?? 0.0) + (float)$item->quantity;
        }

        return $map;
    }

    private function resolveConnectedRowQuantity($row, array $quantitiesByOfferTariff): int
    {
        $offerId = (int)($row->commercial_offer_id ?? 0);
        $tariffId = (int)($row->tariff_id ?? 0);

        if ($offerId > 0 && $tariffId > 0) {
            $key = $offerId . ':' . $tariffId;
            if (array_key_exists($key, $quantitiesByOfferTariff)) {
                return max(0, (int)round((float)$quantitiesByOfferTariff[$key]));
            }
        }

        return 1;
    }

    private function buildSelectedServicesFromConnectedRows(
        ?int  $selectedTariffId,
              $rows,
        array $tariffsById,
        array $quantitiesByOfferTariff
    ): array
    {
        $selectedServices = [];
        $processedCountableKeys = [];

        if ($selectedTariffId) {
            $selectedTariff = Tariff::query()
                ->with('includedServices:id,can_increase')
                ->find($selectedTariffId);

            if ($selectedTariff) {
                foreach ($selectedTariff->includedServices as $includedService) {
                    $serviceKey = 'service-' . (int)$includedService->id;
                    $includedChannels = max(0, (int)($includedService->pivot?->quantity ?? 1));
                    $selectedServices[$serviceKey] = [
                        'enabled' => true,
                        'channels' => $includedChannels,
                    ];
                }
            }
        }

        foreach ($rows as $row) {
            $tariff = $tariffsById[(int)$row->tariff_id] ?? null;
            if (!$tariff) {
                continue;
            }

            if ((bool)$tariff->is_tariff || (bool)$tariff->is_extra_user) {
                continue;
            }

            $serviceKey = 'service-' . (int)$tariff->id;
            $quantity = $this->resolveConnectedRowQuantity($row, $quantitiesByOfferTariff);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $hasChannels = (bool)$tariff->can_increase;
            if ($hasChannels) {
                $countableKey = $this->buildConnectedCountableKey($row);
                if (isset($processedCountableKeys[$countableKey])) {
                    continue;
                }
                $processedCountableKeys[$countableKey] = true;

                $currentChannels = (int)data_get($selectedServices, $serviceKey . '.channels', 0);
                $selectedServices[$serviceKey] = [
                    'enabled' => true,
                    'channels' => $currentChannels + $quantity,
                ];
                continue;
            }

            $selectedServices[$serviceKey] = [
                'enabled' => true,
                'channels' => 1,
            ];
        }

        return $this->normalizeSelectedServices($selectedServices);
    }

    private function resolveExtraUsersFromConnectedRows($rows, array $tariffsById, array $quantitiesByOfferTariff): int
    {
        $extraUsers = 0;
        $processedCountableKeys = [];

        foreach ($rows as $row) {
            $tariff = $tariffsById[(int)$row->tariff_id] ?? null;
            if (!$tariff || !(bool)$tariff->is_extra_user) {
                continue;
            }

            $countableKey = $this->buildConnectedCountableKey($row);
            if (isset($processedCountableKeys[$countableKey])) {
                continue;
            }
            $processedCountableKeys[$countableKey] = true;

            $extraUsers += $this->resolveConnectedRowQuantity($row, $quantitiesByOfferTariff);
        }

        return max(0, (int)$extraUsers);
    }

    private function buildConnectedCountableKey($row): string
    {
        $offerId = (int)($row->commercial_offer_id ?? 0);
        $tariffId = (int)($row->tariff_id ?? 0);
        if ($offerId > 0 && $tariffId > 0) {
            return $offerId . ':' . $tariffId;
        }

        return 'row:' . (int)($row->id ?? 0);
    }

    private function buildOfferPayload(CommercialOffer $offer): array
    {
        $requestType = $this->normalizeRequestType((string)($offer->request_type ?: 'connection'));
        $selectedTariffId = $offer->tariff_id ?: $this->resolveSelectedTariffIdFromItems($offer);

        return [
            'offer_id' => $offer->id,
            'request_type' => $requestType,
            'organization_id' => $offer->organization_id,
            'partner_id' => $offer->partner_id,
            'selected_tariff_id' => $selectedTariffId,
            'selected_tariff_key' => $selectedTariffId ? ('tariff-' . (int)$selectedTariffId) : null,
            'period_months' => max(1, (int)($offer->period_months ?: 1)),
            'extra_users' => max(0, (int)$offer->extra_users),
            'status_date' => $offer->status_date ? $offer->status_date->toDateString() : null,
            'pricing_date' => $offer->pricing_date ? $offer->pricing_date->toDateString() : null,
            'currency' => $this->toCurrencyCode($offer->currency),
            'payable_currency' => $this->toCurrencyCode($offer->payable_currency ?: $offer->currency),
            'card_payment_type' => (string)($offer->card_payment_type ?: ($offer->payable_currency === 'UZS' ? 'alif' : 'octo')),
            'conversion_rate' => $offer->conversion_rate !== null ? (float)$offer->conversion_rate : null,
            'manager_name' => (string)($offer->manager_name ?? ''),
            'client_name' => (string)($offer->client_name ?? ''),
            'client_phone' => (string)($offer->client_phone ?? ''),
            'client_email' => (string)($offer->client_email ?? ''),
            'partner_name' => (string)($offer->partner_name ?? ''),
            'partner_phone' => (string)($offer->partner_phone ?? ''),
            'partner_email' => (string)($offer->partner_email ?? ''),
            'monthly_total' => (float)$offer->monthly_total,
            'period_total' => (float)$offer->period_total,
            'grand_total' => (float)$offer->grand_total,
            'original_total' => (float)$offer->original_total,
            'payable_total' => (float)$offer->payable_total,
            'selected_services' => $this->buildSelectedServicesFromOffer($offer, $selectedTariffId),
            'items' => $offer->items
                ->map(fn($item) => [
                    'tariff_id' => (int)$item->tariff_id,
                    'quantity' => (float)$item->quantity,
                    'unit_price' => (float)$item->unit_price,
                    'months' => (int)$item->months,
                    'discount_percent' => (float)$item->discount_percent,
                    'partner_percent' => (float)$item->partner_percent,
                    'total_price' => (float)$item->total_price,
                ])
                ->values()
                ->all(),
        ];
    }

    private function resolveSelectedTariffIdFromItems(CommercialOffer $offer): ?int
    {
        foreach ($offer->items as $item) {
            $tariff = $item->tariff;
            if (!$tariff) {
                continue;
            }
            if ((bool)$tariff->is_tariff && !(bool)$tariff->is_extra_user) {
                return (int)$tariff->id;
            }
        }

        return null;
    }

    private function buildSelectedServicesFromOffer(CommercialOffer $offer, ?int $selectedTariffId): array
    {
        $selectedServices = [];

        if ($selectedTariffId) {
            $selectedTariff = Tariff::query()
                ->with('includedServices:id,can_increase')
                ->find($selectedTariffId);

            if ($selectedTariff) {
                foreach ($selectedTariff->includedServices as $includedService) {
                    $serviceKey = 'service-' . (int)$includedService->id;
                    $includedChannels = max(0, (int)($includedService->pivot?->quantity ?? 1));
                    $selectedServices[$serviceKey] = [
                        'enabled' => true,
                        'channels' => $includedChannels,
                    ];
                }
            }
        }

        foreach ($offer->items as $item) {
            $tariff = $item->tariff;
            if (!$tariff) {
                continue;
            }
            if ((bool)$tariff->is_tariff || (bool)$tariff->is_extra_user) {
                continue;
            }

            $serviceKey = 'service-' . (int)$tariff->id;
            $quantity = max(0, (int)round((float)$item->quantity));
            if ($quantity === 0) {
                $quantity = 1;
            }

            $hasChannels = (bool)$tariff->can_increase;
            if ($hasChannels) {
                $currentChannels = (int)data_get($selectedServices, $serviceKey . '.channels', 0);
                $selectedServices[$serviceKey] = [
                    'enabled' => true,
                    'channels' => $currentChannels + $quantity,
                ];
                continue;
            }

            $selectedServices[$serviceKey] = [
                'enabled' => true,
                'channels' => 1,
            ];
        }

        return $this->normalizeSelectedServices($selectedServices);
    }

    private function normalizeSelectedServices($services): array
    {
        if (!is_array($services)) {
            return [];
        }

        $normalized = [];
        foreach ($services as $serviceKey => $serviceState) {
            if (!is_array($serviceState)) {
                continue;
            }

            $enabled = (bool)data_get($serviceState, 'enabled', false);
            $channels = $this->toNullableInt(data_get($serviceState, 'channels'));
            $normalized[(string)$serviceKey] = [
                'enabled' => $enabled,
                'channels' => max(0, (int)($channels ?? 0)),
            ];
        }

        return $normalized;
    }

    private function normalizeAllowedPaymentMethods($methods): array
    {
        $allowed = ['card', 'invoice', 'cash'];
        $defaults = ['card', 'invoice'];

        if (is_string($methods)) {
            $decoded = json_decode($methods, true);
            if (is_array($decoded)) {
                $methods = $decoded;
            }
        }

        if (!is_array($methods)) {
            return $defaults;
        }

        $set = [];
        foreach ($methods as $method) {
            $code = strtolower(trim((string)$method));
            if (in_array($code, $allowed, true)) {
                $set[$code] = true;
            }
        }

        if (empty($set)) {
            return $defaults;
        }

        $ordered = [];
        foreach ($allowed as $method) {
            if (isset($set[$method])) {
                $ordered[] = $method;
            }
        }

        return $ordered;
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int)$value;
    }

    private function toDecimal($value): float
    {
        $normalized = str_replace(',', '.', (string)$value);
        if (!is_numeric($normalized)) {
            return 0.0;
        }
        return round((float)$normalized, 4);
    }

    private function toNullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', (string)$value);
        if (!is_numeric($normalized)) {
            return null;
        }
        return round((float)$normalized, 6);
    }

    private function toCurrencyCode($value): string
    {
        $code = strtoupper(trim((string)$value));
        if ($code === 'SUM' || $code === 'UZB') {
            $code = 'UZS';
        }
        return $code !== '' ? $code : 'USD';
    }

    private function resolvePartnerBillingCurrency(?User $partner): ?string
    {
        if (!$partner) {
            return null;
        }

        $code = $this->toCurrencyCode($partner->currency?->symbol_code);
        if (!in_array($code, ['USD', 'UZS'], true)) {
            return null;
        }

        return $code;
    }

    private function toNullableDate($value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function extractTariffIdFromKey(?string $key): ?int
    {
        $raw = trim((string)$key);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^tariff-(\d+)$/', $raw, $m)) {
            return (int)$m[1];
        }

        return null;
    }

    private function extractTariffIdFromAnyKey($key): ?int
    {
        $raw = trim((string)$key);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/(?:tariff|service)-(\d+)/', $raw, $matches) === 1) {
            return (int)$matches[1];
        }

        return null;
    }
}
