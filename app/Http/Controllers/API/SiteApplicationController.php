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
use Illuminate\Support\Facades\Http;
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
            if (!empty($validated['email']) && !$this->isValidEmail($validated['email'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Указанный email адрес не является действительным.'
                ], 400);
            }

            $existingClient = $this->checkExistingClient($validated);
            if ($existingClient) {
                return response()->json([
                    'success' => false,
                    'message' => $existingClient
                ], 409); // 409 Conflict
            }

            $client = $this->createDemoClient($validated);
            if (!$client) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось создать демо-аккаунт. Попробуйте позже.'
                ], 500);
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

    private function checkExistingClient(array $data): ?string
    {
        $subdomain = $this->generateSubdomain($data['email']);

        $existingClient = Client::query()
            ->where(function($query) use ($data, $subdomain) {
                $query->where('sub_domain', $subdomain)
                    ->orWhere('phone', $data['phone']);

                if (!empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }
            })
            ->first();

        if ($existingClient) {
            if ($existingClient->sub_domain === $subdomain) {
                return 'Клиент с таким поддоменом уже существует.';
            }
            if ($existingClient->phone === $data['phone']) {
                return 'Клиент с таким номером телефона уже существует.';
            }
            if (!empty($data['email']) && $existingClient->email === $data['email']) {
                return 'Клиент с таким email адресом уже существует.';
            }
        }

        return null;
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
            'partner_id' => 'nullable',
            'manager_id' => 'nullable'
        ], [
            'fio.required' => 'Поле ФИО обязательно для заполнения.',
            'fio.string' => 'Поле ФИО должно быть строкой.',
            'fio.max' => 'Поле ФИО не должно превышать 255 символов.',
            'phone.required' => 'Поле телефон обязательно для заполнения.',
            'phone.string' => 'Поле телефон должно быть строкой.',
            'phone.max' => 'Поле телефон не должно превышать 20 символов.',
            'email.required' => 'Поле email обязательно для демо-аккаунта.',
            'email.email' => 'Поле email должно содержать действительный email адрес.',
            'email.max' => 'Поле email не должно превышать 255 символов.',
            'region_id.integer' => 'Поле регион должно быть числом.',
            'request_type.required' => 'Поле тип заявки обязательно для заполнения.',
            'request_type.string' => 'Поле тип заявки должно быть строкой.',
            'request_type.in' => 'Поле тип заявки должно быть одним из: demo, partner, corporate, individual.',
        ]);
    }

    private function isValidEmail(string $email): bool
    {
        try {
            $response = Http::timeout(10)->get('https://api.emailvalidation.io/v1/info', [
                'apikey' => 'ema_live_pLU3hBeUBOIc2NJqRuJOt6wh2TsqDilv9FBduowR',
                'email' => $email
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $isDeliverable = ($data['state'] ?? '') === 'deliverable';
                $hasValidFormat = ($data['format_valid'] ?? false);
                $passesSmtpCheck = ($data['smtp_check'] ?? false);
                $isNotDisposable = !($data['disposable'] ?? true);

                return $isDeliverable || ($hasValidFormat && $passesSmtpCheck && $isNotDisposable);
            }
            else {
                return $this->fallbackEmailValidation($email);
            }
        } catch (\Exception $e) {
            return $this->fallbackEmailValidation($email);
        }
    }

    private function fallbackEmailValidation(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);
        $hasRecords = checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');

        // Проверяем на известные временные домены
        $tempDomains = ['10minutemail.com', 'tempmail.org', 'guerrillamail.com', 'mailinator.com'];
        $isNotTemp = !in_array(strtolower($domain), $tempDomains);

        return $hasRecords && $isNotTemp;
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
            'sub_domain' => $this->generateSubdomain($data['email']),
            'currency_id' => $currency_id,
            'manager_id' => $data['manager_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
        ];

        if (!empty($clientData['manager_id'])) {
            $manager = \App\Models\User::query()->find($clientData['manager_id']);

            if ($manager && !empty($manager->partner_id)) {
                $clientData['partner_id'] = $manager->partner_id;
            }
        }

        try {
            return Client::create($clientData);
        } catch (\Exception $e) {
            \Log::error('Ошибка создания демо-клиента: ' . $e->getMessage(), $clientData);
            return null;
        }
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
