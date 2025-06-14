<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Jobs\NewSiteRequestJob;
use App\Jobs\SendToShamJob;
use App\Jobs\SubDomainJob;
use App\Models\Client;
use App\Models\Country;
use App\Models\SiteApplications;
use App\Models\Tariff;
use App\Models\Transaction;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\OrganizationRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SiteApplicationController extends Controller
{
    public function index()
    {
        $applications = SiteApplications::all();
        return view('admin.site-applications.index', compact('applications'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        if ($validated['request_type'] === 'demo') {

            $client = $this->createDemoClient($validated);
            if (!$client) {
                return response()->json([
                    'success' => false
                ], 400);
            }
            SubDomainJob::dispatch($client);
            $data = [
                'name' => $client->name,
                'phone' => $client->phone,
                'client_id' => $client->id,
                'has_access' => true,
                'partner_id' => $validated['partner_id'],
                'country_id' => $validated['region_id']
            ];


           (new OrganizationRepository())->store($client, $data);

           SendToShamJob::dispatch($client->phone, $client->tariff?->name, $client->email, $client->name, $client->country?->name, $client->partner?->name);
        }
        elseif ($validated['request_type'] === 'individual') {

            SendToShamJob::dispatch($validated['phone'], 'Тариф: Индивидуальный тариф', $validated['email'], $validated['fio'], Country::find($validated['region_id'])?->name );
        }
        else {
            $this->createRegularApplication($validated);
            NewSiteRequestJob::dispatch(User::first(), $validated['request_type']);
        }

        return response()->json(['success' => true]);
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fio' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => [
                Rule::requiredIf($request->input('request_type') === 'demo'),
                'email',
                'max:255'
            ],
            'region_id' => 'nullable|integer',
            'request_type' => 'required|string|in:demo,partner,corporate,individual',
            'partner_id' => 'nullable'
        ]);
    }

    private function createDemoClient(array $data): ?Client
    {
        $countryId = $data['region_id'] ?? 1;

        $tariffId = match ($countryId) {
            2 => 8,
            default => 4,
        };
        $currency_id = match ($countryId) {
            2 => 2,
            default => 1,
        };

        $clientData = [
            'name' => $data['fio'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'country_id' => $countryId,
            'is_demo' => true,
            'tariff_id' => $tariffId,
            'partner_id' => $data['partner_id'],
            'sub_domain' => $this->generateSubdomain($data['email']),
            'currency_id' => $currency_id,
        ];

        $client = Client::query()
            ->where('sub_domain', $clientData['sub_domain'])
            ->orWhere('phone', $clientData['phone'])
            ->first();

        if ($client) {
            return null;
        }

        return Client::create($clientData);
    }


    private function generateSubdomain(string $email): string
    {
        [$local, $domain] = explode('@', $email);
        $isPublic = in_array(strtolower($domain), config('app.public_domains'));

        return Str::of($isPublic ? $local : $local . $domain)
            ->replace('_', '')
            ->lower()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->trim('-')
            ->replaceMatches('/-+/', '-')
            ->whenEmpty(fn() => 'default');
    }

    private function createRegularApplication(array $data): void
    {
        SiteApplications::create([
            'fio' => $data['fio'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'region_id' => $data['region_id'],
            'request_type' => $data['request_type']
        ]);
    }
}
