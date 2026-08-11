<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminDatabaseBackupController extends Controller
{
    public function index(DatabaseBackupService $service): View
    {
        return view('admin.backups.index', [
            'backups' => $service->list(),
            'keepCount' => (int) config('backup.keep_count', 60),
        ]);
    }

    public function run(DatabaseBackupService $service): RedirectResponse
    {
        try {
            $result = $service->run();

            return redirect()->route('admin.backups.index')
                ->with('status', "Sauvegarde créée : {$result['filename']} ({$result['tables']} tables, {$result['rows']} lignes).");
        } catch (\Throwable $e) {
            return redirect()->route('admin.backups.index')
                ->withErrors(['backup' => 'Échec de la sauvegarde : '.$e->getMessage()]);
        }
    }

    public function download(string $filename, DatabaseBackupService $service)
    {
        return Storage::disk('local')->download($service->pathFor($filename));
    }

    public function destroy(string $filename, DatabaseBackupService $service): RedirectResponse
    {
        $service->delete($filename);

        return redirect()->route('admin.backups.index')->with('status', 'Sauvegarde supprimée.');
    }
}
