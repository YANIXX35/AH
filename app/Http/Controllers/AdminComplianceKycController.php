<?php

namespace App\Http\Controllers;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\AdminAuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminComplianceKycController extends Controller
{
    public function __construct(
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $query = User::query()
            ->clients()
            ->with(['kycDocuments' => fn ($q) => $q->latest('submitted_at')])
            ->whereIn('kyc_status', ['pending', 'submitted', 'approved', 'rejected']);

        if ($status !== '' && $status !== 'all') {
            $query->where('kyc_status', $status);
        }

        $users = $query->orderByRaw("FIELD(kyc_status,'submitted','pending','rejected','approved')")
            ->latest('kyc_submitted_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.compliance.kyc-index', [
            'users' => $users,
            'status' => $status,
            'stats' => [
                'submitted' => User::query()->clients()->where('kyc_status', 'submitted')->count(),
                'approved' => User::query()->clients()->where('kyc_status', 'approved')->count(),
                'rejected' => User::query()->clients()->where('kyc_status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        abort_if($user->isPlatformAdmin() || $user->isAccountant(), 404);
        $user->load(['kycDocuments.reviewedBy:id,name,email']);

        return view('admin.compliance.kyc-show', [
            'kycUser' => $user,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->isPlatformAdmin() || $user->isAccountant(), 403);

        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ]);

        foreach ((array) $data['documents'] as $file) {
            $path = $file->store('kyc-documents', 'public');
            KycDocument::query()->create([
                'user_id' => $user->id,
                'document_type' => 'kyb_supporting_document',
                'stored_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        }

        $before = $user->only(['kyc_status', 'kyc_submitted_at']);
        $user->update([
            'kyc_status' => 'submitted',
            'kyc_submitted_at' => now(),
            'kyc_rejection_reason' => null,
        ]);

        $this->auditTrail->log(
            'kyc.submit',
            User::class,
            $user->id,
            $user->id,
            $before,
            $user->fresh()?->only(['kyc_status', 'kyc_submitted_at']),
            ['documents_count' => count((array) $data['documents'])],
            $request
        );

        return back()->with('status', 'Dossier KYC/KYB soumis pour validation administrateur.');
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPlatformAdmin() || $user->isAccountant(), 404);
        $before = $user->only(['kyc_status', 'kyc_validated_at', 'kyc_validated_by_user_id', 'kyc_rejection_reason']);

        $user->update([
            'kyc_status' => 'approved',
            'kyc_validated_at' => now(),
            'kyc_validated_by_user_id' => $request->user()?->id,
            'kyc_rejection_reason' => null,
        ]);

        KycDocument::query()
            ->where('user_id', $user->id)
            ->update([
                'status' => 'approved',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->id,
                'review_note' => (string) $request->input('note', ''),
            ]);

        $this->auditTrail->log(
            'kyc.approve',
            User::class,
            $user->id,
            $request->user()?->id,
            $before,
            $user->fresh()?->only(['kyc_status', 'kyc_validated_at', 'kyc_validated_by_user_id']),
            ['note' => (string) $request->input('note', '')],
            $request
        );

        return back()->with('status', 'KYC/KYB validé avec succès.');
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPlatformAdmin() || $user->isAccountant(), 404);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $before = $user->only(['kyc_status', 'kyc_validated_at', 'kyc_validated_by_user_id', 'kyc_rejection_reason']);
        $user->update([
            'kyc_status' => 'rejected',
            'kyc_validated_at' => null,
            'kyc_validated_by_user_id' => null,
            'kyc_rejection_reason' => (string) $data['reason'],
        ]);

        KycDocument::query()
            ->where('user_id', $user->id)
            ->update([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $request->user()?->id,
                'review_note' => (string) $data['reason'],
            ]);

        $this->auditTrail->log(
            'kyc.reject',
            User::class,
            $user->id,
            $request->user()?->id,
            $before,
            $user->fresh()?->only(['kyc_status', 'kyc_rejection_reason']),
            ['reason' => (string) $data['reason']],
            $request
        );

        return back()->with('status', 'KYC/KYB rejeté. Motif enregistré.');
    }

    public function resubmit(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isPlatformAdmin() || $user->isAccountant(), 404);

        $before = $user->only(['kyc_status', 'kyc_submitted_at', 'kyc_validated_at', 'kyc_validated_by_user_id', 'kyc_rejection_reason']);
        $user->update([
            'kyc_status' => 'submitted',
            'kyc_submitted_at' => now(),
            'kyc_validated_at' => null,
            'kyc_validated_by_user_id' => null,
            'kyc_rejection_reason' => null,
        ]);

        KycDocument::query()
            ->where('user_id', $user->id)
            ->update([
                'status' => 'pending',
                'reviewed_at' => null,
                'reviewed_by_user_id' => null,
                'review_note' => null,
                'submitted_at' => now(),
            ]);

        $this->auditTrail->log(
            'kyc.resubmit',
            User::class,
            $user->id,
            $request->user()?->id,
            $before,
            $user->fresh()?->only(['kyc_status', 'kyc_submitted_at', 'kyc_validated_at', 'kyc_validated_by_user_id', 'kyc_rejection_reason']),
            ['trigger' => 'admin_manual'],
            $request
        );

        return back()->with('status', 'Dossier KYC/KYB resoumis pour validation.');
    }

    public function streamDocument(Request $request, KycDocument $document)
    {
        $admin = $request->user();
        abort_unless($admin?->isPlatformAdmin(), 403);
        abort_unless(Storage::disk('public')->exists($document->stored_path), 404);
        $absolutePath = Storage::disk('public')->path($document->stored_path);
        $safeName = str_replace('"', '', (string) ($document->original_name ?: basename($document->stored_path)));

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
        ]);
    }
}
