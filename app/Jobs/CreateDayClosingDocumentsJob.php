<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDayClosingDocumentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public int $authorId
    ) {
    }

    public function handle(): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->startOfDay();

        if ($from->greaterThan($to)) {
            return;
        }

        CreateDayClosingDocumentsCursorJob::dispatch($from->toDateString(), $to->toDateString(), $this->authorId)
            ->onConnection($this->connection)
            ->onQueue($this->queue);
    }
}
