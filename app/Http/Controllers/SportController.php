<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\Invoice;
use App\Models\SportEvent;
use App\Services\ErpNextClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SportController extends Controller
{
    use UsesClientWorkspace;

    public function index(): View
    {
        return view('sport.index');
    }

    public function members(ErpNextClient $erpNext): View
    {
        $pme = $this->workspaceUser();

        $members = [];
        $error = null;

        if (empty($pme->erpnext_company_name)) {
            $error = 'Cette PME n\'est pas provisionnée sur ERPNext.';
        } else {
            try {
                $members = $erpNext->listSportMembers($pme);
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return view('sport.members', ['members' => $members, 'error' => $error]);
    }

    public function storeMember(Request $request, ErpNextClient $erpNext): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $pme = $this->workspaceUser();

        try {
            $erpNext->createSportMember($pme, $validated['name'], $validated['mobile'] ?? null, $validated['email'] ?? null);
        } catch (\Throwable $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()->route('sport.members')->with('success', 'Membre créé.');
    }

    public function cotisations(): View
    {
        $userIds = $this->workspaceDataUserIds();

        $cotisations = Invoice::whereIn('user_id', $userIds)
            ->whereNotNull('erpnext_subscription')
            ->orderByDesc('issue_date')
            ->get();

        return view('sport.cotisations', ['cotisations' => $cotisations]);
    }

    public function events(): View
    {
        $userIds = $this->workspaceDataUserIds();

        $events = SportEvent::whereIn('user_id', $userIds)
            ->orderBy('starts_on')
            ->get();

        return view('sport.events', ['events' => $events]);
    }

    private function workspaceUser(): \App\Models\User
    {
        return \App\Models\User::findOrFail($this->workspaceUserId());
    }
}
