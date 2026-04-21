<?php

namespace App\Http\Controllers;

use App\Models\MenuActionLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MenuActivityLogController extends Controller
{
    /**
     * Liste le journal des actions « menu » : compte courant, ou tout le monde si admin plateforme.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $query = MenuActionLog::query()
            ->with(['user:id,name,email'])
            ->latest('created_at');

        if (! ($user->is_platform_admin ?? false)) {
            $query->where('user_id', $user->id);
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('activity.menu-log', [
            'logs' => $logs,
            'showUserColumn' => (bool) ($user->is_platform_admin ?? false),
        ]);
    }
}
