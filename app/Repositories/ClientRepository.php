<?php

namespace App\Repositories;

use App\Jobs\ActivationJob;
use App\Jobs\ChangeRequestStatusJob;
use App\Jobs\SubDomainJob;
use App\Jobs\UpdateTariffJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceStatus;
use App\Models\Organization;
use App\Models\PartnerRequest;
use App\Models\Tariff;
use App\Models\TariffCurrency;
use App\Models\Transaction;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Services\WithdrawalService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use function Pest\Laravel\put;

class ClientRepository implements ClientRepositoryInterface
{
    public function index(array $data): LengthAwarePaginator
    {
        $query = Client::query();

        $query = $query->filter($data);

        $clients = $query
            ->withCount('organizations')
            ->with(['tariff', 'partner', 'organizations.packs.pack' => function ($query) {
                $query->where('type', 'user');
            }])
            ->orderByDesc('created_at')
            ->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount;
                });
            });

            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;

            $client->validate_date = $this->calculateValidateDate($client);
            return $client;
        });
        $clients->setCollection($processedClients);
        return $clients;
    }

    /**
     * Calculate daily payment for a client
     *
     * @param Client $client
     * @return float
     */
        protected function calculateDailyPayment(Client $client): float
        {
            if (!$client->tariff) {
                return 0;
            }

            $currentMonth = now();
            $daysInMonth = $currentMonth->daysInMonth;

            $dailyPayment = $client->tariff->price / $daysInMonth;

            $packsDailyPayment = $client->organizations->sum(function ($organization) use ($daysInMonth) {
                return $organization->packs->sum(function ($organizationPack) use ($daysInMonth) {

                    $pack = $organizationPack->pack()->first();


                    return $pack ? ($pack->price / $daysInMonth) : 0;
                });
            });

            $totalDailyPayment = $dailyPayment + $packsDailyPayment;

            if ($client->sale_id) {
                $sale = $client->sale;

                if ($sale->sale_type === 'procent') {
                    $totalDailyPayment -= ($client->tariff->price * $sale->amount) / (100 * $daysInMonth);
                } else {
                    $totalDailyPayment -= $sale->amount / $daysInMonth;
                }
            }

            return max(0, $totalDailyPayment);
        }

        /**
         * Calculate validation date for a client
         *
         * @param Client $client
         * @return Carbon|null
         */
        protected function calculateValidateDate(Client $client)
        {
            if ($client->is_demo) {
                return Carbon::parse($client->created_at)->addWeeks(2)  ;
            }

            $dailyPayment = round($this->calculateDailyPayment($client), 4);

            $days = (int)($client->balance / $dailyPayment);

            return Carbon::now()->addDays($days);
        }

    public function store(array $data)
    {
        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        if (isset($data['nfr']) && $data['nfr'] == 'on') $data['nfr'] = true;

        $client = Client::create($data);

        SubDomainJob::dispatch($client);

        if (isset($data['partner_request_id']) && $data['partner_request_id'] != null) {
            $partnerRequest = PartnerRequest::where('id', $data['partner_request_id'])->first();
            $partnerRequest->update(['request_status' => 'Успешный']);

            $partner = $partnerRequest->partner()->first();

            ChangeRequestStatusJob::dispatch($partner, $partnerRequest, Auth::user());
        }

        return $client;
    }

    public function update(Client $client, array $data)
    {
        if ($client->tariff_id != $data['tariff_id']) UpdateTariffJob::dispatch($client, $data['tariff_id'], $client->sub_domain);

        if (isset($data['is_demo']) && $data['is_demo'] == 'on') $data['is_demo'] = true;
        else $data['is_demo'] = false;

        $client->update($data);

        if ($data['is_demo'] == false) $this->withdrawal($client);

    }

    public function activation(Client $client, ?array $data)
    {
        $organizationIds = $client->organizations()->pluck('id')->toArray();
        $reject_cause = $data['reject_cause'] ?? '';

        ActivationJob::dispatch($organizationIds, $client->sub_domain, !$client->is_active, true, auth()->id() ?? 1, $reject_cause);
    }

    public function createTransaction(Client $client, array $data)
    {
        DB::transaction(function () use ($data, $client) {
            $organization = Organization::find($data['organization_id']);
            $data['type'] = 'Пополнение';
            $data['client_id'] = $client->id;
            Transaction::create($data);
            $organization->increment('balance', $data['sum']);
        });
    }

    public function getBalance(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        return response()->json([
            'balance' => $client->balance,
            'tariff' => $client->tariff->name
        ]);
    }

    public function withdrawal(Client $client)
    {
        $service = new WithdrawalService();
        $sum = $service->countSum($client);

        $organizations = $client->organizations()
            ->where('has_access', true)->get();

        foreach ($organizations as $organization) {
            $service->handle($organization, $sum);
        }

    }

    public function getByPartner(array $data)
    {
        $query = Client::query()->with(['tariff', 'country', 'partner'])->filter($data);

        if (auth()->user()->role == 'partner') {
            $query->where('partner_id', auth()->id());
        }

        $clients = $query->with(['sale', 'tariff', 'city', 'partner'])->paginate(20);

        $processedClients = $clients->getCollection()->map(function ($client) {
            $totalUsersFromPacks = $client->organizations->sum(function ($organization) {

                return $organization->packs->sum(function ($organizationPack) {
                    return $organizationPack->amount ?? 0;
                });
            });


            $totalUsersFromOrganizations = $client->organizations->sum(function ($organization) {
                return $organization->client->tariff->user_count ?? 0;
            });

            $client->total_users = $totalUsersFromOrganizations + $totalUsersFromPacks;
            $client->validate_date = $this->calculateValidateDate($client);
            return $client;
        });

        $clients->setCollection($processedClients);

        return $clients;
    }

    public function countDifference(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();

        $organization = Organization::find($data['organization_id']);
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $licenseDifference = $newTariff->license_price > $lastTariff->license_price ? ($newTariff->license_price - $lastTariff->license_price) : 0;
        $tariffPrice = $newTariff->tariff_price * $data['month'];

        $difference = $organization->balance - ($licenseDifference + $tariffPrice);

        return [
            'organization_balance' => $organization->balance,
            'license_difference' => $licenseDifference,
            'tariff_price' => $tariffPrice,
            'must_pay' => $difference < 0
        ];
    }

    public function changeTariff(array $data)
    {
        $client = Client::where('sub_domain', $data['sub_domain'])->first();
        $newTariff = TariffCurrency::find($data['tariff_id']);
        $lastTariff = TariffCurrency::find($client->tariff_id);

        $tariffPrice = $newTariff->tariff_price * $data['month'];

        $organizations = $client->organizations;

        $currency = $client->currency;
        $exchangeRate = $currency->latestExchangeRate?->kurs ?? 1;

        if ($lastTariff->license_price < $newTariff->license_price) {
            $difference = $newTariff->license_price - $lastTariff->license_price;

            $amounts = $this->calculateAmounts($difference, $currency, $exchangeRate);

            foreach ($organizations as $organization) {
                $organization->decrement('balance', $difference);
                $transactions = [
                    [
                        'sum' => $difference,
                        'accounted_amount' => $amounts['accounted_amount']
                    ]
                ];
                $this->createTransactions($client, $organization, $transactions);
            }
        }

        $service = new WithdrawalService();
        $tariffSum = $service->countSum($client);
        $amounts = $this->calculateAmounts($tariffSum, $currency, $exchangeRate);

        foreach ($organizations as $organization) {
            $organization->decrement('balance', $tariffPrice);
            $transactions = [
                [
                    'sum' => $tariffSum,
                    'accounted_amount' => $amounts['accounted_amount']
                ]
            ];
            $this->createTransactions($client, $organization, $transactions);
        }
    }

    private function createTransactions(Client $client, Organization $organization, array $transactions): void
    {
        foreach ($transactions as $transaction) {
            if ($transaction['sum'] > 0) {
                Transaction::create([
                    'client_id' => $client->id,
                    'organization_id' => $organization->id,
                    'tariff_id' => $client->tariff?->id,
                    'sale_id' => $client->sale?->id,
                    'sum' => $transaction['sum'],
                    'type' => 'Снятие',
                    'accounted_amount' => $transaction['accounted_amount']
                ]);
            }
        }
    }

    private function calculateAmounts(float $price, $currency, float $exchangeRate): array
    {
        $isUSD = $currency->symbol_code != 'USD';

        return [
            'accounted_amount' => $isUSD ? $price / $exchangeRate : $price,
        ];
    }

    public function createInvoice(Client $client, int $price, int $organizationId)
    {
        $token = config('payments.alif.token');
        $url = config('payments.alif.url');

        $invoiceData = $this->prepareInvoiceData($organizationId, $client);
        $invoice = Invoice::create($invoiceData);

        $invoiceItems = $this->prepareInvoiceItems($price, $invoice->id);
        InvoiceItem::insert($invoiceItems);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Token' => $token,
            'Accept' => 'application/json'
        ])->post($url, array_merge($invoiceData, ['items' => $invoiceItems]));

        if ($response->failed()) {
            Log::error('Alif invoice creation failed', ['response' => $response->body()]);
            throw new \Exception('Ошибка при создании счета в Alif');
        }

        $res = $response->json();

        $invoice->update(['invoice_id' => $res['id']]);

        return config('payments.alif.payment_page') . $res['id'];
    }

    private function prepareInvoiceData(int $organizationId, Client $client): array
    {
        return [
            'receipt' => true,
            'organization_id' => $organizationId,
            'phone' => $client->phone,
            'timeout' => 86400,
            'meta' => (object)[],
            'invoice_status_id' => 1,
            'cancel_url' => "https://shamcrm.com/payment-failed?subdomain={$client->sub_domain}",
            'redirect_url' => "https://{$client->sub_domain}shamcrm.com/payment",
//            'webhook_url' => 'https://' . $client->sub_domain . '-back.shamcrm.com/api/payment/alif/webhook/change-tariff',
            'webhook_url' => 'https://357b-95-142-94-22.ngrok-free.app/api/payment/alif/webhook/change-tariff',
        ];
    }

    private function prepareInvoiceItems($price, int $invoiceId): array
    {
        return [
            [
                'name' => 'Изменение тарифа',
                'spic' => '11201001001000000',
                'amount' => 1,
                'price' => $price,
                'invoice_id' => $invoiceId,
            ],
        ];
    }

    public function webhookChangeTariff(Request $request)
    {
        try {
            $invoice = Invoice::where('invoice_id', $request->id)->first();

            if ($request->payment['status'] !== 'SUCCEEDED') {
                return response()->json(['message' => 'Payment not succeeded'], 200);
            }

            DB::transaction(function () use ($invoice, $request) {
                $this->processSuccessfulPayment($invoice, $request->price);
            });

            return response()->json(['message' => 'Webhook processed successfully'], 200);

        } catch (ValidationException $e) {

            return response()->json(['error' => $e->getMessage()], 422);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function processSuccessfulPayment(Invoice $invoice, float $price)
    {
        $successStatus = InvoiceStatus::where('is_success', true)->first();

        $invoice->update(['invoice_status_id' => $successStatus->id]);

        $organization = Organization::findOrFail($invoice->organization_id);
        $organization->increment('balance', $price);

        $client = Client::with(['currency.latestExchangeRate', 'tariff', 'tariffPrice', 'sale'])
            ->where('phone', $invoice->phone)
            ->firstOrFail();

        $currency = $client->currency;
        $exchangeRate = $currency->latestExchangeRate?->kurs ?? 1;

        $amounts = $this->calculateAmounts($price, $currency, $exchangeRate);

        $this->transaction($client, $organization, $price, $amounts['accounted_amount']);
    }

    private function transaction(Client $client, Organization $organization, float $sum, float $accountedAmount)
    {
        Transaction::create([
            'client_id' => $client->id,
            'organization_id' => $organization->id,
            'tariff_id' => $client->tariff?->id,
            'sale_id' => $client->sale?->id,
            'sum' => $sum,
            'type' => 'Пополнение',
            'accounted_amount' => $accountedAmount
        ]);
    }


}
