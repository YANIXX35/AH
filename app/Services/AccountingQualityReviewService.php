<?php

namespace App\Services;

use App\Models\AccountingEntry;

/**
 * Contrôle qualité périodique des données comptables (PRD 4.2). Revérifie une
 * écriture déjà validée — pas au moment de son import (voir OcrPipelineService,
 * qui couvre le PRD 4.1), mais après coup, sur les seules données persistées.
 * Aucun nouvel appel OCR n'est effectué ici : c'est une revérification de
 * complétude, pas une nouvelle extraction.
 */
class AccountingQualityReviewService
{
    /**
     * Réexamine une écriture et retourne son verdict sans la sauvegarder.
     *
     * @return array{status:string, issues:array<int,string>, compliance_rate:float}
     */
    public function evaluate(AccountingEntry $entry): array
    {
        $issues = [];

        if (trim((string) $entry->document_reference) === '') {
            $issues[] = 'reference_manquante';
        }

        if ((float) $entry->amount <= 0) {
            $issues[] = 'montant_invalide';
        }

        if (! $this->hasTiersIdentification($entry)) {
            $issues[] = 'identification_tiers_absente';
        }

        $checksCount = 3;
        $complianceRate = round((($checksCount - count($issues)) / $checksCount) * 100, 2);

        return [
            'status' => empty($issues) ? 'compliant' : 'non_compliant',
            'issues' => $issues,
            'compliance_rate' => $complianceRate,
        ];
    }

    /**
     * Réexamine une écriture et persiste le verdict.
     */
    public function reviewAndPersist(AccountingEntry $entry): array
    {
        $result = $this->evaluate($entry);

        $entry->forceFill([
            'quality_status' => $result['status'],
            'quality_reviewed_at' => now(),
            'quality_issues' => $result['issues'],
        ])->save();

        return $result;
    }

    /**
     * Identification du tiers : reprise des identifiants extraits par l'OCR à
     * l'import (NIF/IFU/RCCM) quand un document source est lié. Une écriture
     * saisie manuellement, sans document, n'a pas de source vérifiable — elle
     * est donc traitée comme non identifiée plutôt que supposée conforme.
     */
    private function hasTiersIdentification(AccountingEntry $entry): bool
    {
        $document = $entry->document;
        if ($document === null) {
            return false;
        }

        $identifiers = (array) (($document->extracted_data ?? [])['identifiers'] ?? []);
        $taxIds = (array) ($identifiers['tax_ids'] ?? []);
        $businessIds = (array) ($identifiers['business_ids'] ?? []);

        return ! empty($taxIds) || ! empty($businessIds);
    }
}
