<?php

namespace App\Console\Commands;

use App\Jobs\TariffExtensionJob;
use App\Models\IntegrationActionLog;
use App\Models\Organization;
use App\Services\Mailing\ResendMailService;
use Illuminate\Console\Command;

class ControlDemoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:control-demo-command';

    //
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    private const DEMO_DAYS = 14;
    private const FOLLOW_UP_DAYS = [3, 6, 14, 18];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizations = Organization::query()
            ->whereDoesntHave('connections')
            ->with('client:id,name,email,is_demo,is_active,created_at')
            ->get();

        $sent = 0;

        foreach ($organizations as $organization) {
            $daysSinceCreated = (int)$organization->created_at->diffInDays(now());

            if ($daysSinceCreated > self::DEMO_DAYS) {
                $organization->client?->update(['is_active' => false]);
                TariffExtensionJob::dispatch($organization, false);
                $sent += $this->sendDemoExpiredFollowUpIfNeeded($organization, $daysSinceCreated);
            }
        }

        $this->info("Demo follow-up emails sent: {$sent}");
    }

    private function sendDemoExpiredFollowUpIfNeeded(Organization $organization, int $daysSinceCreated): int
    {
        $client = $organization->client;
        if (!$client || !$client->is_demo || trim((string)$client->email) === '') {
            return 0;
        }

        $daysAfterExpiration = $daysSinceCreated - self::DEMO_DAYS;
        if (!in_array($daysAfterExpiration, self::FOLLOW_UP_DAYS, true)) {
            return 0;
        }

        $action = 'demo_expired_followup_' . $daysAfterExpiration;
        if ($this->followUpAlreadySent($organization, $action)) {
            return 0;
        }

        app(ResendMailService::class)->sendWithView(
            to: (string)$client->email,
            subject: $this->followUpSubject($daysAfterExpiration),
            view: 'mail.demo_expired_followup',
            data: [
                'client' => $client,
                'organization' => $organization,
                'daysAfterExpiration' => $daysAfterExpiration,
                'variant' => $this->followUpVariant($daysAfterExpiration),
            ],
            sendInternalCopy: false,
            logContext: [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'action' => $action,
            ]
        );

        return 1;
    }

    private function followUpAlreadySent(Organization $organization, string $action): bool
    {
        return IntegrationActionLog::query()
            ->where('type', 'email')
            ->where('action', $action)
            ->where(function ($query) use ($organization): void {
                $query->where('organization_id', $organization->id);

                if ($organization->client_id) {
                    $query->orWhere('client_id', $organization->client_id);
                }
            })
            ->exists();
    }

    private function followUpSubject(int $daysAfterExpiration): string
    {
        return match ($daysAfterExpiration) {
            3 => 'Ваш демо-доступ shamCRM завершился',
            6 => 'Готовы продолжить работу в shamCRM?',
            14 => 'Демо shamCRM закончилось: сохраним ваши настройки',
            18 => 'Последнее напоминание по вашему демо shamCRM',
            default => 'Демо-доступ shamCRM завершился',
        };
    }

    private function followUpVariant(int $daysAfterExpiration): array
    {
        return match ($daysAfterExpiration) {
            3 => [
                'title' => 'Демо-период завершился',
                'lead' => 'Спасибо, что протестировали shamCRM. Если сервис помог вам увидеть порядок в продажах и клиентах, мы можем быстро перевести демо в рабочий аккаунт.',
                'accent' => 'Подключение сохранит ваш поддомен и данные, которые были подготовлены во время демо.',
            ],
            6 => [
                'title' => 'Можно продолжить без долгой настройки',
                'lead' => 'Ваш демо-доступ уже завершился, но мы можем помочь спокойно перейти на оплату и продолжить работу там, где вы остановились.',
                'accent' => 'Напишите нам, и менеджер подберет удобный тариф и срок оплаты.',
            ],
            14 => [
                'title' => 'Сохраним ваши настройки',
                'lead' => 'Прошло две недели после окончания демо. Если вы планируете вернуться к shamCRM, лучше активировать аккаунт сейчас, пока данные и сценарии еще актуальны.',
                'accent' => 'Мы поможем восстановить доступ и довести систему до рабочего запуска.',
            ],
            18 => [
                'title' => 'Финальное напоминание',
                'lead' => 'Ваш демо-период закончился уже давно. Если shamCRM вам подходит, самое время подключить рабочий доступ и не начинать внедрение заново позже.',
                'accent' => 'Ответьте на письмо или свяжитесь с нами, чтобы менеджер помог с оплатой.',
            ],
            default => [
                'title' => 'Демо-период завершился',
                'lead' => 'Спасибо, что протестировали shamCRM.',
                'accent' => 'Мы готовы помочь с подключением.',
            ],
        };
    }
}
