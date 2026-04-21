<?php

namespace App\Services;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\PlanComptableAccount;
use App\Models\User;

/**
 * Contrôles de cohérence type « revue de dossier » avant dépôt investissement.
 */
class InvestmentDossierChecklistService
{
    public const STATUS_OK = 'ok';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAIL = 'fail';

    /**
     * @return list<array{key: string, label: string, status: string, detail: string, route: string|null}>
     */
    public function build(int $userId, array $metricsBreakdown): array
    {
        $user = User::find($userId);
        $items = [];

        $planLoaded = PlanComptableAccount::where('user_id', $userId)->exists();
        $items[] = [
            'key' => 'plan_comptable',
            'label' => 'Plan comptable personnalisé',
            'status' => $planLoaded ? self::STATUS_OK : self::STATUS_WARNING,
            'detail' => $planLoaded
                ? 'Un plan comptable est chargé pour votre entité.'
                : 'Aucun plan importé : les investisseurs attendent souvent un référentiel de comptes aligné sur votre activité.',
            'route' => $planLoaded ? 'accounting.plan' : 'accounting.plan',
        ];

        $entriesCount = AccountingEntry::where('user_id', $userId)->count();
        if ($entriesCount >= 15) {
            $st = self::STATUS_OK;
            $detail = "{$entriesCount} écritures en base : volume suffisant pour une première analyse.";
        } elseif ($entriesCount >= 5) {
            $st = self::STATUS_WARNING;
            $detail = "{$entriesCount} écritures : complétez l’historique (idéalement 12 mois glissants).";
        } else {
            $st = self::STATUS_FAIL;
            $detail = $entriesCount === 0
                ? 'Aucune écriture : impossible d’établir une photographie comptable fiable.'
                : "Seulement {$entriesCount} écriture(s) : le dossier sera systématiquement contesté.";
        }
        $items[] = [
            'key' => 'ecritures',
            'label' => 'Historique d’écritures',
            'status' => $st,
            'detail' => $detail,
            'route' => 'accounting',
        ];

        $ratio = $metricsBreakdown['ocr_verified_ratio'] ?? null;
        if ($entriesCount === 0) {
            $items[] = [
                'key' => 'ocr',
                'label' => 'Fiabilisation OCR / pièces',
                'status' => self::STATUS_WARNING,
                'detail' => 'Sans écriture, la traçabilité des pièces justificatives n’est pas démontrable.',
                'route' => 'accounting',
            ];
        } elseif ($ratio === null) {
            $items[] = [
                'key' => 'ocr',
                'label' => 'Fiabilisation OCR / pièces',
                'status' => self::STATUS_WARNING,
                'detail' => 'Ratio OCR non calculable.',
                'route' => 'accounting',
            ];
        } elseif ($ratio >= 70) {
            $items[] = [
                'key' => 'ocr',
                'label' => 'Fiabilisation OCR / pièces',
                'status' => self::STATUS_OK,
                'detail' => number_format($ratio, 1, ',', ' ').'% d’écritures avec validation OCR : bonne traçabilité.',
                'route' => 'accounting',
            ];
        } elseif ($ratio >= 45) {
            $items[] = [
                'key' => 'ocr',
                'label' => 'Fiabilisation OCR / pièces',
                'status' => self::STATUS_WARNING,
                'detail' => number_format($ratio, 1, ',', ' ').'% validés : renforcez les contrôles avant soumission.',
                'route' => 'accounting',
            ];
        } else {
            $items[] = [
                'key' => 'ocr',
                'label' => 'Fiabilisation OCR / pièces',
                'status' => self::STATUS_FAIL,
                'detail' => 'Taux de validation OCR faible : risque de questions sur la qualité des pièces.',
                'route' => 'accounting',
            ];
        }

        $docRatio = $metricsBreakdown['documents_pending_ratio'] ?? null;
        if ($docRatio === null || AccountingDocument::where('user_id', $userId)->count() === 0) {
            $items[] = [
                'key' => 'documents',
                'label' => 'Circuit documentaire',
                'status' => self::STATUS_WARNING,
                'detail' => 'Peu ou pas de documents indexés : prévoir les liasses et annexes hors outil.',
                'route' => 'accounting.documents',
            ];
        } elseif ($docRatio <= 25) {
            $items[] = [
                'key' => 'documents',
                'label' => 'Circuit documentaire',
                'status' => self::STATUS_OK,
                'detail' => 'File d’attente documentaire maîtrisée ('.number_format($docRatio, 1, ',', ' ').'% en attente).',
                'route' => 'accounting.documents',
            ];
        } elseif ($docRatio <= 55) {
            $items[] = [
                'key' => 'documents',
                'label' => 'Circuit documentaire',
                'status' => self::STATUS_WARNING,
                'detail' => number_format($docRatio, 1, ',', ' ').'% de documents en attente : traiter avant envoi.',
                'route' => 'accounting.documents',
            ];
        } else {
            $items[] = [
                'key' => 'documents',
                'label' => 'Circuit documentaire',
                'status' => self::STATUS_FAIL,
                'detail' => 'Trop de documents en attente : le dossier paraîtra incomplet.',
                'route' => 'accounting.documents',
            ];
        }

        $soldeProj = (float) ($metricsBreakdown['solde_projete'] ?? 0);
        if ($soldeProj < 0) {
            $items[] = [
                'key' => 'tresorerie',
                'label' => 'Trésorerie (vision projetée)',
                'status' => self::STATUS_FAIL,
                'detail' => 'Solde projeté négatif : à expliquer explicitement (plan de trésorerie, ressources).',
                'route' => 'treasury.forecast',
            ];
        } elseif ($soldeProj < (float) ($metricsBreakdown['solde_actuel'] ?? 0) * 0.5 && ($metricsBreakdown['solde_actuel'] ?? 0) > 0) {
            $items[] = [
                'key' => 'tresorerie',
                'label' => 'Trésorerie (vision projetée)',
                'status' => self::STATUS_WARNING,
                'detail' => 'Dégradation marquée du solde projeté : documenter les mesures correctrices.',
                'route' => 'treasury.forecast',
            ];
        } else {
            $items[] = [
                'key' => 'tresorerie',
                'label' => 'Trésorerie (vision projetée)',
                'status' => self::STATUS_OK,
                'detail' => 'Pas de signal critique immédiat sur le solde projeté.',
                'route' => 'treasury.forecast',
            ];
        }

        $reliability = (float) ($metricsBreakdown['forecast_reliability'] ?? 0);
        if ($reliability >= 65) {
            $st = self::STATUS_OK;
            $detail = 'Fiabilité des prévisions trésorerie : '.number_format($reliability, 1, ',', ' ').' %.';
        } elseif ($reliability >= 45) {
            $st = self::STATUS_WARNING;
            $detail = 'Écarts planifié / réalisé : renforcer la discipline de prévision.';
        } else {
            $st = self::STATUS_FAIL;
            $detail = 'Prévisions peu fiables : un investisseur exigera un plan de trésorerie recalibré.';
        }
        $items[] = [
            'key' => 'previsions',
            'label' => 'Cohérence des prévisions',
            'status' => $st,
            'detail' => $detail,
            'route' => 'treasury.forecast',
        ];

        $companyOk = $user && (filled($user->company_name) || filled($user->company_designation));
        $items[] = [
            'key' => 'identite',
            'label' => 'Identité de l’entité (profil / FIRD)',
            'status' => $companyOk ? self::STATUS_OK : self::STATUS_WARNING,
            'detail' => $companyOk
                ? 'Raison sociale ou désignation renseignée : cohérente pour l’entête du dossier.'
                : 'Complétez la fiche entreprise (FIRD) : dénomination, fiscalité et registres.',
            'route' => 'profile.company.fird',
        ];

        return $items;
    }

    /**
     * Synthèse pour le bandeau (ex. « dossier exploitable » ou « à compléter »).
     */
    public function summarize(array $items): string
    {
        $fail = collect($items)->where('status', self::STATUS_FAIL)->count();
        $warn = collect($items)->where('status', self::STATUS_WARNING)->count();

        if ($fail > 0) {
            return "Points bloquants : {$fail} — le dossier nécessite des compléments avant une présentation sérieuse aux financeurs.";
        }
        if ($warn > 0) {
            return "Dossier exploitable avec réserves : {$warn} point(s) à renforcer (voir checklist).";
        }

        return 'Les contrôles automatiques sont au vert : finalisez les pièces annexes et la note d’investissement.';
    }
}
