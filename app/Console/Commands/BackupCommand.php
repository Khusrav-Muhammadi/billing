<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\DbDumper\Databases\MySql;
use ZipArchive;

class BackupCommand extends Command
{
    protected $signature = 'backup:tenants';
    protected $description = 'Backup all tenant databases and upload to Google Drive';

    public function handle()
    {
        $accessToken = $this->getAccessToken();
        $backupDir = storage_path('app/backups');
        File::makeDirectory($backupDir, 0755, true, true);

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $archivePath = "$backupDir/billings_backup_$timestamp.zip";

            $dbName = env('DB_DATABASE');
            $dumpPath = "$backupDir/{$dbName}.sql";

            $this->info("Dumping database: $dbName");

            MySql::create()
                ->setDbName($dbName)
                ->setUserName(env('DB_USERNAME'))
                ->setPassword(env('DB_PASSWORD'))
                ->setHost(env('DB_HOST'))
                ->dumpToFile($dumpPath);

            if (!file_exists($dumpPath) || filesize($dumpPath) === 0) {
                throw new \Exception("Ошибка: Дамп базы $dbName не был создан!");
            }

            $dumpFiles[] = $dumpPath;

        $this->info("Creating archive...");
        $zip = new ZipArchive;

        if ($zip->open($archivePath, ZipArchive::CREATE) === true) {
            foreach ($dumpFiles as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();
        } else {
            Log::error("Ошибка дампа базы");
            throw new \Exception("Ошибка создания ZIP-архива.");
        }

        $this->uploadToGoogleDrive($archivePath, $accessToken);
        $this->sendTelegramFile($archivePath);


        File::deleteDirectory($backupDir);
        $this->info("Local backup files deleted.");
    }

    private function getAccessToken()
    {
        $response = Http::post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => config('services.google.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        return json_decode((string)$response->getBody(), true)['access_token'];
    }

    private function uploadToGoogleDrive($filePath, $accessToken)
    {
        $fileName = basename($filePath);
        $folderId = config('services.google.folder_id');

        $metadata = [
            'name' => $fileName,
            'parents' => [$folderId]
        ];

        $response = Http::withToken($accessToken)
            ->attach('metadata', json_encode($metadata), 'metadata.json')
            ->attach('file', file_get_contents($filePath), $fileName, ['Content-Type' => 'application/zip'])
            ->post("https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart");

        if ($response->successful()) {
            Log::error("Дамп базы отправлен");
        }
    }
}
