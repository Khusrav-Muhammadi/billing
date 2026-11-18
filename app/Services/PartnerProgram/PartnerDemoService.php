<?php

namespace App\Services\PartnerProgram;

use App\Jobs\ProvisionDemoAccountJob;
use App\Models\Client;
use App\Models\DemoRequest;
use App\Models\User;
use App\Services\Site\DemoEmailAvailability;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Демо-аккаунты shamCRM из партнёрского портала.
 *
 * Тот же поток, что форма на shamcrm.com: DemoRequest -> ProvisionDemoAccountJob.
 * Клиент закрепляется за партнёром (partner_id), логин и пароль уходят на почту клиента.
 * Своё демо партнёр заводит на свою почту.
 */
class PartnerDemoService
{
    public function __construct(private readonly DemoEmailAvailability $availability)
    {
    }

    /**
     * @param  array{name: string, email: string, phone: string, country_id?: int|null}  $data
     *
     * @throws ValidationException
     */
    public function create(User $partner, array $data): DemoRequest
    {
        $email = DemoEmailAvailability::normalize($data['email']);

        // Повторное нажатие: заявка этого партнёра на эту почту уже в работе или готова.
        $existing = DemoRequest::query()
            ->where('email', $email)
            ->whereIn('status', [DemoRequest::STATUS_QUEUED, DemoRequest::STATUS_PROVISIONING, DemoRequest::STATUS_READY])
            ->latest('id')
            ->first();

        if ($existing) {
            if ((int) $existing->partner_id === (int) $partner->id) {
                return $existing;
            }

            throw ValidationException::withMessages([
                'email' => 'На этот email уже есть аккаунт shamCRM.',
            ]);
        }

        $check = $this->availability->check($email);

        if (!$check['available']) {
            throw ValidationException::withMessages(['email' => $check['message']]);
        }

        $demoRequest = DemoRequest::create([
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $data['phone'],
            'country_id' => $data['country_id'] ?? $partner->country_id,
            'partner_id' => $partner->id,
            'status' => DemoRequest::STATUS_QUEUED,
            'step' => DemoRequest::STEP_ACCOUNT,
            'ip' => request()->ip(),
            'user_agent' => 'partners-portal',
        ]);

        ProvisionDemoAccountJob::dispatch($demoRequest);

        return $demoRequest;
    }

    /** Последние заявки партнёра: и его собственное демо, и клиенты. */
    public function list(User $partner, int $limit = 100): Collection
    {
        return DemoRequest::query()
            ->where('partner_id', $partner->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (DemoRequest $r) => $this->serialize($r));
    }

    public function serialize(DemoRequest $request): array
    {
        $client = $request->client_id ? Client::query()->find($request->client_id) : null;

        return [
            'uuid' => $request->uuid,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => $request->status,
            'step' => $request->step,
            'step_number' => $request->stepNumber(),
            'step_total' => count(DemoRequest::STEPS),
            // Одноразовая ссылка входа живёт ~15 минут, потом — обычный вход по паролю из письма.
            'login_url' => $request->hasUsableLoginUrl() ? $request->login_url : null,
            'crm_url' => $client && $client->sub_domain ? $client->crmUrl() : null,
            'message' => $request->failure_reason,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
