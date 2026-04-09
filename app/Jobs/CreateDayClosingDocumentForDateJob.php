<?php

namespace App\Jobs;

use App\Services\DayClosings\DayClosingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDayClosingDocumentForDateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public string $date,
        public int $authorId
    ) {
    }

    public function handle(DayClosingService $dayClosingService): void
    {
        $parsedDate = Carbon::parse($this->date);

        $dayClosingService->createDocumentsForPeriod(
            $parsedDate->copy(),
            $parsedDate->copy(),
            $this->authorId
        );
    }
}
