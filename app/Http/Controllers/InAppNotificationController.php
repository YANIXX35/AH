<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InAppNotificationController extends Controller
{
    /**
     * Marque comme lue et redirige vers l’action (lien depuis la cloche).
     */
    public function go(AppNotification $notification)
    {
        $this->authorizeNotification($notification);
        $notification->markRead();

        return redirect()->to($notification->action_url ?: route('notifications.index'));
    }

    public function index()
    {
        $notifications = AppNotification::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function read(Request $request, AppNotification $notification)
    {
        $this->authorizeNotification($notification);
        $notification->markRead();

        if ($request->filled('redirect')) {
            return redirect()->to($request->get('redirect'));
        }

        return back()->with('status', 'Notification lue.');
    }

    public function readAll()
    {
        AppNotification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'Toutes les notifications sont marquées comme lues.');
    }

    private function authorizeNotification(AppNotification $notification): void
    {
        if ((int) $notification->user_id !== (int) Auth::id()) {
            abort(403);
        }
    }
}
