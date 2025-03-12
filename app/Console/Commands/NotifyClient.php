<?php

namespace App\Console\Commands;

use App\Mail\NotifyClientMail;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class NotifyClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $clients = Client::where([
            ['is_active', true],
            ['nfr', false],
            ['is_demo', false]
        ])->get();

        foreach ($clients as $client) {
            $organizationCount = $client->organizations()
                ->where('has_access', true)
                ->count();

            if ($organizationCount == 0) continue;

            $currentMonth = Carbon::now();

            $daysInMonth = $currentMonth->daysInMonth;

            $validity_period = floor($client->balance / ($organizationCount * ($client->tariff->price / $daysInMonth)));

            if ($validity_period <= 10) {
                Mail::to($client->email)->send(new NotifyClientMail($client, $validity_period));
            }

        }
    }
}
