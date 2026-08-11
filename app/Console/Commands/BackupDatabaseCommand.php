<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Sauvegarde complète de la base de données (export SQL compressé) indépendante de la base de production';

    public function handle(DatabaseBackupService $service): int
    {
        try {
            $result = $service->run();
            $sizeMb = round($result['size'] / 1024 / 1024, 2);
            $this->info("Sauvegarde créée : {$result['filename']} ({$result['tables']} tables, {$result['rows']} lignes, {$sizeMb} Mo, {$result['duration_seconds']}s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Échec de la sauvegarde automatique de la base de données: '.$e->getMessage(), ['exception' => $e]);
            $this->error('Échec de la sauvegarde: '.$e->getMessage());

            User::query()->where('is_platform_admin', true)->get(['id'])->each(function (User $admin) use ($e) {
                AppNotification::create([
                    'user_id' => $admin->id,
                    'title' => 'Échec de la sauvegarde automatique',
                    'body' => "La sauvegarde planifiée de la base de données a échoué : {$e->getMessage()}",
                    'type' => 'danger',
                    'action_url' => route('admin.backups.index'),
                ]);
            });

            return self::FAILURE;
        }
    }
}
