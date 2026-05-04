<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\AddPackRequest;
use App\Http\Requests\Organization\RejectRequest;
use App\Http\Requests\Organization\StoreRequest;
use App\Http\Requests\Organization\UpdateRequest;
use App\Models\Client;
use App\Models\ClientBalance;
use App\Models\ConnectedClientServices;
use App\Models\Country;
use App\Models\IntegrationActionLog;
use App\Models\Organization;
use App\Models\OrganizationPack;
use App\Models\OrganizationConnectionStatus;
use App\Models\Tariff;
use App\Models\User;
use App\Repositories\Contracts\OrganizationRepositoryInterface;
use App\Services\Organizations\OrganizationValidityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class OrganizationV2Controller extends Controller
{
    public function __construct(public OrganizationRepositoryInterface $repository) { }

    public function index(Request $request)
    {
        $organizations = $this->repository->index($request->all());
        $this->hydrateRealBalances($organizations);
        app(OrganizationValidityService::class)->hydrate($organizations);

        $partners = User::query()->where('role', 'partner')->get();
        $tariffs = Tariff::all();
        $countries = Country::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', compact('organizations'))->render();
        }

        return view('v2.organizations_v2.index', compact('organizations', 'partners', 'tariffs', 'countries'));
    }

    public function demo(Request $request)
    {
        $organizations = $this->repository->demo($request->all());
        $this->hydrateRealBalances($organizations);
        $this->hydrateDemoValidateDates($organizations);

        $partners = User::query()->where('role', 'partner')->get();
        $countries = Country::all();

        if ($request->ajax()) {
            return view('admin.partials.organizations_v2', [
                'organizations' => $organizations,
                'isDemoList' => true,
            ])->render();
        }

        return view('v2.organizations_v2.demo', compact('organizations', 'partners', 'countries'));
    }

    public function store(Client $client, StoreRequest $request): RedirectResponse
    {
        $organization = $this->repository->store($client, $request->validated());

        if (!$organization) {
            return redirect()->back()->with('error', 'Не удалось создать организацию');
        }

        return redirect()->back();
    }

    public function show(Organization $organization)
    {
        $organization->load([
            'client:id,name,email,phone,sub_domain,last_activity,is_active,partner_id,tariff_id,country_id',
            'client.country:id,name,currency_id',
            'client.country.currency:id,name,symbol_code',
            'client.partner:id,name',
            'client.tariffPrice:id,tariff_id',
            'client.tariffPrice.tariff:id,name,user_count',
        ]);

        $connectedServices = ConnectedClientServices::query()
            ->where('client_id', (int) $organization->id)
            ->with([
                'tariff:id,name',
                'offerCurrency:id,name,symbol_code',
            ])
            ->orderBy('date')
            ->get();

        $connectionStatusHistory = collect();
        if (Schema::hasTable('organization_connection_statuses')) {
            $connectionStatusHistory = OrganizationConnectionStatus::query()
                ->where('organization_id', (int) $organization->id)
                ->with([
                    'author:id,name',
                    'commercialOffer:id,request_type',
                    'dayClosing:id,doc_number,date',
                ])
                ->orderByDesc('status_date')
                ->orderByDesc('id')
                ->get();
        }

        $balanceOperations = ClientBalance::query()
            ->where('organization_id', (int) $organization->id)
            ->with('currency:id,name,symbol_code')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $realBalance = $this->calculateRealBalance($organization, $balanceOperations);

        $integrationLogs = IntegrationActionLog::query()
            ->where(function ($query) use ($organization): void {
                $query->where('organization_id', (int)$organization->id);

                if ($organization->client_id) {
                    $query->orWhere('client_id', (int)$organization->client_id);
                }
            })
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('v2.organizations_v2.show', compact(
            'organization',
            'connectedServices',
            'connectionStatusHistory',
            'balanceOperations',
            'realBalance',
            'integrationLogs'
        ));
    }

    public function update(Organization $organization, UpdateRequest $request): RedirectResponse
    {
        $this->repository->update($organization, $request->validated());

        return redirect()->back();
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->repository->destroy($organization);

        return redirect()->back();
    }

    public function access(Organization $organization, RejectRequest $request)
    {
        $this->repository->access($organization, $request->validated());

        return redirect()->back();
    }

    public function addPack(Organization $organization, AddPackRequest $request)
    {
        $this->repository->addPack($organization, $request->validated());

        return redirect()->back();
    }

    public function deletePack(OrganizationPack $organizationPack)
    {
        $organizationPack->delete();

        return redirect()->back();
    }

    public function retryIntegrationLog(IntegrationActionLog $log): RedirectResponse
    {
        if (!in_array($log->type, ['api', 'email'], true)) {
            return redirect()->back()->with('error', 'Этот тип лога нельзя повторить');
        }

        if (!$this->shouldRetryIntegrationLog($log)) {
            return redirect()->back()->with('error', 'Повтор доступен только для неуспешных запросов');
        }

        try {
            $response = $log->type === 'email'
                ? $this->retryEmailLog($log)
                : $this->retryApiLog($log);

            $successful = in_array($response->status(), [200, 201], true);
            $this->storeRetriedIntegrationLog($log, $response, $successful);

            return redirect()
                ->back()
                ->with($successful ? 'success' : 'error', $successful ? 'Запрос повторно отправлен' : 'Запрос повторно отправлен, но сервер вернул ошибку');
        } catch (\Throwable $e) {
            $this->storeFailedRetryLog($log, $e->getMessage());

            return redirect()->back()->with('error', 'Не удалось повторить запрос: ' . $e->getMessage());
        }
    }

    private function hydrateRealBalances(Collection|LengthAwarePaginator $organizations): void
    {
        $items = $organizations instanceof LengthAwarePaginator
            ? $organizations->getCollection()
            : $organizations;

        if ($items->isEmpty()) {
            return;
        }

        $organizationIds = $items->pluck('id')->map(fn ($id) => (int) $id)->all();

        $balanceByOrganization = ClientBalance::query()
            ->selectRaw("
                organization_id,
                currency_id,
                COALESCE(SUM(CASE WHEN type = 'income' THEN sum ELSE 0 END), 0) AS total_income,
                COALESCE(SUM(CASE WHEN type = 'outcome' THEN sum ELSE 0 END), 0) AS total_outcome
            ")
            ->whereIn('organization_id', $organizationIds)
            ->groupBy('organization_id', 'currency_id')
            ->get()
            ->groupBy('organization_id');

        foreach ($items as $organization) {
            $rows = $balanceByOrganization->get((int) $organization->id, collect());
            $targetCurrencyId = (int) ($organization->client?->country?->currency_id ?? 0);

            if ($targetCurrencyId > 0) {
                $sameCurrencyRows = $rows->where('currency_id', $targetCurrencyId)->values();
                if ($sameCurrencyRows->isNotEmpty()) {
                    $rows = $sameCurrencyRows;
                }
            }

            $income = (float) $rows->sum('total_income');
            $outcome = (float) $rows->sum('total_outcome');

            $organization->setAttribute('real_balance', round($income - $outcome, 4));
        }
    }

    private function hydrateDemoValidateDates(Collection|LengthAwarePaginator $organizations): void
    {
        $items = $organizations instanceof LengthAwarePaginator
            ? $organizations->getCollection()
            : $organizations;

        foreach ($items as $organization) {
            if (!$organization->client || !$organization->client->is_demo) {
                continue;
            }

            $organization->client->setAttribute(
                'validate_date',
                optional($organization->client->created_at)->copy()?->addWeeks(2)
            );
        }
    }

    private function calculateRealBalance(Organization $organization, Collection $operations): float
    {
        $targetCurrencyId = (int) ($organization->client?->country?->currency_id ?? 0);

        $rows = $operations;
        if ($targetCurrencyId > 0) {
            $sameCurrencyRows = $operations->where('currency_id', $targetCurrencyId)->values();
            if ($sameCurrencyRows->isNotEmpty()) {
                $rows = $sameCurrencyRows;
            }
        }

        $income = (float) $rows->where('type', 'income')->sum('sum');
        $outcome = (float) $rows->where('type', 'outcome')->sum('sum');

        return round($income - $outcome, 4);
    }

    private function shouldRetryIntegrationLog(IntegrationActionLog $log): bool
    {
        if ($log->successful === false) {
            return true;
        }

        if ($log->status_code !== null) {
            return !in_array((int)$log->status_code, [200, 201], true);
        }

        return false;
    }

    private function retryApiLog(IntegrationActionLog $log): \Illuminate\Http\Client\Response
    {
        $method = strtoupper((string)($log->method ?: 'POST'));
        $url = trim((string)$log->url);

        if ($url === '') {
            throw new \RuntimeException('URL запроса не найден');
        }

        $payload = is_array($log->payload) ? $log->payload : [];

        return Http::withHeaders([
            'Accept' => 'application/json',
        ])->send($method, $url, [
            'json' => $payload,
        ]);
    }

    private function retryEmailLog(IntegrationActionLog $log): \Illuminate\Http\Client\Response
    {
        $payload = data_get($log->payload, 'request_body');
        if (!is_array($payload)) {
            throw new \RuntimeException('Тело письма для повторной отправки не найдено');
        }

        return Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.resend.api-key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.resend.com/emails', $payload);
    }

    private function storeRetriedIntegrationLog(
        IntegrationActionLog $sourceLog,
        \Illuminate\Http\Client\Response $response,
        bool $successful
    ): void {
        IntegrationActionLog::query()->create([
            'organization_id' => $sourceLog->organization_id,
            'client_id' => $sourceLog->client_id,
            'commercial_offer_id' => $sourceLog->commercial_offer_id,
            'type' => $sourceLog->type,
            'action' => $sourceLog->action,
            'method' => $sourceLog->type === 'email' ? 'POST' : $sourceLog->method,
            'url' => $sourceLog->type === 'email' ? 'https://api.resend.com/emails' : $sourceLog->url,
            'recipient' => $sourceLog->recipient,
            'subject' => $sourceLog->subject,
            'status_code' => $response->status(),
            'successful' => $successful,
            'payload' => $sourceLog->payload,
            'response' => $this->integrationResponseBody($response),
            'error' => $successful ? null : 'Retry returned HTTP ' . $response->status(),
            'occurred_at' => now(),
        ]);
    }

    private function storeFailedRetryLog(IntegrationActionLog $sourceLog, string $error): void
    {
        IntegrationActionLog::query()->create([
            'organization_id' => $sourceLog->organization_id,
            'client_id' => $sourceLog->client_id,
            'commercial_offer_id' => $sourceLog->commercial_offer_id,
            'type' => $sourceLog->type,
            'action' => $sourceLog->action,
            'method' => $sourceLog->type === 'email' ? 'POST' : $sourceLog->method,
            'url' => $sourceLog->type === 'email' ? 'https://api.resend.com/emails' : $sourceLog->url,
            'recipient' => $sourceLog->recipient,
            'subject' => $sourceLog->subject,
            'successful' => false,
            'payload' => $sourceLog->payload,
            'error' => $error,
            'occurred_at' => now(),
        ]);
    }

    private function integrationResponseBody(\Illuminate\Http\Client\Response $response): array
    {
        try {
            $json = $response->json();
            if (is_array($json)) {
                return $json;
            }
        } catch (\Throwable) {
        }

        return ['body' => mb_substr((string)$response->body(), 0, 5000)];
    }

}
