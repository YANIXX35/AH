<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\MenuActionLog;
use App\Models\TreasuryAuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AdminPlatformLogController extends Controller
{
    /**
     * Journalisation complète de la plateforme (menu, trésorerie, comptabilité) avec filtres.
     */
    public function index(Request $request): View
    {
        $module = (string) $request->query('module', 'all');
        $actorId = (int) $request->query('actor_user_id', 0);
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');
        $search = trim((string) $request->query('q', ''));

        $logs = $this->buildPlatformLogs();

        if ($module !== 'all') {
            $logs = $logs->where('module', $module)->values();
        }
        if ($actorId > 0) {
            $logs = $logs->where('actor_id', $actorId)->values();
        }
        if ($dateFrom !== '') {
            $from = Carbon::parse($dateFrom)->startOfDay();
            $logs = $logs->filter(fn ($row) => ($row['created_at'] ?? null) && $row['created_at']->gte($from))->values();
        }
        if ($dateTo !== '') {
            $to = Carbon::parse($dateTo)->endOfDay();
            $logs = $logs->filter(fn ($row) => ($row['created_at'] ?? null) && $row['created_at']->lte($to))->values();
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $logs = $logs->filter(function ($row) use ($needle) {
                $text = mb_strtolower(
                    ($row['actor_name'] ?? '').' '.
                    ($row['actor_email'] ?? '').' '.
                    ($row['action'] ?? '').' '.
                    ($row['detail'] ?? '')
                );

                return str_contains($text, $needle);
            })->values();
        }

        $actors = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.platform-logs', [
            'logs' => $logs->take(500),
            'actors' => $actors,
            'filters' => [
                'module' => $module,
                'actor_user_id' => $actorId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Assemble les sources de logs disponibles en une collection homogène.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildPlatformLogs(): Collection
    {
        $menuLogs = MenuActionLog::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->limit(500)
            ->get()
            ->map(function (MenuActionLog $row) {
                return [
                    'module' => 'menu',
                    'created_at' => $row->created_at,
                    'actor_id' => (int) $row->user_id,
                    'actor_name' => $row->user?->name ?? '—',
                    'actor_email' => $row->user?->email,
                    'action' => 'Navigation menu',
                    'detail' => strtoupper((string) $row->http_method).' '.$row->route_name.' · '.$row->path.' · HTTP '.$row->status_code,
                    'ip' => $row->ip,
                ];
            });

        $treasuryLabels = [
            'treasury.period.lock' => 'Verrouillage période',
            'treasury.period.unlock' => 'Déverrouillage période',
            'treasury.forecast.validate' => 'Validation prévision',
            'treasury.transaction.create' => 'Création transaction',
            'treasury.transaction.update' => 'Mise à jour transaction',
            'treasury.transaction.delete' => 'Suppression transaction',
            'treasury.transaction.stripe.cancel' => 'Annulation transaction Stripe',
            'treasury.mobile_money.personal_data_purged' => 'Purge données personnelles Mobile Money',
            'stock.product.deleted' => 'Suppression produit stock',
            'stock.product.archived' => 'Archivage produit stock (retiré)',
            'stock.product.updated' => 'Mise à jour produit stock',
            'stock.movement.recorded' => 'Mouvement de stock enregistré',
            'invoicing.invoice.created' => 'Création facture',
            'invoicing.invoice.updated' => 'Modification facture',
            'invoicing.invoice.deleted' => 'Suppression facture',
            'invoicing.invoice.cancelled' => 'Annulation facture',
            'invoicing.invoice.payment_recorded' => 'Encaissement facture',
            'accounting.entry.deleted' => 'Suppression écriture comptable',
            'accounting.document.deleted' => 'Suppression document comptable',
            'admin.license.revoked' => 'Révocation licence entreprise',
            'profile.team.member_removed' => 'Retrait d’un membre d’équipe',
        ];
        $treasuryLogs = TreasuryAuditLog::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->latest('created_at')
            ->limit(500)
            ->get()
            ->map(function (TreasuryAuditLog $row) use ($treasuryLabels) {
                $actor = $row->actor ?? $row->user;

                return [
                    'module' => 'treasury',
                    'created_at' => $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $treasuryLabels[$row->action] ?? $row->action,
                    'detail' => ! empty($row->properties)
                        ? (string) json_encode($row->properties, JSON_UNESCAPED_UNICODE)
                        : '—',
                    'ip' => $row->ip_address,
                ];
            });

        $entryLogs = AccountingEntry::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->latest('created_at')
            ->limit(500)
            ->get()
            ->map(function (AccountingEntry $row) {
                $actor = $row->actor ?? $row->user;
                $isUpdated = $row->updated_at !== null && $row->created_at !== null && $row->updated_at->gt($row->created_at);

                return [
                    'module' => 'accounting',
                    'created_at' => $isUpdated ? $row->updated_at : $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $isUpdated ? 'Mise à jour écriture' : 'Création écriture',
                    'detail' => ($row->document_type ?? 'Écriture').' · '.$row->description.' · '.$row->amount,
                    'ip' => null,
                ];
            });

        $documentLogs = AccountingDocument::query()
            ->with(['actor:id,name,email', 'user:id,name,email'])
            ->latest('created_at')
            ->limit(500)
            ->get()
            ->map(function (AccountingDocument $row) {
                $actor = $row->actor ?? $row->user;
                $isUpdated = $row->updated_at !== null && $row->created_at !== null && $row->updated_at->gt($row->created_at);

                return [
                    'module' => 'accounting',
                    'created_at' => $isUpdated ? $row->updated_at : $row->created_at,
                    'actor_id' => (int) ($row->actor_user_id ?? $row->user_id),
                    'actor_name' => $actor?->name ?? '—',
                    'actor_email' => $actor?->email,
                    'action' => $isUpdated ? 'Mise à jour document' : 'Import document',
                    'detail' => ($row->original_name ?? 'Document').' · statut '.$row->status,
                    'ip' => null,
                ];
            });

        $authActionLabels = [
            'otp_sent' => 'OTP envoyé',
            'otp_send_failed' => 'Échec envoi OTP',
            'otp_resend_blocked' => 'Renvoi OTP bloqué',
            'otp_requested_unknown_email' => 'Demande OTP e-mail inconnu',
            'otp_check_expired_or_missing' => 'OTP expiré ou absent',
            'otp_check_too_many_attempts' => 'OTP trop de tentatives',
            'otp_check_failed' => 'OTP incorrect',
            'otp_check_user_not_found' => 'Compte introuvable',
            'password_reset_confirmed_mail_sent' => 'E-mail confirmation envoyé',
            'password_reset_confirmed_mail_failed' => 'Échec e-mail confirmation',
            'password_reset_completed' => 'Mot de passe réinitialisé',
        ];
        $authLogs = collect();
        if (Schema::hasTable('auth_event_logs')) {
            $authLogs = DB::table('auth_event_logs as l')
                ->leftJoin('users as u', 'u.id', '=', 'l.actor_user_id')
                ->orderByDesc('l.created_at')
                ->limit(500)
                ->get([
                    'l.created_at',
                    'l.actor_user_id',
                    'u.name as actor_name',
                    'u.email as actor_email',
                    'l.email',
                    'l.event',
                    'l.detail',
                    'l.ip_address',
                ])
                ->map(function ($row) use ($authActionLabels) {
                    $actorName = $row->actor_name ?? '—';
                    $email = $row->email ?? null;
                    if ($actorName === '—' && $email !== null) {
                        $actorName = 'Compte invité';
                    }

                    return [
                        'module' => 'auth',
                        'created_at' => $row->created_at ? Carbon::parse((string) $row->created_at) : null,
                        'actor_id' => (int) ($row->actor_user_id ?? 0),
                        'actor_name' => $actorName,
                        'actor_email' => $row->actor_email ?? $email,
                        'action' => $authActionLabels[$row->event] ?? (string) $row->event,
                        'detail' => trim((string) (($email ? 'email='.$email : '').($row->detail ? ' · '.$row->detail : ''))) ?: '—',
                        'ip' => $row->ip_address,
                    ];
                });
        }

        return $menuLogs
            ->concat($treasuryLogs)
            ->concat($entryLogs)
            ->concat($documentLogs)
            ->concat($authLogs)
            ->sortByDesc(fn ($row) => $row['created_at']?->getTimestamp() ?? 0)
            ->values();
    }
}

