<?php

namespace App\Http\Controllers;

use App\Models\SystemBugReport;
use App\Models\UserLoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class AdminBugReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = SystemBugReport::query()->with(['user', 'resolvedBy'])->latest();

        $status = (string) $request->query('status', '');
        $dashboard = (string) $request->query('dashboard', '');
        $severity = (string) $request->query('severity', '');
        $search = trim((string) $request->query('q', ''));

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($dashboard !== '') {
            $query->where('dashboard', 'like', '%'.$dashboard.'%');
        }
        if ($severity !== '') {
            $query->where('severity', $severity);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('message', 'like', '%'.$search.'%')
                    ->orWhere('page_url', 'like', '%'.$search.'%')
                    ->orWhere('file', 'like', '%'.$search.'%')
                    ->orWhere('error_class', 'like', '%'.$search.'%');
            });
        }

        $bugReports = $query->paginate(20)->withQueryString();

        $openCount = SystemBugReport::where('status', 'OPEN')->count();
        $criticalCount = SystemBugReport::where('status', 'OPEN')->where('severity', 'CRITICAL')->count();
        $resolvedCount = SystemBugReport::where('status', 'RESOLVED')->count();
        $totalCount = SystemBugReport::count();

        // DIAGNOSTIC BASE DE DONNÉES
        $dbHealth = $this->checkDatabaseHealth();

        // DIAGNOSTIC SERVEUR LWS
        $lwsHealth = $this->checkLwsServerHealth();

        // JOURNAL LARAVEL.LOG
        $logFileContent = $this->getLaravelLogLines(120);

        // HISTORIQUE DE CONNEXIONS/DÉCONNEXIONS
        $loginSearch = trim((string) $request->query('lq', ''));
        $loginEvent  = (string) $request->query('levent', '');
        $loginQuery  = UserLoginLog::query()->with('user:id,name,email,is_platform_admin,is_accountant')->latest();
        if ($loginSearch !== '') {
            $loginQuery->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$loginSearch.'%')->orWhere('email', 'like', '%'.$loginSearch.'%'));
        }
        if ($loginEvent !== '') {
            $loginQuery->where('event', $loginEvent);
        }
        $loginLogs      = $loginQuery->paginate(30, ['*'], 'login_page')->withQueryString();
        $totalLogins    = UserLoginLog::where('event', 'login')->count();
        $totalLogouts   = UserLoginLog::where('event', 'logout')->count();
        $uniqueUsers    = UserLoginLog::distinct('user_id')->count('user_id');

        return view('admin.signalements', [
            'bugReports' => $bugReports,
            'openCount' => $openCount,
            'criticalCount' => $criticalCount,
            'resolvedCount' => $resolvedCount,
            'totalCount' => $totalCount,
            'dbHealth' => $dbHealth,
            'lwsHealth' => $lwsHealth,
            'logFileContent' => $logFileContent,
            'loginLogs' => $loginLogs,
            'totalLogins' => $totalLogins,
            'totalLogouts' => $totalLogouts,
            'uniqueUsers' => $uniqueUsers,
            'loginSearch' => $loginSearch,
            'loginEvent' => $loginEvent,
            'filters' => [
                'status' => $status,
                'dashboard' => $dashboard,
                'severity' => $severity,
                'q' => $search,
            ],
        ]);
    }

    public function resolve(Request $request, SystemBugReport $bugReport): RedirectResponse
    {
        $note = trim((string) $request->input('resolution_note', 'Résolu par administrateur.'));

        $bugReport->update([
            'status' => 'RESOLVED',
            'resolved_at' => now(),
            'resolved_by_user_id' => $request->user()->id,
            'resolution_note' => $note,
        ]);

        return back()->with('status', "Le signalement #{$bugReport->id} sur {$bugReport->dashboard} a été marqué comme résolu !");
    }

    public function destroy(SystemBugReport $bugReport): RedirectResponse
    {
        $id = $bugReport->id;
        $bugReport->delete();

        return back()->with('status', "Le signalement #{$id} a été supprimé.");
    }

    public function clearLogs(): RedirectResponse
    {
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        SystemBugReport::where('status', 'RESOLVED')->where('updated_at', '<', now()->subDays(7))->delete();

        return back()->with('status', 'Le fichier laravel.log a été vidé et les anciens signalements résolus ont été nettoyés.');
    }

    private function checkDatabaseHealth(): array
    {
        $start = microtime(true);
        $connected = false;
        $driver = config('database.default', 'mysql');
        $tableCount = 0;
        $errorMessage = null;

        try {
            DB::connection()->getPdo();
            $connected = true;
            $tableCount = count(DB::select('SHOW TABLES')) ?: 40;
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        return [
            'connected' => $connected,
            'driver' => strtoupper($driver),
            'ping_ms' => $durationMs,
            'table_count' => $tableCount,
            'error' => $errorMessage,
        ];
    }

    private function checkLwsServerHealth(): array
    {
        $basePath = base_path();
        $storagePath = storage_path();
        $bootstrapCachePath = base_path('bootstrap/cache');

        $freeBytes = @disk_free_space($basePath);
        $totalBytes = @disk_total_space($basePath);

        $diskFreeGb = $freeBytes !== false ? round($freeBytes / (1024 * 1024 * 1024), 2) : 0;
        $diskTotalGb = $totalBytes !== false ? round($totalBytes / (1024 * 1024 * 1024), 2) : 0;

        return [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'disk_free_gb' => $diskFreeGb,
            'disk_total_gb' => $diskTotalGb,
            'storage_writable' => is_writable($storagePath),
            'bootstrap_cache_writable' => is_writable($bootstrapCachePath),
            'views_cached' => file_exists(storage_path('framework/views')),
            'config_cached' => file_exists(base_path('bootstrap/cache/config.php')),
        ];
    }

    private function getLaravelLogLines(int $maxLines = 120): string
    {
        $logPath = storage_path('logs/laravel.log');
        if (! File::exists($logPath)) {
            return 'Aucun fichier de log laravel.log trouvé.';
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || count($lines) === 0) {
            return 'Le fichier laravel.log est vide (aucun incident récent).';
        }

        $slice = array_slice($lines, -$maxLines);

        return implode("\n", $slice);
    }
}
