<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sauvegarde complète de la base de données applicative (export SQL compressé),
 * indépendante de la base de production LWS elle-même : les fichiers sont
 * stockés sur le disque privé de l'application (storage/app/private/backups),
 * donc consultables/téléchargeables même si la base de données est
 * indisponible. Ne dépend d'aucun binaire externe (mysqldump...) — tout est
 * fait en PHP pur pour rester portable entre l'environnement local et
 * l'hébergement mutualisé LWS.
 */
class DatabaseBackupService
{
    private const DIRECTORY = 'backups';

    public function run(): array
    {
        $startedAt = microtime(true);
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver !== 'mysql') {
            throw new \RuntimeException("Sauvegarde non prise en charge pour le pilote de base de données [{$driver}].");
        }

        $database = $connection->getDatabaseName();
        $tables = collect(DB::select('SHOW TABLES'))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->values();

        $filename = 'backup-'.now()->format('Y-m-d_His').'-'.Str::random(6).'.sql.gz';
        $path = self::DIRECTORY.'/'.$filename;

        $tmpPath = tempnam(sys_get_temp_dir(), 'sitiame_backup_');
        $handle = gzopen($tmpPath, 'w9');

        gzwrite($handle, "-- Sauvegarde SITIAME Capital [{$database}] — ".now()->toDateTimeString()." --\n");
        gzwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $rowCount = 0;

        foreach ($tables as $table) {
            $createRow = DB::select("SHOW CREATE TABLE `{$table}`");
            $createSql = $createRow[0]->{'Create Table'} ?? null;
            if (! $createSql) {
                continue;
            }

            gzwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            gzwrite($handle, $createSql.";\n\n");

            $columns = null;
            $batch = [];

            foreach (DB::table($table)->cursor() as $row) {
                $row = (array) $row;
                if ($columns === null) {
                    $columns = array_keys($row);
                }
                $batch[] = $row;

                if (count($batch) >= 500) {
                    $this->writeInsertBatch($handle, $table, $columns, $batch);
                    $rowCount += count($batch);
                    $batch = [];
                }
            }

            if ($batch) {
                $this->writeInsertBatch($handle, $table, $columns, $batch);
                $rowCount += count($batch);
            }
        }

        gzwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($handle);

        Storage::disk('local')->put($path, file_get_contents($tmpPath));
        @unlink($tmpPath);

        $this->pruneOldBackups();

        return [
            'filename' => $filename,
            'tables' => $tables->count(),
            'rows' => $rowCount,
            'size' => Storage::disk('local')->size($path),
            'duration_seconds' => round(microtime(true) - $startedAt, 2),
        ];
    }

    public function list(): Collection
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::DIRECTORY)) {
            return collect();
        }

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn ($path) => str_ends_with($path, '.sql.gz'))
            ->map(fn ($path) => [
                'filename' => basename($path),
                'path' => $path,
                'size' => $disk->size($path),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function delete(string $filename): bool
    {
        return Storage::disk('local')->delete($this->safePath($filename));
    }

    public function pathFor(string $filename): string
    {
        return $this->safePath($filename);
    }

    private function writeInsertBatch($handle, string $table, array $columns, array $rows): void
    {
        $columnList = implode('`, `', $columns);

        $valueRows = array_map(function ($row) use ($columns) {
            $values = array_map(function ($column) use ($row) {
                $value = $row[$column] ?? null;
                if ($value === null) {
                    return 'NULL';
                }
                if (is_int($value) || is_float($value)) {
                    return (string) $value;
                }

                return "'".str_replace(
                    ['\\', "'", "\n", "\r", "\0"],
                    ['\\\\', "\\'", '\\n', '\\r', '\\0'],
                    (string) $value
                )."'";
            }, $columns);

            return '('.implode(', ', $values).')';
        }, $rows);

        gzwrite($handle, "INSERT INTO `{$table}` (`{$columnList}`) VALUES\n".implode(",\n", $valueRows).";\n\n");
    }

    private function safePath(string $filename): string
    {
        $filename = basename($filename);
        if (! preg_match('/^backup-[0-9\-_]+-[A-Za-z0-9]+\.sql\.gz$/', $filename)) {
            abort(404);
        }

        return self::DIRECTORY.'/'.$filename;
    }

    private function pruneOldBackups(): void
    {
        $keep = (int) config('backup.keep_count', 60);
        $this->list()->slice($keep)->each(fn ($file) => Storage::disk('local')->delete($file['path']));
    }
}
