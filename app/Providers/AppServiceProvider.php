<?php

namespace App\Providers;

use App\Console\Commands\WindowsServeCommand;
use App\Models\AppNotification;
use App\Models\SupportTicket;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->runningInConsole() && PHP_OS_FAMILY === 'Windows') {
            $this->app->extend(ServeCommand::class, function () {
                return new WindowsServeCommand();
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
