<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CommercialOffer;
use App\Models\CommercialOfferItem;
use App\Models\ConnectedClientServices;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\ExchangeRate;
use App\Models\Organization;
use App\Models\PartnerProcent;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommercialFooferController extends Controller
{
    private const REQUEST_TYPES = [
        'connection',
        'connection_extra_services',
        'renewal',
        'renewal_no_changes',
    ];

    public function offers(Request $request): JsonResponse
    {
        $perPage = (int)$request->query('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = $this->ownedOffersQuery()
            ->with([
                'tariff:id,name',
                'organization:id,name,phone,email,order_number',
                'latestOfferStatus' => static function ($query) {
                    $query->select([
                        'commercial_offer_statuses.id',
                        'commercial_offer_statuses.commercial_offer_id',
                        'commercial_offer_statuses.status',
                        'commercial_offer_statuses.status_date',
                        'commercial_offer_statuses.payment_method',
                    ]);
                },
            ])
            ->orderByDesc('id');

        $requestType = trim((string)$request->query('request_type', ''));
        if ($requestType !== '' && in_array($requestType, self::REQUEST_TYPES, true)) {
            $query->where('request_type', $requestType);
        }

        $offers = $query->paginate($perPage);

        return response()->json($offers);
    }

    public function index(Request $request): JsonResponse
    {
        $asOfTs = $this->getAsOfTs($request);
        $clients = $this->buildOrganizationsForCurrentUser($request);
        $organizationIds = collect($clients)
            ->map(static fn($row): int => (int)data_get($row, 'id', 0))
            ->filter(static fn(int $id): bool => $id > 0)
            ->values()
            ->all();
        $allowedOrganizationIds = array_values(array_filter($organizationIds, static fn(int $id): bool => $id > 0));

        $config = $this->buildCommercialConfig($asOfTs);
        $clientPrices = $this->buildClientPricesForOrganizations($asOfTs, $organizationIds);
        $organizationId = (int)$request->query('organization_id', 0);
        if ($organizationId > 0) {
            if (!in_array($organizationId, $allowedOrganizationIds, true)) {
                return response()->json([
                    'message' => 'Организация не найдена или недоступна.',
                ], 403);
            }

            $config = $this->applyOrganizationOverridesToConfig(
                $config,
                (array)($clientPrices[$organizationId] ?? [])
            );
        }

        return response()->json([
            'organization_id' => $organizationId > 0 ? $organizationId : null,
            'config' => $config,
            'client_prices' => $clientPrices,
        ]);
    }

    public function organizations(Request $request): JsonResponse
    {
        $organizations = $this->buildOrganizationsForCurrentUser($request);

        return response()->json([
            'organizations' => $organizations
        ]);
    }

    public function partners(Request $request): JsonResponse
    {
        return response()->json([
            'partners' => $this->buildCurrentPartnerRows($request, $this->getAsOfTs($request)),
        ]);
    }

    public function show(CommercialOffer $commercialOffer): JsonResponse
    {
        $offer = $this->ownedOffersQuery()
            ->whereKey($commercialOffer->id)
            ->with([
                'items:id,commercial_offer_id,tariff_id,quantity,unit_price,months,discount_percent,partner_percent,total_price',
                'items.tariff:id,name,is_tariff,is_extra_user,can_increase,parent_tariff_id',
                'tariff:id,name',
                'organization:id,name,phone,email,order_number',
                'partner:id,name,phone,email,payment_methods',
                'payment:id,payment_type',
                'latestOfferStatus' => static function ($query) {
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
            ])
            ->firstOrFail();

        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'locked' => $offer->locked_at !== null,
                'status' => (string)($offer->status ?? 'draft'),
                'latest_status' => (string)($offer->latestOfferStatus?->status ?? $offer->status ?? 'draft'),
                'payment_type' => (string)($offer->payment?->payment_type ?? ''),
                'payment_link' => (string)($offer->payment_link ?? ''),
                'organization' => $this->buildOrganizationSnapshot($offer->organization),
                'payload' => $this->buildOfferPayload($offer),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
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

        $offer = DB::transaction(function () use ($validated, $payload) {
            $offerId = isset($validated['offer_id']) ? (int)$validated['offer_id'] : null;
            $organizationId = (int)$validated['organization_id'];

            $organization = Organization::query()
                ->with('client:id,phone,email,partner_id')
                ->findOrFail($organizationId);

            if ($offerId) {
                $offer = $this->ownedOffersQuery()->lockForUpdate()->findOrFail($offerId);
                if ($offer->locked_at) {
                    throw ValidationException::withMessages([
                        'offer_id' => 'КП заблокировано и не может быть изменено.',
                    ]);
                }
            } else {
                $offer = new CommercialOffer();
                $offer->created_by = Auth::id();
            }

            $requestType = $this->normalizeRequestType((string)data_get($payload, 'request_type', 'connection'));

            $selectedTariffId = $this->toNullableInt(data_get($payload, 'selected_tariff_id'));
            if (!$selectedTariffId) {
                $selectedTariffId = $this->extractTariffIdFromKey((string)data_get($payload, 'selected_tariff_key', ''));
            }

            $tariff = null;
            if ($selectedTariffId) {
                $tariff = Tariff::query()->select('id', 'name')->find($selectedTariffId);
            }

            $partnerId = (int)Auth::id();
            $partner = User::query()
                ->select('id', 'name', 'phone', 'email')
                ->find($partnerId);

            $clientName = (string)($organization->name ?? data_get($payload, 'client_name', ''));
            $clientPhone = (string)($organization->phone ?: ($organization->client?->phone ?: data_get($payload, 'client_phone', '')));
            $clientEmail = (string)($organization->email ?: ($organization->client?->email ?: data_get($payload, 'client_email', '')));

            $partnerName = (string)(($partner?->name) ?: data_get($payload, 'partner_name', ''));
            $partnerPhone = (string)(($partner?->phone) ?: data_get($payload, 'partner_phone', ''));
            $partnerEmail = (string)(($partner?->email) ?: data_get($payload, 'partner_email', ''));

            $periodMonths = max(1, (int)data_get($payload, 'period_months', 6));

            $offer->fill([
                'organization_id' => $organization->id,
                'partner_id' => $partner?->id,
                'tariff_id' => $tariff?->id,
                'status' => 'draft',
                'request_type' => $requestType,
                'saved_at' => now(),
                'status_date' => $this->toNullableDate(data_get($payload, 'status_date')),
                'pricing_date' => $this->toNullableDate(data_get($payload, 'pricing_date')),
                'currency' => $this->toCurrencyCode(data_get($payload, 'currency', 'USD')),
                'payable_currency' => $this->toCurrencyCode(data_get($payload, 'payable_currency', data_get($payload, 'currency', 'USD'))),
                'card_payment_type' => (string)data_get($payload, 'card_payment_type', 'octo'),
                'period_months' => $periodMonths,
                'extra_users' => max(0, (int)data_get($payload, 'extra_users', 0)),
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $clientEmail,
                'partner_name' => $partnerName !== '' ? $partnerName : null,
                'partner_phone' => $partnerPhone !== '' ? $partnerPhone : null,
                'partner_email' => $partnerEmail !== '' ? $partnerEmail : null,
                'payer_type' => (string)data_get($payload, 'payer.type', 'client'),
                'manager_name' => (string)data_get($payload, 'manager_name', ''),
                'original_total' => $this->toDecimal(data_get($payload, 'original_total', data_get($payload, 'grand_total', 0))),
                'monthly_total' => $this->toDecimal(data_get($payload, 'monthly_total', 0)),
                'period_total' => $this->toDecimal(data_get($payload, 'period_total', data_get($payload, 'grand_total', 0))),
                'grand_total' => $this->toDecimal(data_get($payload, 'grand_total', 0)),
                'payable_total' => $this->toDecimal(data_get($payload, 'payable_total', data_get($payload, 'grand_total', 0))),
                'conversion_rate' => $this->toNullableDecimal(data_get($payload, 'conversion_rate')),
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

                $itemTariff = Tariff::query()->select('id')->find($tariffId);
                if (!$itemTariff) {
                    continue;
                }

                $quantity = max(1, (float)data_get($row, 'quantity', 1));
                $months = max(1, (int)data_get($row, 'months', $periodMonths));
                $totalPrice = $this->toDecimal(data_get($row, 'total_price', data_get($row, 'price', 0)));
                if ($totalPrice <= 0) {
                    continue;
                }

                $unitPrice = $this->toDecimal(data_get($row, 'unit_price', 0));
                if ($unitPrice <= 0) {
                    $unitPrice = $this->toDecimal($totalPrice / max(1, $quantity));
                }

                $offer->items()->create([
                    'tariff_id' => (int)$itemTariff->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'months' => $months,
                    'discount_percent' => $this->toDecimal(data_get($row, 'discount_percent', 0)),
                    'partner_percent' => $this->toDecimal(data_get($row, 'partner_percent', 0)),
                    'total_price' => $totalPrice,
                ]);
            }

            if ($offer->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'payload' => 'В КП нет позиций для сохранения.',
                ]);
            }

            return $offer;
        });

        $offer->load([
            'items:id,commercial_offer_id,tariff_id,quantity,unit_price,months,discount_percent,partner_percent,total_price',
            'items.tariff:id,name,is_tariff,is_extra_user,can_increase,parent_tariff_id',
            'tariff:id,name',
            'organization:id,name,phone,email,order_number',
            'partner:id,name,phone,email,payment_methods',
        ]);

        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'locked' => $offer->locked_at !== null,
                'organization' => $this->buildOrganizationSnapshot($offer->organization),
                'payload' => $this->buildOfferPayload($offer),
            ],
        ]);
    }

    public function update(Request $request, CommercialOffer $commercialOffer): JsonResponse
    {
        $request->merge(['offer_id' => $commercialOffer->id]);

        return $this->store($request);
    }

    public function connectionContext(Organization $organization): JsonResponse
    {
        $rows = ConnectedClientServices::query()
            ->where('client_id', $organization->id)
            ->where('status', true)
            ->whereNotNull('date')
            ->whereDate('date', '<=', now()->toDateString())
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
                'connection_create_url' => null,
            ]);
        }

        $tariffIds = $rows->pluck('tariff_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $tariffsById = Tariff::query()
            ->whereIn('id', $tariffIds)
            ->get(['id', 'name', 'is_tariff', 'is_extra_user', 'can_increase'])
            ->keyBy('id');

        $connectionRow = $rows->first(function ($row) use ($tariffsById) {
            $tariff = $tariffsById->get((int) $row->tariff_id);
            if (!$tariff) {
                return false;
            }

            return (bool) $tariff->is_tariff && !(bool) $tariff->is_extra_user;
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
                'connection_create_url' => null,
            ]);
        }

        $selectedTariffId = (int) $connectionRow->tariff_id;
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

        $partnerId = (int) ($organization->client?->partner_id ?? 0);
        if ($partnerId <= 0) {
            $partnerId = (int) ($connectionRow->partner_id ?? 0);
        }
        $partnerId = $partnerId > 0 ? $partnerId : null;

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
                'id' => $connectionRow->commercial_offer_id ? (int) $connectionRow->commercial_offer_id : null,
                'selected_tariff_key' => $selectedTariffId ? ('tariff-' . (int) $selectedTariffId) : null,
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
            ->map(fn ($value) => (int) $value)
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
            $offerId = (int) $item->commercial_offer_id;
            $tariffId = (int) $item->tariff_id;
            $key = $offerId . ':' . $tariffId;
            $map[$key] = ($map[$key] ?? 0.0) + (float) $item->quantity;
        }

        return $map;
    }

    private function resolveConnectedRowQuantity($row, array $quantitiesByOfferTariff): int
    {
        $offerId = (int) ($row->commercial_offer_id ?? 0);
        $tariffId = (int) ($row->tariff_id ?? 0);

        if ($offerId > 0 && $tariffId > 0) {
            $key = $offerId . ':' . $tariffId;
            if (array_key_exists($key, $quantitiesByOfferTariff)) {
                return max(0, (int) round((float) $quantitiesByOfferTariff[$key]));
            }
        }

        return 1;
    }

    private function buildSelectedServicesFromConnectedRows(
        ?int $selectedTariffId,
        $rows,
        array $tariffsById,
        array $quantitiesByOfferTariff
    ): array {
        $selectedServices = [];
        $processedCountableKeys = [];
        $includedDefaults = $this->getIncludedServiceDefaultsForTariff($selectedTariffId);

        foreach ($includedDefaults as $serviceKey => $meta) {
            $canIncrease = (bool) data_get($meta, 'can_increase', false);
            if ($canIncrease) {
                $includedChannels = max(0, (int) data_get($meta, 'included_channels', 0));
                if ($includedChannels <= 0) {
                    continue;
                }

                $selectedServices[$serviceKey] = [
                    'enabled' => true,
                    'channels' => $includedChannels,
                ];
                continue;
            }

            $selectedServices[$serviceKey] = [
                'enabled' => true,
                'channels' => 1,
            ];
        }

        foreach ($rows as $row) {
            $tariff = $tariffsById[(int) $row->tariff_id] ?? null;
            if (!$tariff) {
                continue;
            }

            if ((bool) $tariff->is_tariff || (bool) $tariff->is_extra_user) {
                continue;
            }

            $serviceKey = 'service-' . (int) $tariff->id;
            $quantity = $this->resolveConnectedRowQuantity($row, $quantitiesByOfferTariff);
            if ($quantity <= 0) {
                $quantity = 1;
            }

            $hasChannels = (bool) $tariff->can_increase;
            if ($hasChannels) {
                $countableKey = $this->buildConnectedCountableKey($row);
                if (isset($processedCountableKeys[$countableKey])) {
                    continue;
                }
                $processedCountableKeys[$countableKey] = true;

                $currentChannels = (int) data_get($selectedServices, $serviceKey . '.channels', 0);
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
            $tariff = $tariffsById[(int) $row->tariff_id] ?? null;
            if (!$tariff || !(bool) $tariff->is_extra_user) {
                continue;
            }

            $countableKey = $this->buildConnectedCountableKey($row);
            if (isset($processedCountableKeys[$countableKey])) {
                continue;
            }
            $processedCountableKeys[$countableKey] = true;

            $extraUsers += $this->resolveConnectedRowQuantity($row, $quantitiesByOfferTariff);
        }

        return max(0, (int) $extraUsers);
    }

    private function buildConnectedCountableKey($row): string
    {
        $offerId = (int) ($row->commercial_offer_id ?? 0);
        $tariffId = (int) ($row->tariff_id ?? 0);
        if ($offerId > 0 && $tariffId > 0) {
            return $offerId . ':' . $tariffId;
        }

        return 'row:' . (int) ($row->id ?? 0);
    }

    private function getAsOfTs(Request $request): int
    {
        $raw = trim((string)$request->query('date', ''));
        $parsed = $this->parseDateToTs($raw);

        return $parsed ?? strtotime(date('Y-m-d'));
    }

    private function parseDateToTs(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts !== false) {
            return $ts;
        }

        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTime::createFromFormat($format, $raw);
            if ($dt instanceof \DateTime) {
                return $dt->getTimestamp();
            }
        }

        return null;
    }

    private function buildOrganizationsForCurrentUser(Request $request): array
    {
        $search = trim((string)$request->query('order_number', $request->query('search', '')));
        $organizationId = (int)$request->query('organization_id', 0);

        $organizations = Organization::query()
            ->select('id', 'name', 'phone', 'client_id', 'order_number')
            ->with(['client.country.currency'])
                        ->when($organizationId > 0, function (Builder $query) use ($organizationId) {
                $query->whereKey($organizationId);
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function (Builder $q) use ($like) {
                    $q->where('order_number', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        $operationStartDates = $this->getOrganizationOperationStartDates(
            $organizations->pluck('id')->map(static fn($id): int => (int)$id)->all()
        );

        return $organizations
            ->map(static function ($o) use ($operationStartDates): array {
                $organizationId = (int)$o->id;

                return [
                    'id' => $organizationId,
                    'name' => (string)$o->name,
                    'email' => '',
                    'phone' => (string)($o->phone ?? ''),
                    'order_number' => (string)($o->order_number ?? ''),
                    'country_id' => data_get($o, 'client.country_id'),
                    'currency_id' => data_get($o, 'client.country.currency_id'),
                    'currency' => data_get($o, 'client.country.currency.symbol_code'),
                    'operation_start_date' => $operationStartDates[$organizationId] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function applyCurrentUserOrganizationScope(Builder $query): void
    {
        $user = Auth::user();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        $userId = (int)$user->id;
        $role = mb_strtolower(trim((string)($user->role ?? '')));

        if ($role === 'partner') {
            $query->where(function (Builder $orgScope) use ($userId) {
                $orgScope
                    ->whereHas('client', function (Builder $clientQuery) use ($userId) {
                        $clientQuery->where('partner_id', $userId);
                    })
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->selectRaw('1')
                            ->from('commercial_offers')
                            ->whereColumn('commercial_offers.organization_id', 'organizations.id')
                            ->where('commercial_offers.partner_id', $userId);
                    })
                    ->orWhereExists(function ($subQuery) use ($userId) {
                        $subQuery->selectRaw('1')
                            ->from('connected_client_services')
                            ->whereColumn('connected_client_services.client_id', 'organizations.id')
                            ->where('connected_client_services.partner_id', $userId);
                    });
            });

            return;
        }

        if ($role === 'manager') {
            $query->whereHas('client', function (Builder $clientQuery) use ($userId) {
                $clientQuery->where('manager_id', $userId);
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function getOrganizationOperationStartDates(array $organizationIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $organizationIds), static fn(int $id): bool => $id > 0));
        if (empty($ids)) {
            return [];
        }

        $rows = ConnectedClientServices::query()
            ->whereIn('client_id', $ids)
            ->where('status', true)
            ->select('client_id', DB::raw('MIN(`date`) as operation_start_at'))
            ->groupBy('client_id')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $organizationId = (int)($row->client_id ?? 0);
            if ($organizationId <= 0) {
                continue;
            }

            $value = trim((string)($row->operation_start_at ?? ''));
            if ($value === '') {
                continue;
            }

            $ts = strtotime($value);
            if ($ts === false) {
                continue;
            }

            $result[$organizationId] = date('Y-m-d', $ts);
        }

        return $result;
    }

    private function applyCurrentUserClientScope(Builder $query): void
    {
        $user = Auth::user();
        if (!$user) {
            $query->whereRaw('1 = 0');
            return;
        }

        $userId = (int)$user->id;
        $role = mb_strtolower(trim((string)($user->role ?? '')));

        if ($role === 'partner') {
            $query->where('partner_id', $userId);
            return;
        }

        if ($role === 'manager') {
            $query->where('manager_id', $userId);
            return;
        }

        // For this endpoint we expose only data strictly tied to current user.
        $query->whereRaw('1 = 0');
    }

    private function buildCurrentPartnerRows(Request $request, int $asOfTs): array
    {
        $user = Auth::user();
        if (!$user) {
            return [];
        }

        $search = mb_strtolower(trim((string)$request->query('search', '')));
        $row = [
            'id' => (int)$user->id,
            'name' => (string)($user->name ?? ''),
            'email' => (string)($user->email ?? ''),
            'phone' => (string)($user->phone ?? ''),
        ];

        if ($search !== '') {
            $haystack = mb_strtolower(implode(' ', [
                (string)$row['name'],
                (string)$row['email'],
                (string)$row['phone'],
            ]));
            if (!str_contains($haystack, $search)) {
                return [];
            }
        }

        $percents = $this->resolvePartnerPercents((int)$user->id, $asOfTs);

        return [[
            'id' => (int)$user->id,
            'name' => (string)($user->name ?? ''),
            'email' => (string)($user->email ?? ''),
            'phone' => (string)($user->phone ?? ''),
            'procent_from_tariff' => (int)($percents['tariff'] ?? 0),
            'procent_from_pack' => (int)($percents['pack'] ?? 0),
            'payment_methods' => $this->normalizePaymentMethods($user->payment_methods ?? null),
        ]];
    }

    private function resolvePartnerPercents(int $partnerId, int $asOfTs): array
    {
        if ($partnerId <= 0) {
            return ['tariff' => 0, 'pack' => 0];
        }

        $asOfDate = date('Y-m-d', $asOfTs);
        $row = PartnerProcent::query()
            ->where('partner_id', $partnerId)
            ->where(function (Builder $query) use ($asOfDate) {
                $query->whereNull('date')
                    ->orWhereDate('date', '<=', $asOfDate);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first(['procent_from_tariff', 'procent_from_pack']);

        if (!$row) {
            return ['tariff' => 0, 'pack' => 0];
        }

        $tariff = (int)max(0, min(100, (int)($row->procent_from_tariff ?? 0)));
        $pack = (int)max(0, min(100, (int)($row->procent_from_pack ?? 0)));

        return [
            'tariff' => $tariff,
            'pack' => $pack,
        ];
    }

    private function normalizePaymentMethods(mixed $methods): array
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
            $code = mb_strtolower(trim((string)$method));
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

    private function buildCommercialConfig(int $asOfTs): array
    {
        $template = $this->readConfigTemplate();
        $templateTariffs = is_array(data_get($template, 'tariffs')) ? data_get($template, 'tariffs') : [];

        $currencyRows = Currency::query()
            ->select('id', 'symbol_code', 'name')
            ->orderBy('id')
            ->get();

        $currencies = [];
        $currenciesById = [];
        foreach ($currencyRows as $currency) {
            $code = strtoupper(trim((string)$currency->symbol_code));
            if ($code === '') {
                continue;
            }
            $currencies[$code] = [
                'symbol' => $code === 'USD' ? '$' : $code,
                'name' => (string)$currency->name,
            ];
            $currenciesById[(string)$currency->id] = $code;
        }

        if (empty($currencies)) {
            $currencies = (array)data_get($template, 'currencies', [
                'USD' => ['symbol' => '$', 'name' => 'Доллар'],
                'UZS' => ['symbol' => 'UZS', 'name' => 'Сум'],
                'TJS' => ['symbol' => 'TJS', 'name' => 'Сомони'],
            ]);
        }

        if (empty($currenciesById)) {
            $currenciesById = [
                '1' => 'USD',
                '2' => 'UZS',
                '3' => 'TJS',
            ];
        }

        $currencyCodes = array_keys($currencies);
        $templateUsdRates = (array)data_get($template, 'usdRates', [
            'USD' => 1,
            'UZS' => 1,
            'TJS' => 1,
        ]);
        $dbUsdRates = $this->buildUsdRatesFromCurrencyRows($currencyRows, $asOfTs);
        $usdRates = $this->normalizeUsdRates(
            array_replace($templateUsdRates, $dbUsdRates),
            $currencyCodes
        );

        $tariffs = Tariff::query()
            ->where('is_tariff', true)
            ->where(function (Builder $query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with([
                'prices.currency:id,symbol_code',
                'includedServices:id,name,is_tariff,is_extra_user,can_increase',
            ])
            ->orderBy('id')
            ->get();

        $tariffCurrencyRows = TariffCurrency::query()
            ->whereIn('tariff_id', $tariffs->pluck('id')->all())
            ->get(['tariff_id', 'currency_id', 'tariff_price']);

        $tariffCurrencyMap = [];
        foreach ($tariffCurrencyRows as $row) {
            $tariffId = (int)$row->tariff_id;
            $currencyCode = (string)($currenciesById[(string)$row->currency_id] ?? '');
            $price = (float)$row->tariff_price;
            if ($tariffId <= 0 || $currencyCode === '' || $price <= 0) {
                continue;
            }
            $tariffCurrencyMap[$tariffId][$currencyCode] = round($price, 4);
        }

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency:id,symbol_code'])
            ->get()
            ->groupBy('parent_tariff_id');

        $tariffsForJs = [];
        foreach ($tariffs as $tariff) {
            $endTs = $this->parseDateToTs($tariff->end_date);
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $templateTariff = $this->findTemplateTariffData($templateTariffs, (string)$tariff->name);

            $pricesFromRows = $this->pickActivePrices(
                $this->filterBasePriceRows($tariff->prices),
                $asOfTs
            );
            $prices = array_replace(
                (array)($tariffCurrencyMap[(int)$tariff->id] ?? []),
                (array)data_get($templateTariff, 'prices', []),
                $pricesFromRows
            );
            $prices = $this->normalizeCurrencyPrices(
                $prices,
                $currencyCodes,
                $usdRates,
                $tariff->price !== null ? round((float)$tariff->price, 4) : null
            );
            if (!$this->hasAnyPositivePrice($prices)) {
                continue;
            }

            $extraServices = $extraUserServicesByTariffId->get((int)$tariff->id);
            $extraUserTariffId = ($extraServices && $extraServices->isNotEmpty())
                ? (int)$extraServices->first()->id
                : null;

            $extraUserPrices = [];
            if ($extraServices) {
                foreach ($extraServices as $extraService) {
                    $candidate = $this->pickActivePrices(
                        $this->filterBasePriceRows($extraService->prices),
                        $asOfTs
                    );
                    foreach ($candidate as $currencyCode => $value) {
                        $extraUserPrices[$currencyCode] = (float)$value;
                    }
                }
            }

            if (empty($extraUserPrices)) {
                $extraUserPrices = $this->pickActivePrices(
                    $this->filterExtraUserPriceRows($tariff->prices),
                    $asOfTs
                );
            }

            $extraUserPrices = array_replace(
                (array)data_get($templateTariff, 'extraUserPrice', []),
                $extraUserPrices
            );
            $extraUserPrices = $this->normalizeCurrencyPrices(
                $extraUserPrices,
                $currencyCodes,
                $usdRates
            );

            $includedServices = [];
            $includedQuantities = [];
            foreach ($tariff->includedServices as $includedService) {
                $serviceKey = 'service-' . (int)$includedService->id;
                $includedServices[] = $serviceKey;
                $includedQuantities[$serviceKey] = max(1, (int)($includedService->pivot?->quantity ?? 1));
            }

            $tariffsForJs['tariff-' . (int)$tariff->id] = [
                'id' => (int)$tariff->id,
                'name' => (string)$tariff->name,
                'users' => (int)($tariff->user_count ?? 0),
                'extraUserTariffId' => $extraUserTariffId,
                'prices' => $prices,
                'prices12Months' => array_map(static fn(float $v) => round($v * 0.85, 4), $prices),
                'extraUserPrice' => $extraUserPrices,
                'extraUserPrices' => $extraUserPrices,
                'includedServices' => $includedServices,
                'includedServiceQuantities' => $includedQuantities,
                'features' => (array)data_get($templateTariff, 'features', []),
                'tariffFeatures' => (array)data_get($templateTariff, 'tariffFeatures', []),
            ];
        }

        $services = Tariff::query()
            ->where('is_tariff', false)
            ->where(function (Builder $query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency:id,symbol_code'])
            ->orderBy('id')
            ->get();

        $servicesForJs = [];
        foreach ($services as $service) {
            $endTs = $this->parseDateToTs($service->end_date);
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $prices = $this->pickActivePrices(
                $this->filterBasePriceRows($service->prices),
                $asOfTs
            );
            $prices = $this->normalizeCurrencyPrices(
                $prices,
                $currencyCodes,
                $usdRates,
                $service->price !== null ? round((float)$service->price, 4) : null
            );
            if (!$this->hasAnyPositivePrice($prices)) {
                continue;
            }

            $servicesForJs['service-' . (int)$service->id] = [
                'id' => (int)$service->id,
                'name' => (string)$service->name,
                'description' => '',
                'type' => 'monthly',
                'prices' => $prices,
                'hasChannels' => (bool)$service->can_increase,
                'isAvailableOnDate' => true,
                'excludedOrganizationIds' => [],
            ];
        }

        return [
            'currencies' => $currencies,
            'currenciesById' => $currenciesById,
            'usdRates' => $usdRates,
            'paymentPeriods' => (array)data_get($template, 'paymentPeriods', [
                ['months' => 6, 'discount' => 0, 'label' => '6 месяцев'],
                ['months' => 12, 'discount' => 15, 'label' => '12 месяцев (скидка 15%)'],
            ]),
            'tariffs' => $tariffsForJs,
            'services' => $servicesForJs,
            'company' => (array)data_get($template, 'company', [
                'name' => 'SHAMCRM',
                'phone' => '+998785557416',
                'email' => 'info@shamcrm.com',
                'website' => 'shamcrm.com',
            ]),
        ];
    }

    private function buildClientPricesForOrganizations(int $asOfTs, array $organizationIds): array
    {
        $allowedOrgIds = array_values(array_filter(array_map('intval', $organizationIds), static fn(int $id): bool => $id > 0));
        if (empty($allowedOrgIds)) {
            return [];
        }
        $allowed = array_fill_keys($allowedOrgIds, true);

        $tariffs = Tariff::query()
            ->where('is_tariff', true)
            ->where(function (Builder $query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency:id,symbol_code'])
            ->get();

        $services = Tariff::query()
            ->where('is_tariff', false)
            ->where(function (Builder $query) {
                $query->whereNull('is_extra_user')->orWhere('is_extra_user', false);
            })
            ->with(['prices.currency:id,symbol_code'])
            ->get();

        $extraUserServicesByTariffId = Tariff::query()
            ->where('is_extra_user', true)
            ->with(['prices.currency:id,symbol_code'])
            ->get()
            ->groupBy('parent_tariff_id');

        $result = [];

        foreach ($tariffs as $tariff) {
            $tariffKey = 'tariff-' . (int)$tariff->id;

            $extraServices = $extraUserServicesByTariffId->get((int)$tariff->id);
            if ($extraServices) {
                foreach ($extraServices as $extraService) {
                    $rows = $this->filterBasePriceRows($extraService->prices, false)->whereNotNull('organization_id');
                    foreach ($rows as $priceRow) {
                        $orgId = (int)data_get($priceRow, 'organization_id', 0);
                        if ($orgId <= 0 || !isset($allowed[$orgId])) {
                            continue;
                        }

                        $symbol = strtoupper(trim((string)data_get($priceRow, 'currency.symbol_code', '')));
                        if ($symbol === '') {
                            continue;
                        }

                        $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
                        $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
                        if ($startTs !== null && $startTs > $asOfTs) {
                            continue;
                        }
                        if ($endTs !== null && $endTs < $asOfTs) {
                            continue;
                        }

                        $sum = round((float)data_get($priceRow, 'sum', 0), 4);
                        if ($sum <= 0) {
                            continue;
                        }

                        $this->assignClientPrice(
                            $result,
                            $orgId,
                            'extra_users',
                            $tariffKey,
                            $symbol,
                            $sum,
                            $startTs ?? 0,
                            $endTs ?? PHP_INT_MAX,
                            false
                        );
                    }
                }
            }

            $tariffRows = $this->filterBasePriceRows($tariff->prices, false)->whereNotNull('organization_id');
            foreach ($tariffRows as $priceRow) {
                $orgId = (int)data_get($priceRow, 'organization_id', 0);
                if ($orgId <= 0 || !isset($allowed[$orgId])) {
                    continue;
                }

                $symbol = strtoupper(trim((string)data_get($priceRow, 'currency.symbol_code', '')));
                if ($symbol === '') {
                    continue;
                }

                $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
                $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
                if ($startTs !== null && $startTs > $asOfTs) {
                    continue;
                }
                if ($endTs !== null && $endTs < $asOfTs) {
                    continue;
                }

                $sum = round((float)data_get($priceRow, 'sum', 0), 4);
                if ($sum <= 0) {
                    continue;
                }

                $this->assignClientPrice(
                    $result,
                    $orgId,
                    'tariffs',
                    $tariffKey,
                    $symbol,
                    $sum,
                    $startTs ?? 0,
                    $endTs ?? PHP_INT_MAX,
                    false
                );
            }

            $extraRows = $this->filterExtraUserPriceRows($tariff->prices, false)->whereNotNull('organization_id');
            foreach ($extraRows as $priceRow) {
                $orgId = (int)data_get($priceRow, 'organization_id', 0);
                if ($orgId <= 0 || !isset($allowed[$orgId])) {
                    continue;
                }

                $symbol = strtoupper(trim((string)data_get($priceRow, 'currency.symbol_code', '')));
                if ($symbol === '') {
                    continue;
                }

                $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
                $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
                if ($startTs !== null && $startTs > $asOfTs) {
                    continue;
                }
                if ($endTs !== null && $endTs < $asOfTs) {
                    continue;
                }

                $sum = round((float)data_get($priceRow, 'sum', 0), 4);
                if ($sum <= 0) {
                    continue;
                }

                $this->assignClientPrice(
                    $result,
                    $orgId,
                    'extra_users',
                    $tariffKey,
                    $symbol,
                    $sum,
                    $startTs ?? 0,
                    $endTs ?? PHP_INT_MAX,
                    true
                );
            }
        }

        foreach ($services as $service) {
            $serviceKey = 'service-' . (int)$service->id;
            $serviceRows = $this->filterBasePriceRows($service->prices, false)->whereNotNull('organization_id');

            foreach ($serviceRows as $priceRow) {
                $orgId = (int)data_get($priceRow, 'organization_id', 0);
                if ($orgId <= 0 || !isset($allowed[$orgId])) {
                    continue;
                }

                $symbol = strtoupper(trim((string)data_get($priceRow, 'currency.symbol_code', '')));
                if ($symbol === '') {
                    continue;
                }

                $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
                $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
                if ($startTs !== null && $startTs > $asOfTs) {
                    continue;
                }
                if ($endTs !== null && $endTs < $asOfTs) {
                    continue;
                }

                $sum = round((float)data_get($priceRow, 'sum', 0), 4);
                if ($sum <= 0) {
                    continue;
                }

                $this->assignClientPrice(
                    $result,
                    $orgId,
                    'services',
                    $serviceKey,
                    $symbol,
                    $sum,
                    $startTs ?? 0,
                    $endTs ?? PHP_INT_MAX,
                    false
                );
            }
        }

        foreach ($result as $orgId => $groups) {
            foreach (['tariffs', 'services', 'extra_users'] as $groupName) {
                if (empty($result[$orgId][$groupName])) {
                    continue;
                }

                foreach ($result[$orgId][$groupName] as $itemKey => $prices) {
                    unset($result[$orgId][$groupName][$itemKey]['__meta']);
                }
            }
        }

        return $result;
    }

    private function applyOrganizationOverridesToConfig(array $config, array $organizationPrices): array
    {
        $tariffOverrides = (array)($organizationPrices['tariffs'] ?? []);
        foreach ($tariffOverrides as $tariffKey => $prices) {
            if (!isset($config['tariffs'][$tariffKey]) || !is_array($prices)) {
                continue;
            }

            foreach ($prices as $currencyCode => $sum) {
                if ($currencyCode === '__meta') {
                    continue;
                }
                $config['tariffs'][$tariffKey]['prices'][(string)$currencyCode] = (float)$sum;
                $config['tariffs'][$tariffKey]['prices12Months'][(string)$currencyCode] = round((float)$sum * 0.85, 4);
            }
        }

        $serviceOverrides = (array)($organizationPrices['services'] ?? []);
        foreach ($serviceOverrides as $serviceKey => $prices) {
            if (!isset($config['services'][$serviceKey]) || !is_array($prices)) {
                continue;
            }

            foreach ($prices as $currencyCode => $sum) {
                if ($currencyCode === '__meta') {
                    continue;
                }
                $config['services'][$serviceKey]['prices'][(string)$currencyCode] = (float)$sum;
            }
        }

        $extraUserOverrides = (array)($organizationPrices['extra_users'] ?? []);
        foreach ($extraUserOverrides as $tariffKey => $prices) {
            if (!isset($config['tariffs'][$tariffKey]) || !is_array($prices)) {
                continue;
            }

            foreach ($prices as $currencyCode => $sum) {
                if ($currencyCode === '__meta') {
                    continue;
                }
                $config['tariffs'][$tariffKey]['extraUserPrices'][(string)$currencyCode] = (float)$sum;
                $config['tariffs'][$tariffKey]['extraUserPrice'][(string)$currencyCode] = (float)$sum;
            }
        }

        return $config;
    }

    private function assignClientPrice(
        array &$result,
        int $organizationId,
        string $groupName,
        string $itemKey,
        string $currencyCode,
        float $sum,
        int $startScore,
        int $endScore,
        bool $skipIfAlreadySet
    ): void {
        if ($skipIfAlreadySet && isset($result[$organizationId][$groupName][$itemKey][$currencyCode])) {
            return;
        }

        $prevStart = $result[$organizationId][$groupName][$itemKey]['__meta'][$currencyCode]['score'] ?? null;
        $prevEnd = $result[$organizationId][$groupName][$itemKey]['__meta'][$currencyCode]['end'] ?? null;

        if (
            $prevStart === null
            || $startScore > $prevStart
            || ($startScore === $prevStart && $endScore >= ((int)$prevEnd))
        ) {
            $result[$organizationId][$groupName][$itemKey][$currencyCode] = $sum;
            $result[$organizationId][$groupName][$itemKey]['__meta'][$currencyCode] = [
                'score' => $startScore,
                'end' => $endScore,
            ];
        }
    }

    private function pickActivePrices(iterable $priceRows, int $asOfTs): array
    {
        $bestByCurrency = [];

        foreach ($priceRows as $priceRow) {
            $currencyCode = strtoupper(trim((string)data_get($priceRow, 'currency.symbol_code', '')));
            if ($currencyCode === '') {
                continue;
            }

            $startTs = $this->parseDateToTs(data_get($priceRow, 'start_date'));
            $endTs = $this->parseDateToTs(data_get($priceRow, 'date'));
            if ($startTs !== null && $startTs > $asOfTs) {
                continue;
            }
            if ($endTs !== null && $endTs < $asOfTs) {
                continue;
            }

            $startScore = $startTs ?? 0;
            $endScore = $endTs ?? PHP_INT_MAX;
            $sum = round((float)data_get($priceRow, 'sum', 0), 4);
            if ($sum <= 0) {
                continue;
            }

            $prev = $bestByCurrency[$currencyCode] ?? null;
            if (
                !$prev
                || $startScore > $prev['start']
                || ($startScore === $prev['start'] && $endScore >= $prev['end'])
            ) {
                $bestByCurrency[$currencyCode] = [
                    'start' => $startScore,
                    'end' => $endScore,
                    'sum' => $sum,
                ];
            }
        }

        $prices = [];
        foreach ($bestByCurrency as $currencyCode => $row) {
            $prices[$currencyCode] = (float)$row['sum'];
        }

        return $prices;
    }

    private function buildUsdRatesFromCurrencyRows(iterable $currencyRows, int $asOfTs): array
    {
        $rows = collect($currencyRows)
            ->map(static function ($currency): ?array {
                $symbol = strtoupper(trim((string)data_get($currency, 'symbol_code', '')));
                $id = (int)data_get($currency, 'id', 0);
                if ($symbol === '' || $id <= 0) {
                    return null;
                }

                return [
                    'id' => $id,
                    'symbol' => $symbol,
                ];
            })
            ->filter()
            ->values();

        if ($rows->isEmpty()) {
            return ['USD' => 1.0];
        }

        $usd = $rows->first(static fn(array $row): bool => $row['symbol'] === 'USD');
        if (!$usd) {
            return ['USD' => 1.0];
        }

        $quotes = $rows->filter(static fn(array $row): bool => $row['symbol'] !== 'USD')->values();
        $rates = ['USD' => 1.0];
        if ($quotes->isEmpty()) {
            return $rates;
        }

        $quoteIdToSymbol = [];
        foreach ($quotes as $row) {
            $quoteIdToSymbol[(int)$row['id']] = (string)$row['symbol'];
        }
        $quoteIds = array_keys($quoteIdToSymbol);
        if (empty($quoteIds)) {
            return $rates;
        }

        $asOfDate = date('Y-m-d', $asOfTs);

        $datedRows = CurrencyRate::query()
            ->where('base_currency_id', (int)$usd['id'])
            ->whereIn('quote_currency_id', $quoteIds)
            ->where(function (Builder $query) use ($asOfDate) {
                $query->whereNull('rate_date')
                    ->orWhereDate('rate_date', '<=', $asOfDate);
            })
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->get(['quote_currency_id', 'rate']);

        $usedQuoteIds = [];
        foreach ($datedRows as $row) {
            $quoteId = (int)$row->quote_currency_id;
            if ($quoteId <= 0 || isset($usedQuoteIds[$quoteId])) {
                continue;
            }

            $rate = (float)$row->rate;
            if ($rate <= 0) {
                continue;
            }

            $symbol = $quoteIdToSymbol[$quoteId] ?? null;
            if (!$symbol) {
                continue;
            }

            $rates[$symbol] = round($rate, 6);
            $usedQuoteIds[$quoteId] = true;
        }

        $missingQuoteIds = array_values(array_filter($quoteIds, static fn(int $id): bool => !isset($usedQuoteIds[$id])));
        if (!empty($missingQuoteIds)) {
            $latestRows = CurrencyRate::query()
                ->where('base_currency_id', (int)$usd['id'])
                ->whereIn('quote_currency_id', $missingQuoteIds)
                ->orderByDesc('rate_date')
                ->orderByDesc('id')
                ->get(['quote_currency_id', 'rate']);

            foreach ($latestRows as $row) {
                $quoteId = (int)$row->quote_currency_id;
                if ($quoteId <= 0 || isset($usedQuoteIds[$quoteId])) {
                    continue;
                }

                $rate = (float)$row->rate;
                if ($rate <= 0) {
                    continue;
                }

                $symbol = $quoteIdToSymbol[$quoteId] ?? null;
                if (!$symbol) {
                    continue;
                }

                $rates[$symbol] = round($rate, 6);
                $usedQuoteIds[$quoteId] = true;
            }
        }

        $stillMissingQuoteIds = array_values(array_filter($quoteIds, static fn(int $id): bool => !isset($usedQuoteIds[$id])));
        if (!empty($stillMissingQuoteIds)) {
            $fallbackRows = ExchangeRate::query()
                ->whereIn('currency_id', $stillMissingQuoteIds)
                ->orderByDesc('id')
                ->get(['currency_id', 'kurs']);

            foreach ($fallbackRows as $row) {
                $quoteId = (int)$row->currency_id;
                if ($quoteId <= 0 || isset($usedQuoteIds[$quoteId])) {
                    continue;
                }

                $rate = (float)$row->kurs;
                if ($rate <= 0) {
                    continue;
                }

                $symbol = $quoteIdToSymbol[$quoteId] ?? null;
                if (!$symbol) {
                    continue;
                }

                $rates[$symbol] = round($rate, 6);
                $usedQuoteIds[$quoteId] = true;
            }
        }

        return $rates;
    }

    private function normalizeUsdRates(array $rates, array $currencyCodes): array
    {
        $normalized = ['USD' => 1.0];

        foreach ($rates as $currencyCode => $value) {
            $code = strtoupper(trim((string)$currencyCode));
            if ($code === '') {
                continue;
            }

            $rate = (float)str_replace(',', '.', (string)$value);
            if ($rate <= 0) {
                continue;
            }

            $normalized[$code] = round($rate, 6);
        }

        foreach ($currencyCodes as $currencyCode) {
            $code = strtoupper(trim((string)$currencyCode));
            if ($code === '') {
                continue;
            }

            if ($code === 'USD') {
                $normalized[$code] = 1.0;
            }
        }

        return $normalized;
    }

    private function normalizeCurrencyPrices(
        array $prices,
        array $currencyCodes,
        array $usdRates,
        ?float $fallbackPrice = null
    ): array {
        $normalized = [];

        foreach ($prices as $currencyCode => $rawValue) {
            $code = strtoupper(trim((string)$currencyCode));
            if ($code === '' || $code === '__META') {
                continue;
            }

            $value = $this->toDecimal($rawValue);
            if ($value <= 0) {
                continue;
            }

            $normalized[$code] = $value;
        }

        $fallback = $fallbackPrice !== null ? $this->toDecimal($fallbackPrice) : null;
        $fallback = ($fallback !== null && $fallback > 0) ? $fallback : null;

        $usd = $normalized['USD'] ?? null;
        if ($usd === null) {
            foreach ($normalized as $code => $value) {
                $rate = (float)($usdRates[$code] ?? 0);
                if ($rate > 0) {
                    $usd = round($value / $rate, 4);
                    break;
                }
            }
        }
        if ($usd === null && $fallback !== null) {
            $usd = $fallback;
        }

        $firstKnownPrice = null;
        foreach ($normalized as $value) {
            if ($value > 0) {
                $firstKnownPrice = $value;
                break;
            }
        }
        if ($firstKnownPrice === null && $fallback !== null) {
            $firstKnownPrice = $fallback;
        }

        foreach ($currencyCodes as $currencyCode) {
            $code = strtoupper(trim((string)$currencyCode));
            if ($code === '') {
                continue;
            }

            if (isset($normalized[$code]) && $normalized[$code] > 0) {
                continue;
            }

            $derived = null;
            if ($code === 'USD' && $usd !== null && $usd > 0) {
                $derived = $usd;
            } elseif ($usd !== null && $usd > 0) {
                $rate = (float)($usdRates[$code] ?? 0);
                if ($rate > 0) {
                    $derived = $usd * $rate;
                }
            }

            if ($derived === null && $fallback !== null) {
                if ($code === 'USD') {
                    $derived = $fallback;
                } else {
                    $rate = (float)($usdRates[$code] ?? 0);
                    $derived = $rate > 0 ? $fallback * $rate : $fallback;
                }
            }

            if ($derived === null && $firstKnownPrice !== null) {
                $derived = $firstKnownPrice;
            }

            $normalized[$code] = $derived !== null ? round((float)$derived, 4) : 0.0;
        }

        return $normalized;
    }

    private function hasAnyPositivePrice(array $prices): bool
    {
        foreach ($prices as $currencyCode => $rawValue) {
            if ((string)$currencyCode === '__META') {
                continue;
            }

            $value = $this->toDecimal($rawValue);
            if ($value > 0) {
                return true;
            }
        }

        return false;
    }

    private function filterBasePriceRows(iterable $priceRows, bool $globalOnly = true): iterable
    {
        if ($priceRows instanceof \Illuminate\Support\Collection) {
            $rows = $priceRows
                ->filter(static function ($row) {
                    $kind = mb_strtolower(trim((string)data_get($row, 'kind', '')));

                    return $kind === '' || $kind === 'base';
                })
                ->values();

            return $globalOnly ? $rows->whereNull('organization_id')->values() : $rows;
        }

        return $priceRows;
    }

    private function filterExtraUserPriceRows(iterable $priceRows, bool $globalOnly = true): iterable
    {
        if ($priceRows instanceof \Illuminate\Support\Collection) {
            $rows = $priceRows
                ->filter(static function ($row) {
                    $kind = mb_strtolower(trim((string)data_get($row, 'kind', '')));

                    return $kind === 'extra_user';
                })
                ->values();

            return $globalOnly ? $rows->whereNull('organization_id')->values() : $rows;
        }

        return $priceRows;
    }

    private function readConfigTemplate(): array
    {
        $path = public_path('kp_generator/data/config.json');
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function findTemplateTariffData(array $templateTariffs, string $tariffName): array
    {
        if (empty($templateTariffs)) {
            return [];
        }

        $byKey = [];
        foreach ($templateTariffs as $key => $row) {
            if (is_array($row)) {
                $byKey[(string)$key] = $row;
            }
        }

        $detectedKey = $this->detectTemplateTariffKey($tariffName);
        if ($detectedKey !== null && isset($byKey[$detectedKey])) {
            return $byKey[$detectedKey];
        }

        $target = $this->normalizeLookup((string)$tariffName);
        foreach ($byKey as $row) {
            $name = $this->normalizeLookup((string)data_get($row, 'name', ''));
            if ($name !== '' && $name === $target) {
                return $row;
            }
        }

        return [];
    }

    private function detectTemplateTariffKey(string $name): ?string
    {
        $normalized = $this->normalizeLookup($name);
        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, 'base') || str_contains($normalized, 'баз')) {
            return 'basic';
        }
        if (str_contains($normalized, 'stand') || str_contains($normalized, 'standart') || str_contains($normalized, 'станд')) {
            return 'standard';
        }
        if (str_contains($normalized, 'prem') || str_contains($normalized, 'прем')) {
            return 'premium';
        }
        if (str_contains($normalized, 'vip')) {
            return 'vip';
        }

        return null;
    }

    private function normalizeLookup(string $value): string
    {
        $raw = mb_strtolower(trim($value));
        $raw = str_replace(['ё'], ['е'], $raw);

        return preg_replace('/[^a-z0-9а-я]+/u', '', $raw) ?: '';
    }

    private function ownedOffersQuery(): Builder
    {
        $userId = (int)Auth::id();

        return CommercialOffer::query()
            ->where(function (Builder $query) use ($userId) {
                $query->where('created_by', $userId)
                    ->orWhere('partner_id', $userId);
            });
    }

    private function normalizeRequestType(?string $requestType): string
    {
        $normalized = trim((string)$requestType);

        return in_array($normalized, self::REQUEST_TYPES, true)
            ? $normalized
            : 'connection';
    }

    private function buildOfferPayload(CommercialOffer $offer): array
    {
        $selectedTariffId = $this->resolveSelectedTariffIdFromItems($offer);
        $organization = $this->buildOrganizationSnapshot($offer->organization);

        return [
            'offer_id' => $offer->id,
            'request_type' => (string)($offer->request_type ?: 'connection'),
            'organization_id' => $offer->organization_id,
            'organization' => $organization,
            'partner_id' => $offer->partner_id,
            'selected_tariff_key' => $selectedTariffId ? ('tariff-' . (int)$selectedTariffId) : null,
            'selected_tariff_id' => $selectedTariffId,
            'period_months' => (int)($offer->period_months ?: 6),
            'extra_users' => $this->resolveExtraUsersFromOffer($offer),
            'status_date' => optional($offer->status_date)->format('Y-m-d'),
            'pricing_date' => optional($offer->pricing_date)->format('Y-m-d'),
            'currency' => (string)($offer->currency ?: 'USD'),
            'payable_currency' => (string)($offer->payable_currency ?: $offer->currency ?: 'USD'),
            'card_payment_type' => (string)($offer->card_payment_type ?: 'octo'),
            'conversion_rate' => $offer->conversion_rate !== null ? (float)$offer->conversion_rate : null,
            'manager_name' => (string)($offer->manager_name ?? ''),
            'client_name' => (string)($offer->client_name ?? ''),
            'client_phone' => (string)($offer->client_phone ?? ''),
            'client_email' => (string)($offer->client_email ?? ''),
            'partner_name' => (string)($offer->partner_name ?? ''),
            'partner_phone' => (string)($offer->partner_phone ?? ''),
            'partner_email' => (string)($offer->partner_email ?? ''),
            'payer' => [
                'type' => (string)($offer->payer_type ?: 'client'),
                'id' => $offer->partner_id,
            ],
            'monthly_total' => (float)$offer->monthly_total,
            'period_total' => (float)$offer->period_total,
            'grand_total' => (float)$offer->grand_total,
            'original_total' => (float)$offer->original_total,
            'payable_total' => (float)$offer->payable_total,
            'selected_services' => $this->buildSelectedServicesFromOffer($offer, $selectedTariffId),
            'items' => $offer->items
                ->map(fn($item) => [
                    'tariff_id' => (int)$item->tariff_id,
                    'service_key' => $item->tariff_id ? ('service-' . (int)$item->tariff_id) : null,
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

    private function buildOrganizationSnapshot(?Organization $organization): ?array
    {
        if (!$organization) {
            return null;
        }

        return [
            'id' => (int)$organization->id,
            'name' => (string)($organization->name ?? ''),
            'order_number' => (string)($organization->order_number ?? ''),
            'phone' => (string)($organization->phone ?? ''),
            'email' => (string)($organization->email ?? ''),
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

        return $offer->tariff_id ? (int)$offer->tariff_id : null;
    }

    private function buildSelectedServicesFromOffer(CommercialOffer $offer, ?int $selectedTariffId): array
    {
        $selectedServices = [];
        $includedDefaults = $this->getIncludedServiceDefaultsForTariff($selectedTariffId);

        foreach ($offer->items as $item) {
            $tariff = $item->tariff;
            if (!$tariff) {
                continue;
            }

            if ((bool)$tariff->is_tariff || (bool)$tariff->is_extra_user) {
                continue;
            }

            $serviceKey = 'service-' . (int)$tariff->id;
            $quantity = max(1, (int)round((float)$item->quantity));

            if ((bool)$tariff->can_increase) {
                $includedChannels = (int)data_get($includedDefaults, $serviceKey . '.included_channels', 0);
                $current = (int)data_get($selectedServices, $serviceKey . '.channels', 0);
                if ($current <= 0 && $includedChannels > 0) {
                    $current = $includedChannels;
                }
                $selectedServices[$serviceKey] = [
                    'enabled' => true,
                    'channels' => $current + $quantity,
                ];
            } else {
                $selectedServices[$serviceKey] = [
                    'enabled' => true,
                    'channels' => 1,
                ];
            }
        }

        foreach ($includedDefaults as $serviceKey => $meta) {
            $canIncrease = (bool)data_get($meta, 'can_increase', false);
            if ($canIncrease) {
                $includedChannels = max(0, (int)data_get($meta, 'included_channels', 0));
                if ($includedChannels <= 0) {
                    continue;
                }

                $currentChannels = (int)data_get($selectedServices, $serviceKey . '.channels', 0);
                if ($currentChannels < $includedChannels) {
                    $selectedServices[$serviceKey] = [
                        'enabled' => true,
                        'channels' => $includedChannels,
                    ];
                }
                continue;
            }

            $selectedServices[$serviceKey] = [
                'enabled' => true,
                'channels' => 1,
            ];
        }

        return $this->normalizeSelectedServices($selectedServices);
    }

    private function getIncludedServiceDefaultsForTariff(?int $selectedTariffId): array
    {
        if (!$selectedTariffId) {
            return [];
        }

        $tariff = Tariff::query()
            ->whereKey($selectedTariffId)
            ->with(['includedServices:id,can_increase'])
            ->first();

        if (!$tariff) {
            return [];
        }

        $result = [];
        foreach ($tariff->includedServices as $service) {
            $serviceKey = 'service-' . (int)$service->id;
            $result[$serviceKey] = [
                'can_increase' => (bool)$service->can_increase,
                'included_channels' => max(0, (int)($service->pivot?->quantity ?? 1)),
            ];
        }

        return $result;
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

    private function resolveExtraUsersFromOffer(CommercialOffer $offer): int
    {
        $sum = 0;

        foreach ($offer->items as $item) {
            $tariff = $item->tariff;
            if (!$tariff || !(bool)$tariff->is_extra_user) {
                continue;
            }

            $sum += max(0, (int)round((float)$item->quantity));
        }

        if ($sum <= 0) {
            $sum = max(0, (int)$offer->extra_users);
        }

        return $sum;
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

        return $code !== '' ? $code : 'USD';
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
