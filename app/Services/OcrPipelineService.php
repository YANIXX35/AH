<?php

namespace App\Services;

class OcrPipelineService
{
    /**
     * Lance le pipeline OCR complet sur un document stocké.
     */
    public function processStoredDocument(string $storedPath): array
    {
        $ocrService = new OcrService();
        $ocrResult = $ocrService->extractText($storedPath);

        if (! ($ocrResult['success'] ?? false)) {
            return [
                'success' => false,
                'ocr_result' => $ocrResult,
                'rich_data' => [],
                'field_confidence' => [],
                'low_confidence_fields' => [],
                'global_confidence' => 0.0,
                'review_required' => true,
            ];
        }

        $text = (string) ($ocrResult['text'] ?? '');
        $richData = $ocrService->extractRichDocumentData($text);
        $fieldConfidence = $this->computeFieldConfidence($richData, $text);
        $lowConfidenceFields = $this->resolveLowConfidenceFields($fieldConfidence);
        $globalConfidence = $this->computeGlobalConfidence((float) ($ocrResult['confidence'] ?? 0), $fieldConfidence);

        return [
            'success' => true,
            'ocr_result' => $ocrResult,
            'rich_data' => $richData,
            'field_confidence' => $fieldConfidence,
            'low_confidence_fields' => $lowConfidenceFields,
            'global_confidence' => $globalConfidence,
            'review_required' => $globalConfidence < 70 || !empty($lowConfidenceFields),
        ];
    }

    /**
     * Calcule une confiance par champ métier critique.
     */
    private function computeFieldConfidence(array $richData, string $ocrText): array
    {
        $primary = (array) ($richData['primary'] ?? []);
        $scores = [];

        $scores['invoice_number'] = $this->scoreField(
            (string) ($primary['invoice_number'] ?? ''),
            '/[A-Z0-9][A-Z0-9\-\/_.]{2,}/u'
        );
        $scores['invoice_date'] = $this->scoreField(
            (string) ($primary['invoice_date'] ?? ''),
            '/^\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}$|^\d{4}-\d{2}-\d{2}$/u'
        );
        $scores['partner_name'] = $this->scoreField((string) ($primary['partner_name'] ?? ''), '/.{3,}/u');
        $scores['amount_ht'] = $this->scoreNumericField($primary['amount_ht'] ?? null);
        $scores['amount_tva'] = $this->scoreNumericField($primary['amount_tva'] ?? null);
        $scores['amount_ttc'] = $this->scoreNumericField($primary['amount_ttc'] ?? null);
        $scores['currency'] = $this->scoreField(
            (string) ($primary['currency'] ?? ''),
            '/^(FCFA|XOF|XAF|EUR|USD|GBP)$/iu'
        );

        // Bonus de cohérence TTC = HT + TVA si les 3 montants existent.
        $ht = is_numeric($primary['amount_ht'] ?? null) ? (float) $primary['amount_ht'] : null;
        $tva = is_numeric($primary['amount_tva'] ?? null) ? (float) $primary['amount_tva'] : null;
        $ttc = is_numeric($primary['amount_ttc'] ?? null) ? (float) $primary['amount_ttc'] : null;
        if ($ht !== null && $tva !== null && $ttc !== null) {
            $delta = abs(($ht + $tva) - $ttc);
            $scores['amount_consistency'] = $delta <= 1 ? 95.0 : ($delta <= 5 ? 75.0 : 45.0);
        } else {
            $scores['amount_consistency'] = 35.0;
        }

        // Bonus léger si le texte OCR contient les mots-clés documentaires.
        $lower = mb_strtolower($ocrText);
        $scores['document_keywords'] = (
            (str_contains($lower, 'facture') ? 20 : 0)
            + (str_contains($lower, 'total') ? 20 : 0)
            + (str_contains($lower, 'tva') ? 20 : 0)
        );
        $scores['document_keywords'] = min(100.0, (float) $scores['document_keywords']);

        return $scores;
    }

    /**
     * Retourne la liste des champs à faible confiance.
     */
    private function resolveLowConfidenceFields(array $scores): array
    {
        $criticalFields = ['invoice_number', 'invoice_date', 'partner_name', 'amount_ht', 'amount_ttc', 'currency'];
        $low = [];
        foreach ($criticalFields as $field) {
            $score = (float) ($scores[$field] ?? 0);
            if ($score < 60) {
                $low[] = $field;
            }
        }

        return $low;
    }

    /**
     * Agrège confiance moteur + confiance champs.
     */
    private function computeGlobalConfidence(float $engineConfidence, array $scores): float
    {
        if (empty($scores)) {
            return max(0.0, min(100.0, $engineConfidence));
        }

        $avgField = array_sum(array_map('floatval', $scores)) / count($scores);
        $blended = ($engineConfidence * 0.45) + ($avgField * 0.55);

        return round(max(0.0, min(100.0, $blended)), 2);
    }

    /**
     * Score un champ texte sur présence + format.
     */
    private function scoreField(string $value, string $pattern): float
    {
        $value = trim($value);
        if ($value === '') {
            return 25.0;
        }

        if (preg_match($pattern, $value)) {
            return 90.0;
        }

        return 60.0;
    }

    /**
     * Score un champ numérique.
     */
    private function scoreNumericField(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 25.0;
        }

        $number = (float) $value;
        if ($number <= 0) {
            return 45.0;
        }

        return 90.0;
    }
}
