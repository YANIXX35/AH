<?php

namespace App\Providers;

use App\Console\Commands\WindowsServeCommand;
use App\Contracts\FinancialRatioServiceContract;
use App\Mail\AppNotificationMail;
use App\Models\AppNotification;
use App\Models\SupportTicket;
use App\Services\SmeFinancialRatioService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FinancialRatioServiceContract::class, SmeFinancialRatioService::class);

        if ($this->app->runningInConsole() && PHP_OS_FAMILY === 'Windows') {
            $this->app->extend(ServeCommand::class, function () {
                return new WindowsServeCommand;
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limite utf8mb4 pour les index uniques MySQL (varchar 255 → 191).
        Schema::defaultStringLength(191);

        AppNotification::created(function (AppNotification $notification): void {
            $notification->loadMissing('user');
            $recipient = $notification->user;

            if (! $recipient || ! $recipient->email) {
                return;
            }

            if (! (bool) ($recipient->email_notifications ?? false)) {
                return;
            }

            try {
                Mail::to($recipient->email)->queue(new AppNotificationMail($notification));
            } catch (\Throwable $exception) {
                Log::warning('notification_email_send_failed', [
                    'notification_id' => $notification->id,
                    'user_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        RateLimiter::for('auth-sensitive', function (Request $request) {
            return Limit::perMinute(8)->by($request->ip());
        });
        RateLimiter::for('ocr-intensive', function (Request $request) {
            $userId = Auth::id() ?: 'guest';

            return Limit::perMinute(10)->by($userId.'|'.$request->ip());
        });
        RateLimiter::for('investor-actions', function (Request $request) {
            $userId = Auth::id() ?: 'guest';

            return Limit::perMinute(12)->by($userId.'|'.$request->ip());
        });
        RateLimiter::for('finance-write', function (Request $request) {
            $userId = Auth::id() ?: 'guest';

            return Limit::perMinute(30)->by($userId.'|'.$request->ip());
        });
        RateLimiter::for('stripe-webhook', function (Request $request) {
            return Limit::perMinute(120)->by('stripe|'.$request->ip());
        });

        // Données cloche (notifications) et aperçu support pour le layout principal.
        View::composer('layouts.app', function ($view) {
            if (! Auth::check()) {
                return;
            }
            $userId = Auth::id();
            $view->with([
                'topbarNotifications' => AppNotification::where('user_id', $userId)->latest()->limit(8)->get(),
                'unreadNotificationsCount' => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
                'openSupportTicketsCount' => SupportTicket::where('user_id', $userId)
                    ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
                    ->count(),
                'topbarSupportTickets' => SupportTicket::where('user_id', $userId)
                    ->with('latestMessage')
                    ->latest('updated_at')
                    ->limit(5)
                    ->get(),
            ]);
        });
    }
}
