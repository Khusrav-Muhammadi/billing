<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\CommercialOffer;
use App\Models\Organization;
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

    public function index()
    {
        $offers = CommercialOffer::query()
            ->with([
                'tariff:id,name',
                'organization:id,name',
                'partner:id,name',
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

    public function create()
    {
        return view('admin.applications.create', [
            'offer' => null,
        ]);
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

        $payload = json_decode((string) $validated['payload'], true);
        if (!is_array($payload)) {
            throw ValidationException::withMessages([
                'payload' => 'Некорректный формат данных КП.',
            ]);
        }

        $organization = Organization::query()
            ->with('client:id,phone,email')
            ->findOrFail((int) $validated['organization_id']);

        $offer = DB::transaction(function () use ($validated, $payload, $organization) {
            $offerId = isset($validated['offer_id']) ? (int) $validated['offer_id'] : null;

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

            $partnerId = $this->toNullableInt(data_get($payload, 'partner_id'));
            $partner = null;
            if ($partnerId) {
                $partner = User::query()
                    ->where('id', $partnerId)
                    ->whereRaw('LOWER(role) = ?', ['partner'])
                    ->select('id', 'name', 'phone', 'email')
                    ->first();
            }

            $selectedTariffKey = (string) (data_get($payload, 'selected_tariff_key') ?? '');
            $selectedTariffId = $this->toNullableInt(data_get($payload, 'selected_tariff_id'));
            if (!$selectedTariffId) {
                $selectedTariffId = $this->extractTariffIdFromKey($selectedTariffKey);
            }
            $tariff = null;
            if ($selectedTariffId) {
                $tariff = Tariff::query()->select('id', 'name')->find($selectedTariffId);
            }

            $payerType = (string) (data_get($payload, 'payer.type') ?? ($partner ? 'partner' : 'client'));
            if (!in_array($payerType, ['client', 'partner'], true)) {
                $payerType = $partner ? 'partner' : 'client';
            }

            $clientName = (string) ($organization->name ?? data_get($payload, 'client_name', ''));
            $clientPhone = (string) ($organization->phone ?: ($organization->client?->phone ?: data_get($payload, 'client_phone', '')));
            $clientEmail = (string) ($organization->email ?: ($organization->client?->email ?: data_get($payload, 'client_email', '')));

            $partnerName = (string) (($partner?->name) ?: data_get($payload, 'partner_name', ''));
            $partnerPhone = (string) (($partner?->phone) ?: data_get($payload, 'partner_phone', ''));
            $partnerEmail = (string) (($partner?->email) ?: data_get($payload, 'partner_email', ''));

            $allowedPaymentMethods = data_get($payload, 'allowed_payment_methods', []);
            if (!is_array($allowedPaymentMethods)) {
                $allowedPaymentMethods = [];
            }
            $allowedPaymentMethods = array_values(array_unique(array_filter(array_map(
                fn ($value) => strtolower(trim((string) $value)),
                $allowedPaymentMethods
            ))));

            $selectedServices = data_get($payload, 'selected_services', []);
            if (!is_array($selectedServices)) {
                $selectedServices = [];
            }

            $snapshot = $payload;

            $offer->fill([
                'organization_id' => $organization->id,
                'partner_id' => $partner?->id,
                'tariff_id' => $tariff?->id,
                'status' => self::OFFER_STATUS_DRAFT,
                'saved_at' => now(),
                'pricing_date' => $this->toNullableDate(data_get($payload, 'pricing_date')),
                'currency' => $this->toCurrencyCode(data_get($payload, 'currency', 'USD')),
                'payable_currency' => $this->toCurrencyCode(data_get($payload, 'payable_currency', data_get($payload, 'currency', 'USD'))),
                'card_payment_type' => (string) data_get($payload, 'card_payment_type', 'octo'),
                'period_months' => max(1, (int) data_get($payload, 'period_months', 6)),
                'extra_users' => max(0, (int) data_get($payload, 'extra_users', 0)),
                'selected_tariff_key' => $selectedTariffKey !== '' ? $selectedTariffKey : null,
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'client_email' => $clientEmail,
                'partner_name' => $partnerName !== '' ? $partnerName : null,
                'partner_phone' => $partnerPhone !== '' ? $partnerPhone : null,
                'partner_email' => $partnerEmail !== '' ? $partnerEmail : null,
                'payer_type' => $payerType,
                'manager_name' => (string) data_get($payload, 'manager_name', ''),
                'original_total' => $this->toDecimal(data_get($payload, 'original_total', data_get($payload, 'grand_total', 0))),
                'monthly_total' => $this->toDecimal(data_get($payload, 'monthly_total', 0)),
                'period_total' => $this->toDecimal(data_get($payload, 'period_total', data_get($payload, 'grand_total', 0))),
                'grand_total' => $this->toDecimal(data_get($payload, 'grand_total', 0)),
                'payable_total' => $this->toDecimal(data_get($payload, 'payable_total', data_get($payload, 'grand_total', 0))),
                'conversion_rate' => $this->toNullableDecimal(data_get($payload, 'conversion_rate')),
                'selected_services' => $selectedServices,
                'allowed_payment_methods' => $allowedPaymentMethods,
                'snapshot' => $snapshot,
                'updated_by' => Auth::id(),
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

                $name = trim((string) data_get($row, 'name', ''));
                if ($name === '') {
                    continue;
                }

                $quantity = max(1, (float) data_get($row, 'quantity', 1));
                $totalPrice = $this->toDecimal(data_get($row, 'price', data_get($row, 'total_price', 0)));
                if ($totalPrice <= 0) {
                    continue;
                }

                $unitPrice = $this->toDecimal(data_get($row, 'unit_price'));
                if ($unitPrice <= 0) {
                    $unitPrice = $quantity > 0 ? $this->toDecimal($totalPrice / $quantity) : $totalPrice;
                }

                $offer->items()->create([
                    'service_key' => data_get($row, 'service_key'),
                    'service_name' => $name,
                    'billing_type' => (string) data_get($row, 'billing_type', 'period'),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'months' => max(1, (int) data_get($row, 'months', data_get($payload, 'period_months', 1))),
                    'discount_percent' => $this->toDecimal(data_get($row, 'discount_percent', 0)),
                    'partner_percent' => $this->toDecimal(data_get($row, 'partner_percent', 0)),
                    'total_price' => $totalPrice,
                    'meta' => is_array(data_get($row, 'meta')) ? data_get($row, 'meta') : null,
                ]);
            }

            if ($offer->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'payload' => 'В КП нет позиций для сохранения.',
                ]);
            }

            return $offer;
        });

        return redirect()
            ->route('application.show', $offer)
            ->with('success', 'КП успешно сохранено.');
    }

    public function storeCommercialOfferClient(Request $request): RedirectResponse
    {
        return $this->storeCommercialOffer($request);
    }

    public function showCommercialOffer(CommercialOffer $offer)
    {
        $offer->load([
            'items:id,commercial_offer_id,service_name,billing_type,quantity,unit_price,months,total_price',
            'tariff:id,name',
            'organization:id,name,phone,email',
            'partner:id,name,phone,email',
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

        $allowedMethods = $offer->allowed_payment_methods;
        if (!is_array($allowedMethods) || count($allowedMethods) === 0) {
            $allowedMethods = ['card', 'invoice'];
        }

        $cardPaymentType = $offer->card_payment_type ?: ($offer->payable_currency === 'UZS' ? 'alif' : 'octo');

        return view('admin.applications.show', compact('offer', 'allowedMethods', 'cardPaymentType'));
    }

    public function storeOfferStatus(Request $request, CommercialOffer $offer): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,paid,canceled'],
            'status_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:card,invoice,cash'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payment_order_number' => ['nullable', 'string', 'max:100', 'required_if:payment_method,invoice'],
        ]);

        $paymentOrderNumber = isset($validated['payment_order_number'])
            ? trim((string) $validated['payment_order_number'])
            : null;

        if ($validated['payment_method'] !== 'invoice' || $paymentOrderNumber === '') {
            $paymentOrderNumber = null;
        }

        $offer->offerStatuses()->create([
            'status' => $validated['status'],
            'status_date' => $validated['status_date'],
            'payment_method' => $validated['payment_method'],
            'account_id' => (int) $validated['account_id'],
            'payment_order_number' => $paymentOrderNumber,
            'author_id' => Auth::id(),
        ]);

        $offer->update([
            'updated_by' => Auth::id(),
        ]);

        return redirect()
            ->route('application.index')
            ->with('success', 'Статус подключения сохранен.');
    }

    public function edit(int $id)
    {
        $offer = CommercialOffer::query()->findOrFail($id);

        if ($offer->locked_at) {
            return redirect()
                ->route('application.show', $offer)
                ->withErrors(['error' => 'Это КП уже заблокировано после генерации ссылки оплаты.']);
        }

        return view('admin.applications.create', [
            'offer' => $offer,
        ]);
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
        $payload = $offer->snapshot;
        if (!is_array($payload)) {
            $payload = [];
        }

        return response()->json([
            'offer' => [
                'id' => $offer->id,
                'locked' => $offer->locked_at !== null,
                'payload' => $payload,
            ],
        ]);
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function toDecimal($value): float
    {
        $normalized = str_replace(',', '.', (string) $value);
        if (!is_numeric($normalized)) {
            return 0.0;
        }
        return round((float) $normalized, 2);
    }

    private function toNullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $normalized = str_replace(',', '.', (string) $value);
        if (!is_numeric($normalized)) {
            return null;
        }
        return round((float) $normalized, 6);
    }

    private function toCurrencyCode($value): string
    {
        $code = strtoupper(trim((string) $value));
        return $code !== '' ? $code : 'USD';
    }

    private function toNullableDate($value): ?string
    {
        $raw = trim((string) $value);
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
        $raw = trim((string) $key);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^tariff-(\d+)$/', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }
}
