<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Repositories\ClientRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ControlNfrCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:control-nfr-command';

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
        $clients = Client::query()
            ->where([
                ['nfr', true],
                ['is_active', false],
            ])->get();

        $threeMonthsAgo = now()->subMonths(3);

        foreach ($clients as $client) {
            $count = Client::query()
                ->where([
                    ['partner_id', $client->partner?->id],
                    ['is_active', true],
                    ['nfr', false],
                ])
                ->whereBetween('created_at', [$threeMonthsAgo, now()])
                ->count();


            if ($count < 1) {
                $repository = new ClientRepository();
                $repository->activation($client);
            }
        }
    }
}
