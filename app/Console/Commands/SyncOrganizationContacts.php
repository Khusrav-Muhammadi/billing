<?php

namespace App\Console\Commands;

use App\Models\Organization;
use Illuminate\Console\Command;

class SyncOrganizationContacts extends Command
{
    protected $signature = 'organizations:sync-contact
                            {--dry-run : Показывает изменения без сохранения}
                            {--force : Перезаписывает email/phone даже если уже заполнены}
                            {--chunk=500 : Размер чанка}';

    protected $description = 'Копирует email/phone из client в organizations (organization->client)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunk = (int) $this->option('chunk');

        $updated = 0;
        $skipped = 0;

        Organization::query()
            ->whereNotNull('client_id')
            ->with('client:id,email,phone')
            ->orderBy('id')
            ->chunkById($chunk, function ($organizations) use ($dryRun, $force, &$updated, &$skipped) {
                foreach ($organizations as $organization) {
                    $client = $organization->client;
                    if (!$client) {
                        $skipped++;
                        continue;
                    }

                    $clientEmail = trim((string) ($client->email ?? ''));
                    $clientPhone = trim((string) ($client->phone ?? ''));

                    $newEmail = $organization->email;
                    $newPhone = $organization->phone;

                    if ($clientEmail !== '' && ($force || trim((string) ($organization->email ?? '')) === '')) {
                        $newEmail = $clientEmail;
                    }

                    if ($clientPhone !== '' && ($force || trim((string) ($organization->phone ?? '')) === '')) {
                        $newPhone = $clientPhone;
                    }

                    $dirty = ($newEmail !== $organization->email) || ($newPhone !== $organization->phone);

                    if (!$dirty) {
                        $skipped++;
                        continue;
                    }

                    $this->line(sprintf(
                        '#%d: email %s -> %s; phone %s -> %s',
                        $organization->id,
                        $organization->email ?: 'NULL',
                        $newEmail ?: 'NULL',
                        $organization->phone ?: 'NULL',
                        $newPhone ?: 'NULL'
                    ));

                    if (!$dryRun) {
                        $organization->email = $newEmail;
                        $organization->phone = $newPhone;
                        $organization->save();
                    }

                    $updated++;
                }
            });

        $this->info("Готово. Обновлено: {$updated}, пропущено: {$skipped}" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}

