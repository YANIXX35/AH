<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminAuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRbacController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    public function index(): View
    {
        return view('admin.rbac.index', [
            'users' => User::query()
                ->where('is_platform_admin', false)
                ->orderBy('name')
                ->paginate(30),
            'availableRoles' => [
                'manager' => 'Manager',
                'accountant' => 'Comptable',
                'analyst' => 'Analyste',
                'viewer' => 'Lecture seule',
            ],
            'modules' => ['dashboard', 'accounting', 'treasury', 'investor', 'payments', 'support'],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role_key' => ['required', 'in:manager,accountant,analyst,viewer'],
            'modules' => ['nullable', 'array'],
        ]);

        $before = $user->only(['role_key', 'module_permissions', 'is_accountant']);
        $modulePermissions = [];
        foreach (['dashboard', 'accounting', 'treasury', 'investor', 'payments', 'support'] as $module) {
            $modulePermissions[$module] = in_array($module, (array) ($data['modules'] ?? []), true);
        }

        $user->update([
            'role_key' => (string) $data['role_key'],
            'module_permissions' => $modulePermissions,
            'is_accountant' => $data['role_key'] === 'accountant',
        ]);

        $this->auditTrail->log(
            'rbac.role.update',
            User::class,
            $user->id,
            $request->user()?->id,
            $before,
            $user->fresh()?->only(['role_key', 'module_permissions', 'is_accountant']),
            null,
            $request
        );

        return back()->with('status', 'Rôle et permissions mis à jour.');
    }
}
