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
            $organizations = $client->organizations()
                ->where('has_access', true)
                ->get();

            if ($organizations->count() == 0) continue;

            $currentMonth = Carbon::now();
            $daysInMonth = $currentMonth->daysInMonth;
            $dailyRate = $client->tariffPrice->tariff_price / $daysInMonth;

            foreach ($organizations as $organization) {
                $validity_period = floor($organization->balance / $dailyRate);

                if ($validity_period <= 10) {
                    Mail::to($client->email)->send(new NotifyClientMail($client, $validity_period));
                }
            }
        }
    }
}
