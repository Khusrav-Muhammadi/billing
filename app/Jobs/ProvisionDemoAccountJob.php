<?php

namespace App\Jobs;

use App\Exceptions\DemoProvisioningException;
use App\Models\DemoRequest;
use App\Services\Site\DemoProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Выдача демо в фоне: сайт получает ответ сразу, а прогресс читает из заявки.
 */
class ProvisionDemoAccountJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Поддомен может не резолвиться первые секунды — даём ещё две попытки. */
    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public DemoRequest $demoRequest)
    {
    }

    public function uniqueId(): string
    {
        return (string) $this->demoRequest->uuid;
    }

    public function backoff(): array
    {
        return [30, 90];
    }

    public function handle(DemoProvisioner $provisioner): void
    {
        $this->demoRequest->refresh();

        $provisioner->provision($this->demoRequest);
    }

    public function failed(?Throwable $exception): void
    {
        $this->demoRequest->refresh();

        if (!$this->demoRequest->isPending()) {
            return;
        }

        Log::error('ProvisionDemoAccountJob: giving up', [
            'demo_request_id' => $this->demoRequest->id,
            'email' => $this->demoRequest->email,
            'step' => $this->demoRequest->step,
            'error' => $exception?->getMessage(),
        ]);

        $expected = $exception instanceof DemoProvisioningException ? $exception : null;

        app(DemoProvisioner::class)->fail(
            $this->demoRequest,
            $expected?->reason ?? 'unexpected',
            $expected?->getMessage()
                ?? 'Не удалось подготовить демо. Напишите в поддержку — выдадим доступ вручную.',
            rollback: $expected?->rollback ?? true
        );
    }
}
