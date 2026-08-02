<?php

namespace App\Http\Middleware;

use App\Models\SystemBugReport;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CaptureSystemErrors
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $response = $next($request);

            if ($response->getStatusCode() >= 500) {
                $this->logReport(
                    $request,
                    'HttpException'.$response->getStatusCode(),
                    'Erreur HTTP '.$response->getStatusCode().' rencontrée sur la page.',
                    null,
                    null,
                    null,
                    'HIGH'
                );
            }

            return $response;
        } catch (Throwable $e) {
            $this->logReport(
                $request,
                get_class($e),
                $e->getMessage() ?: 'Une erreur système inattendue est survenue.',
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString(),
                'CRITICAL'
            );

            throw $e;
        }
    }

    private function logReport(
        Request $request,
        string $errorClass,
        string $message,
        ?string $file = null,
        ?int $line = null,
        ?string $stackTrace = null,
        string $severity = 'HIGH'
    ): void {
        try {
            $user = $request->user();
            $dashboard = SystemBugReport::resolveDashboardName($user, $request);
            $routeName = $request->route() ? $request->route()->getName() : null;

            SystemBugReport::create([
                'user_id' => $user?->id,
                'dashboard' => $dashboard,
                'page_url' => $request->fullUrl(),
                'route_name' => $routeName,
                'error_class' => $errorClass,
                'message' => $message,
                'file' => $file ? relative_path_clean($file) : null,
                'line' => $line,
                'stack_trace' => $stackTrace ? mb_substr($stackTrace, 0, 5000) : null,
                'severity' => $severity,
                'status' => 'OPEN',
            ]);
        } catch (Throwable $logError) {
            Log::error('Impossible d\'enregistrer le signalement de bug: '.$logError->getMessage());
        }
    }
}

if (! function_exists('relative_path_clean')) {
    function relative_path_clean(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return ltrim(substr($path, strlen($base)), '/\\');
        }

        return $path;
    }
}
