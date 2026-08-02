<?php

namespace App\Http\Controllers;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\User;
use App\Services\Scoring360Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Analyse financière automatique (ratios PME) — réservé aux administrateurs plateforme.
 */
class AdminFinancialAnalysisController extends Controller
{
    public function __construct(
        private FinancialRatioServiceContract $ratioService,
        private Scoring360Service $scoring360
    ) {}

    public function index(Request $request): View
    {
        $users = User::query()
            ->orderBy('company_name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'company_name']);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $selectedId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        if ($selectedId === 0) {
            $selectedId = null;
        }

        $dateFrom = ! empty($validated['date_from'] ?? null)
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : null;
        $dateTo = ! empty($validated['date_to'] ?? null)
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : null;

        $analysis = null;
        $selectedUser = null;
        $scoring360 = null;

        if ($selectedId !== null) {
            $selectedUser = User::query()->find($selectedId);
            if ($selectedUser !== null) {
                $analysis = $this->ratioService->analyze($selectedId, $dateFrom, $dateTo);
                try {
                    $scoring360 = $this->scoring360->scoreUser($selectedId, $dateFrom, $dateTo);
                } catch (\Throwable) {
                    $scoring360 = null;
                }
            }
        }

        return view('admin.financial-analysis', [
            'users' => $users,
            'selectedUserId' => $selectedId,
            'selectedUser' => $selectedUser,
            'dateFrom' => $request->input('date_from'),
            'dateTo' => $request->input('date_to'),
            'analysis' => $analysis,
            'scoring360' => $scoring360,
        ]);
    }

    /**
     * Classement automatique des entreprises (solvable / finançable) sur une période.
     */
    public function ranking(Request $request): View
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $dateFrom = ! empty($validated['date_from'] ?? null)
            ? Carbon::parse($validated['date_from'])->startOfDay()
            : null;
        $dateTo = ! empty($validated['date_to'] ?? null)
            ? Carbon::parse($validated['date_to'])->endOfDay()
            : null;

        $lignes = $this->ratioService->classementPlateforme($dateFrom, $dateTo);

        $compteurs = [
            'financable' => 0,
            'solvable_seulement' => 0,
            'non_retenu' => 0,
            'insuffisant' => 0,
        ];
        foreach ($lignes as $row) {
            $code = $row['classement']['code'] ?? 'insuffisant';
            if (isset($compteurs[$code])) {
                $compteurs[$code]++;
            }
        }

        return view('admin.financial-ranking', [
            'lignes' => $lignes,
            'compteurs' => $compteurs,
            'dateFrom' => $request->input('date_from'),
            'dateTo' => $request->input('date_to'),
        ]);
    }
}
