<?php

namespace App\Jobs;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;

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

        $jobs = [];
        $period = CarbonPeriod::create($from, '1 day', $to);

        foreach ($period as $date) {
            $jobs[] = new CreateDayClosingDocumentForDateJob(
                Carbon::parse($date)->toDateString(),
                $this->authorId
            );
        }

        if (!empty($jobs)) {
            Bus::chain($jobs)->dispatch();
        }
    }
}
