<?php

namespace App\Jobs;

use App\Services\DayClosings\DayClosingService;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDayClosingDocumentsCursorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $currentDate,
        public string $dateTo,
        public int $authorId
    ) {
    }

    public function handle(DayClosingService $dayClosingService): void
    {
        $current = Carbon::parse($this->currentDate)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        if ($current->greaterThan($to)) {
            return;
        }

        $dayClosingService->createDocumentsForPeriod(
            $current->copy(),
            $current->copy(),
            $this->authorId
        );

        if ($current->equalTo($to)) {
            return;
        }

        $nextDate = $current->copy()->addDay()->toDateString();

        self::dispatch($nextDate, $to->toDateString(), $this->authorId)
            ->onConnection($this->connection)
            ->onQueue($this->queue);
    }
}

