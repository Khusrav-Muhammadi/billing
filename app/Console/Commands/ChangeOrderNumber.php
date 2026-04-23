<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class ChangeOrderNumber extends Command
{
    protected $signature = 'organizations:update-order-numbers';
    protected $description = 'Update order_number for existing organizations';

    public function handle(): void
    {
        $organizations = Organization::query()
            ->whereNull('order_number')
            ->orWhere('order_number', '')
            ->get();

        $this->info("Found {$organizations->count()} organizations to update.");

        $bar = $this->output->createProgressBar($organizations->count());
        $bar->start();

        foreach ($organizations as $organization) {
            $timestamp = $organization->created_at->format('YmdHis');
            $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $organization->forceFill([
                'order_number' => $timestamp . $random,
            ])->saveQuietly();

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');
    }
}
