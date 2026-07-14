<?php

namespace App\Http\Controllers;

use App\Domain\Accounting\QualityControlService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\InvestmentRequest;
use App\Models\InvestorProfile;
use App\Services\InvestmentDossierChecklistService;
use App\Services\InvestorReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class InvestorController extends Controller
{
    use UsesClientWorkspace;

    private const ALLOWED_TRANSITIONS = [
        'pending' => ['in_review', 'declined'],
        'in_review' => ['accepted', 'declined'],
        'accepted' => [],
        'declined' => [],
    ];

    public function index(
        InvestorReadinessService $readiness,
        InvestmentDossierChecklistService $checklistService,
        QualityControlService $qualityControl
    ) {
        $userId = $this->workspaceUserId();
        $metrics = $readiness->build($userId);

        // PRD 4.2 — le score reste calculé et affiché normalement (choix volontairement
        // non bloquant, le moins disruptif : aucune PME n'a de revue qualité enregistrée
        // avant l'activation de cette fonctionnalité, un blocage dur couperait l'accès au
        // scoring pour tout le monde du jour au lendemain). Seul un avertissement est
        // ajouté si la période courante n'a pas été validée par un comptable/admin.
        $qualityPeriod = $qualityControl->currentPeriod();
        $qualityReview = $qualityControl->findForPeriod($userId, $qualityPeriod['start'], $qualityPeriod['end']);
        $qualityChecked = $qualityControl->isQualityCheckedForPeriod($userId, $qualityPeriod['start'], $qualityPeriod['end']);
        $checklist = $checklistService->build($userId, $metrics['breakdown']);
        $checklistSummary = $checklistService->summarize($checklist);
        $requests = InvestmentRequest::where('user_id', $userId)
            ->latest()
            ->paginate(10);

        $investorProfile = InvestorProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'risk_score' => $metrics['risk_score'],
                'performance_score' => $metrics['performance_score'],
                'profile_code' => $metrics['profile_code'],
                'profile_label' => $metrics['investor_profile'],
                'profile_detail' => $metrics['investor_profile_detail'],
                'classement_code' => data_get($metrics, 'breakdown.financial.classement.code'),
                'classement_libelle' => data_get($metrics, 'breakdown.financial.classement.libelle'),
                'operational_breakdown' => $metrics['breakdown'],
                'financial_snapshot' => data_get($metrics, 'breakdown.financial'),
                'computed_at' => now(),
            ]
        );

        return view('investor.index', [
            'metrics' => $metrics,
            'checklist' => $checklist,
            'checklistSummary' => $checklistSummary,
            'criticalChecklistCount' => collect($checklist)->where('status', InvestmentDossierChecklistService::STATUS_FAIL)->count(),
            'warningChecklistCount' => collect($checklist)->where('status', InvestmentDossierChecklistService::STATUS_WARNING)->count(),
            'requests' => $requests,
            'investorProfile' => $investorProfile,
            'qualityPeriod' => $qualityPeriod,
            'qualityReview' => $qualityReview,
            'qualityChecked' => $qualityChecked,
            'canReviewQuality' => (bool) (Auth::user()?->is_accountant || Auth::user()?->is_platform_admin),
        ]);
    }

    /**
     * Marquage manuel de la période courante (PRD 4.2, méthode provisoire) — réservé
     * aux comptables/admins, en attendant la méthode de contrôle définitive.
     */
    public function markQualityReview(Request $request, QualityControlService $qualityControl): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user && ((bool) $user->is_accountant || (bool) $user->is_platform_admin), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:validated,flagged'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $period = $qualityControl->currentPeriod();
        $qualityControl->markPeriodReviewed(
            $this->workspaceUserId(),
            $period['start'],
            $period['end'],
            $validated['status'],
            $user->id,
            $validated['notes'] ?? null
        );

        return back()->with('status', 'Contrôle qualité enregistré pour la période courante.');
    }

    public function storeRequest(
        Request $request,
        InvestorReadinessService $readiness,
        InvestmentDossierChecklistService $checklistService
    )
    {
        $userId = $this->workspaceUserId();
        $metrics = $readiness->build($userId);
        $checklist = $checklistService->build($userId, $metrics['breakdown']);
        $criticalCount = collect($checklist)
            ->where('status', InvestmentDossierChecklistService::STATUS_FAIL)
            ->count();

        // Blocage de conformité : on évite un dépôt tant que des points critiques persistent.
        if ($criticalCount > 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'dossier' => "Dépôt bloqué : {$criticalCount} point(s) critique(s) à corriger dans la checklist avant soumission.",
                ]);
        }

        $validated = $request->validate([
            'legal_representative' => ['required', 'string', 'max:255'],
            'amount_requested' => ['required', 'numeric', 'min:1'],
            'horizon' => ['nullable', 'string', 'max:32'],
            'fiscal_closing_at' => ['required', 'date', 'before_or_equal:today'],
            'revenue_n1' => ['required', 'numeric', 'min:0'],
            'equity_n1' => ['required', 'numeric'],
            'purpose' => ['required', 'string', 'min:120', 'max:5000'],
            'attachments_commitment' => ['required', 'string', 'min:30', 'max:2000'],
            'certifies_accuracy' => ['accepted'],
            'identity_document_type' => ['required', 'string', 'in:cni,passport,residence_permit,other'],
            'identity_document_number' => ['required', 'string', 'max:64'],
            'identity_document_expires_at' => ['nullable', 'date'],
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'identity_document_front' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
            'identity_document_back' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $batch = (string) Str::uuid();
        $baseDir = 'investment-requests/'.$userId.'/'.$batch;

        $photoPath = $request->file('photo')->store($baseDir, 'public');
        $frontPath = $request->file('identity_document_front')->store($baseDir, 'public');
        $backPath = $request->file('identity_document_back')->store($baseDir, 'public');

        InvestmentRequest::create([
            'user_id' => $userId,
            'amount_requested' => $validated['amount_requested'],
            'currency' => 'XOF',
            'horizon' => $validated['horizon'] ?? null,
            'purpose' => $validated['purpose'],
            'legal_representative' => $validated['legal_representative'],
            'fiscal_closing_at' => $validated['fiscal_closing_at'] ?? null,
            'revenue_n1' => $validated['revenue_n1'] ?? null,
            'equity_n1' => $validated['equity_n1'] ?? null,
            'attachments_commitment' => $validated['attachments_commitment'] ?? null,
            'certifies_accuracy' => true,
            'status' => 'pending',
            'photo_path' => $photoPath,
            'identity_document_front_path' => $frontPath,
            'identity_document_back_path' => $backPath,
            'identity_document_type' => $validated['identity_document_type'],
            'identity_document_number' => $validated['identity_document_number'],
            'identity_document_expires_at' => $validated['identity_document_expires_at'] ?? null,
        ]);

        return redirect()
            ->route('investor.readiness')
            ->with('status', 'Dépôt enregistré. La suite relève de l’analyse de conformité et de la diligence raisonnable (hors mission de certification des comptes).');
    }

    public function updateRequestWorkflow(Request $request, InvestmentRequest $investmentRequest)
    {
        if ((int) $investmentRequest->user_id !== (int) $this->workspaceUserId()) {
            abort(403, 'Action non autorisée sur cette demande.');
        }

        $validated = $request->validate([
            'next_status' => ['required', 'in:in_review,accepted,declined'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $current = (string) $investmentRequest->status;
        $next = (string) $validated['next_status'];
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (!in_array($next, $allowed, true)) {
            return back()->withErrors([
                'workflow' => "Transition invalide : {$current} -> {$next}.",
            ]);
        }

        $note = trim((string) ($validated['review_note'] ?? ''));
        if (in_array($next, ['accepted', 'declined'], true) && $note === '') {
            return back()
                ->withErrors(['workflow' => 'Une note d’analyse est obligatoire pour une décision finale.'])
                ->withInput();
        }

        $investmentRequest->update([
            'status' => $next,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $note !== '' ? $note : null,
        ]);

        return back()->with('status', 'Workflow mis à jour avec succès.');
    }
}
