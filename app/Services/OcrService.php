<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;

class OcrService
{
    private const OCR_SPACE_API = 'https://api.ocr.space/parse/image';

    /**
     * Extraire le texte du document uploadé via OCR (API OCR.space)
     */
    public function extractText($filePath)
    {
        try {
            $fullPath = storage_path('app/public/' . $filePath);
            
            if (!file_exists($fullPath)) {
                return [
                    'success' => false,
                    'message' => 'Fichier non trouvé',
                    'text' => '',
                    'error_code' => 'FILE_NOT_FOUND',
                    'error_location' => $fullPath,
                    'endpoint' => self::OCR_SPACE_API,
                ];
            }

            // Déterminer le type MIME
            $mimeType = mime_content_type($fullPath);
            
            // Traitement natif des fichiers Excel/CSV (sans appel API OCR).
            $excelMimes = [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                'application/vnd.ms-excel', // xls
                'text/csv', // csv
                'application/csv',
                'text/plain', // certains csv sont détectés en text/plain
            ];
            if (in_array($mimeType, $excelMimes, true)) {
                $excelText = $this->extractTextFromSpreadsheet($fullPath);

                if ($excelText === '') {
                    return [
                        'success' => false,
                        'message' => 'Fichier Excel lisible mais sans contenu exploitable.',
                        'text' => '',
                        'error_code' => 'EXCEL_EMPTY_CONTENT',
                        'error_location' => $fullPath,
                        'endpoint' => 'local_spreadsheet_parser',
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Texte extrait depuis fichier Excel (mode local).',
                    'text' => $excelText,
                    'confidence' => 100,
                    'endpoint' => 'local_spreadsheet_parser',
                ];
            }

            // Accepter les images et PDFs via API OCR.
            $supportedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            if (!in_array($mimeType, $supportedMimes, true)) {
                return [
                    'success' => false,
                    'message' => 'Format non supporté. Utilisez: JPG, PNG, PDF, XLS, XLSX ou CSV',
                    'text' => '',
                    'error_code' => 'UNSUPPORTED_MIME',
                    'error_location' => $mimeType,
                    'endpoint' => self::OCR_SPACE_API,
                ];
            }

            // Appeler l'API OCR.space
            $response = Http::timeout(60)
                ->attach('filename', fopen($fullPath, 'r'), basename($fullPath))
                ->post(self::OCR_SPACE_API, [
                    'language' => 'fre', // Français
                    'apikey' => 'K87899142C88957', // Clé gratuite OCR.space
                ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Erreur API OCR: ' . $response->status(),
                    'text' => '',
                    'error_code' => 'OCR_API_HTTP_ERROR',
                    'http_status' => $response->status(),
                    'error_location' => 'OCR.space endpoint',
                    'endpoint' => self::OCR_SPACE_API,
                ];
            }

            $result = $response->json();
            $parsedText = trim((string) ($result['ParsedText'] ?? data_get($result, 'ParsedResults.0.ParsedText', '')));

            if ($parsedText === '') {
                $errorMessage = data_get($result, 'ErrorMessage.0')
                    ?? data_get($result, 'ErrorMessage')
                    ?? 'Aucun texte détecté dans le document';

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'text' => '',
                    'error_code' => 'OCR_EMPTY_TEXT',
                    'error_location' => 'OCR response payload',
                    'endpoint' => self::OCR_SPACE_API,
                ];
            }

            return [
                'success' => true,
                'message' => 'OCR réalisé avec succès (OCR.space)',
                'text' => $parsedText,
                'confidence' => $result['Confidence'] ?? 0,
                'endpoint' => self::OCR_SPACE_API,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur OCR: ' . $e->getMessage(),
                'text' => '',
                'error_code' => 'OCR_EXCEPTION',
                'error_location' => 'HTTP request to OCR API',
                'endpoint' => self::OCR_SPACE_API,
            ];
        }
    }

    /**
     * Extrait un texte brut depuis un tableur Excel/CSV.
     */
    private function extractTextFromSpreadsheet(string $path): string
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $lines = [];
            foreach ($rows as $row) {
                $values = array_map(
                    fn ($value) => trim((string) $value),
                    array_values($row)
                );
                $values = array_values(array_filter($values, fn ($value) => $value !== ''));

                if (!empty($values)) {
                    $lines[] = implode(' | ', $values);
                }
            }

            return trim(implode("\n", $lines));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Vérifier si le montant du formulaire correspond au fichier
     */
    public function verifyAmount($ocrText, $formAmount)
    {
        // Chercher les patterns de montants (ex: "TOTAL: 12345.67 FCFA")
        $patterns = [
            '/(?:TOTAL|Total|montant|Montant|HT|HTH)[\s:]*(\d+[.,]\d{2})/i',
            '/(\d+[.,]\d{2})\s*(?:FCFA|fcfa|€|EUR)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $ocrText, $matches)) {
                foreach ($matches[1] as $amount) {
                    // Normaliser les montants (remplacer , par .)
                    $amount = str_replace(',', '.', $amount);
                    
                    // Vérifier si proche du montant du formulaire (tolérance ±5%)
                    if ($this->amountsMatch($amount, $formAmount)) {
                        return [
                            'verified' => true,
                            'detected_amount' => (float)$amount,
                            'message' => 'Montant vérifié ✓'
                        ];
                    }
                }
            }
        }

        return [
            'verified' => false,
            'detected_amount' => null,
            'message' => 'Montant non trouvé ou ne correspond pas'
        ];
    }

    /**
     * Vérifier si deux montants correspondent (tolérance ±5%)
     */
    private function amountsMatch($detected, $form)
    {
        $form = (float)$form;
        $detected = (float)$detected;
        
        if ($form == 0) return false;
        
        $tolerance = $form * 0.05; // 5%
        
        return abs($detected - $form) <= $tolerance;
    }

    /**
     * Extrait un socle d'informations structurées depuis le texte OCR.
     */
    private function extractDocumentInfo($ocrText): array
    {
        $invoiceNumber = $this->extractInvoiceNumber($ocrText);
        $dates = $this->extractDates($ocrText);
        $currency = $this->detectCurrency($ocrText);
        $fxRate = $this->getCurrencyToFcfaRate($currency);
        $amountHT = $this->extractAmount($ocrText, 'HT|Montant|Sous[- ]?total');
        $amountTVA = $this->extractAmount($ocrText, 'TVA|Tax');
        $amountTTC = $this->extractAmount($ocrText, 'TTC|Total|Net a payer|Net à payer|Somme');
        $tvaRate = $this->extractTVARate($ocrText);
        $partners = $this->extractPartnerNames($ocrText);

        return [
            'invoice_number' => $invoiceNumber ?: null,
            'date' => $dates[0] ?? null,
            'currency' => $currency,
            'fx_rate' => $fxRate,
            'amount_ht' => $amountHT,
            'amount_ht_fcfa' => $this->convertAmountToFcfa($amountHT, $currency),
            'amount_tva' => $amountTVA,
            'amount_tva_fcfa' => $this->convertAmountToFcfa($amountTVA, $currency),
            'amount_ttc' => $amountTTC,
            'amount_ttc_fcfa' => $this->convertAmountToFcfa($amountTTC, $currency),
            'tva_rate' => $tvaRate,
            'partner_name' => $partners[0] ?? null,
        ];
    }

    /**
     * Vérifier les informations COMPLÈTES du document
     */
    public function verifyCompleteDocument($ocrText, $formData)
    {
        $extractedInfo = $this->extractDocumentInfo($ocrText);
        $verification = [
            'verified_fields' => 0,
            'total_fields' => 0,
            'details' => [],
            'alerts' => [],
            'extracted' => $extractedInfo,
            'overall_status' => 'verified'
        ];

        if (($extractedInfo['currency'] ?? 'FCFA') !== 'FCFA') {
            $verification['details']['currency_conversion'] = sprintf(
                'ℹ️ Conversion automatique appliquée: 1 %s = %s FCFA',
                $extractedInfo['currency'],
                number_format((float) ($extractedInfo['fx_rate'] ?? 1), 3, ',', ' ')
            );
        }

        // Vérifier le montant HT
        $verification['total_fields']++;
        if (isset($extractedInfo['amount_ht']) && isset($formData['amount_ht'])) {
            $extracted = (float)($extractedInfo['amount_ht_fcfa'] ?? $extractedInfo['amount_ht']);
            $form = (float)$formData['amount_ht'];
            if ($this->amountsMatch($extracted, $form)) {
                $verification['details']['amount_ht'] = '✅ Montant HT: ' . number_format($form, 2, ',', ' ') . ' FCFA';
                $verification['verified_fields']++;
            } else {
                $verification['details']['amount_ht'] = '⚠️ HT ne correspond pas: OCR=' . $extracted . ' vs Formulaire=' . $form;
                $verification['overall_status'] = 'mismatched';
            }
        }

        // Vérifier le montant TVA
        $verification['total_fields']++;
        if (isset($extractedInfo['amount_tva']) && isset($formData['amount_tva'])) {
            $extracted = (float)($extractedInfo['amount_tva_fcfa'] ?? $extractedInfo['amount_tva']);
            $form = (float)$formData['amount_tva'];
            if ($this->amountsMatch($extracted, $form)) {
                $verification['details']['amount_tva'] = '✅ Montant TVA: ' . number_format($form, 2, ',', ' ') . ' FCFA';
                $verification['verified_fields']++;
            } else {
                $verification['details']['amount_tva'] = '⚠️ TVA ne correspond pas: OCR=' . $extracted . ' vs Formulaire=' . $form;
                $verification['overall_status'] = 'mismatched';
            }
        }

        // Vérifier le montant TTC
        $verification['total_fields']++;
        $extracted = isset($extractedInfo['amount_ttc'])
            ? (float)($extractedInfo['amount_ttc_fcfa'] ?? $extractedInfo['amount_ttc'])
            : null;
        $formHT = (float)($formData['amount_ht'] ?? $formData['amount'] ?? 0);
        $formTVA = (float)($formData['amount_tva'] ?? 0);
        $formTTC = $formHT + $formTVA;

        if ($extracted && $this->amountsMatch($extracted, $formTTC)) {
            $verification['details']['amount_ttc'] = '✅ Montant TTC: ' . number_format($formTTC, 2, ',', ' ') . ' FCFA';
            $verification['verified_fields']++;
        } elseif ($extracted) {
            $verification['details']['amount_ttc'] = '⚠️ TTC ne correspond pas: OCR=' . $extracted . ' vs Calculé=' . $formTTC;
            $verification['overall_status'] = 'mismatched';
        }

        // Vérifier le taux TVA
        $verification['total_fields']++;
        if (isset($extractedInfo['tva_rate']) && isset($formData['tva_rate'])) {
            $extracted = (float)$extractedInfo['tva_rate'];
            $form = (float)$formData['tva_rate'];
            if (abs($extracted - $form) < 0.5) {
                $verification['details']['tva_rate'] = '✅ Taux TVA: ' . $form . '%';
                $verification['verified_fields']++;
            } else {
                $verification['details']['tva_rate'] = '⚠️ Taux ne correspond pas: OCR=' . $extracted . '% vs Formulaire=' . $form . '%';
                $verification['overall_status'] = 'mismatched';
            }
        }

        // Vérifier le N° facture
        if (isset($extractedInfo['invoice_number'])) {
            if (!isset($formData['document_reference']) || strpos($extractedInfo['invoice_number'], $formData['document_reference']) !== false) {
                $verification['details']['invoice_number'] = '✅ N° facture: ' . $extractedInfo['invoice_number'];
            } else {
                $verification['alerts']['invoice_number'] = '⚠️ N°: OCR=' . $extractedInfo['invoice_number'] . ' / Formulaire=' . ($formData['document_reference'] ?? 'N/A');
            }
        }

        // Vérifier la date
        if (isset($extractedInfo['date'])) {
            $verification['details']['date'] = '✅ Date: ' . $extractedInfo['date'];
        }

        // Vérifier le partenaire
        if (isset($extractedInfo['partner_name'])) {
            if (!isset($formData['partner_name']) || stripos($extractedInfo['partner_name'], $formData['partner_name']) !== false) {
                $verification['details']['partner_name'] = '✅ Partenaire: ' . substr($extractedInfo['partner_name'], 0, 50);
            } else {
                $verification['alerts']['partner_name'] = '⚠️ Partenaire OCR: ' . substr($extractedInfo['partner_name'], 0, 40);
            }
        }

        return $verification;
    }

    /**
     * Détecte la devise dominante dans le texte OCR.
     */
    private function detectCurrency(string $text): string
    {
        $upper = mb_strtoupper($text);

        if (str_contains($upper, 'FCFA') || str_contains($upper, 'XOF')) {
            return 'FCFA';
        }
        if (str_contains($upper, 'EUR') || str_contains($text, '€')) {
            return 'EUR';
        }
        if (str_contains($upper, 'USD') || str_contains($upper, 'US$')) {
            return 'USD';
        }
        if (str_contains($upper, 'GBP') || str_contains($upper, '£')) {
            return 'GBP';
        }

        return 'FCFA';
    }

    /**
     * Retourne le taux de conversion vers FCFA (configurable).
     */
    private function getCurrencyToFcfaRate(string $currency): float
    {
        return match ($currency) {
            'EUR' => (float) env('OCR_FX_EUR_TO_FCFA', 655.957),
            'USD' => (float) env('OCR_FX_USD_TO_FCFA', 600.0),
            'GBP' => (float) env('OCR_FX_GBP_TO_FCFA', 770.0),
            default => 1.0,
        };
    }

    /**
     * Convertit un montant vers FCFA.
     */
    private function convertAmountToFcfa(?float $amount, string $currency): ?float
    {
        if ($amount === null) {
            return null;
        }

        return $amount * $this->getCurrencyToFcfaRate($currency);
    }

    /**
     * Extraire et vérifier tous les champs du document
     */
    public function analyzeDocumentFields($ocrText, $formData)
    {
        $results = [
            'verified' => false,
            'matches' => [],
            'mismatches' => [],
            'extracted_data' => []
        ];

        // 1. Vérification du N° de facture
        if (!empty($formData['document_reference'])) {
            $invoiceNum = $this->extractInvoiceNumber($ocrText);
            $results['extracted_data']['invoice_number'] = $invoiceNum;
            
            if ($invoiceNum && $this->stringMatch($invoiceNum, $formData['document_reference'])) {
                $results['matches'][] = 'N° facture';
            } elseif ($invoiceNum) {
                $results['mismatches'][] = [
                    'field' => 'N° facture',
                    'expected' => $formData['document_reference'],
                    'detected' => $invoiceNum
                ];
            }
        }

        // 2. Vérification de la date
        if (!empty($formData['date'])) {
            $dates = $this->extractDates($ocrText);
            $results['extracted_data']['dates_found'] = $dates;
            
            $dateMatch = $this->findMatchingDate($dates, $formData['date']);
            if ($dateMatch) {
                $results['matches'][] = 'Date facture';
            } elseif (!empty($dates)) {
                $results['mismatches'][] = [
                    'field' => 'Date facture',
                    'expected' => $formData['date'],
                    'detected' => implode(', ', $dates)
                ];
            }
        }

        // 3. Vérification du montant HT
        if (!empty($formData['amount'])) {
            $amountHT = $this->extractAmount($ocrText, 'HT|Montant|Total');
            $results['extracted_data']['amount_ht'] = $amountHT;
            
            if ($amountHT && $this->amountsMatch($amountHT, $formData['amount'])) {
                $results['matches'][] = 'Montant HT';
                $results['verified'] = true; // Au minimum HT match = document vérifié
            } elseif ($amountHT) {
                $results['mismatches'][] = [
                    'field' => 'Montant HT',
                    'expected' => $formData['amount'],
                    'detected' => $amountHT
                ];
            }
        }

        // 4. Vérification du montant TVA (si fourni)
        if (!empty($formData['tva_amount'])) {
            $amountTVA = $this->extractAmount($ocrText, 'TVA|Tva|Tax');
            $results['extracted_data']['amount_tva'] = $amountTVA;
            
            if ($amountTVA && $this->amountsMatch($amountTVA, $formData['tva_amount'])) {
                $results['matches'][] = 'Montant TVA';
            } elseif ($amountTVA) {
                $results['mismatches'][] = [
                    'field' => 'Montant TVA',
                    'expected' => $formData['tva_amount'],
                    'detected' => $amountTVA
                ];
            }
        }

        // 5. Vérification du montant TTC (si fourni)
        if (!empty($formData['ttc_amount'])) {
            $amountTTC = $this->extractAmount($ocrText, 'TTC|Total|Somme');
            $results['extracted_data']['amount_ttc'] = $amountTTC;
            
            if ($amountTTC && $this->amountsMatch($amountTTC, $formData['ttc_amount'])) {
                $results['matches'][] = 'Montant TTC';
            } elseif ($amountTTC) {
                $results['mismatches'][] = [
                    'field' => 'Montant TTC',
                    'expected' => $formData['ttc_amount'],
                    'detected' => $amountTTC
                ];
            }
        }

        // 6. Vérification du taux TVA (si fourni)
        if (!empty($formData['tva_rate'])) {
            $tvaRate = $this->extractTVARate($ocrText);
            $results['extracted_data']['tva_rate'] = $tvaRate;
            
            if ($tvaRate && abs($tvaRate - $formData['tva_rate']) < 2) { // Tolérance 2%
                $results['matches'][] = 'Taux TVA';
            } elseif ($tvaRate) {
                $results['mismatches'][] = [
                    'field' => 'Taux TVA',
                    'expected' => $formData['tva_rate'] . '%',
                    'detected' => $tvaRate . '%'
                ];
            }
        }

        // 7. Vérification du partenaire (si fourni)
        if (!empty($formData['partner_name'])) {
            $partners = $this->extractPartnerNames($ocrText);
            $results['extracted_data']['partners'] = $partners;
            
            $partnerMatch = $this->findMatchingPartner($partners, $formData['partner_name']);
            if ($partnerMatch) {
                $results['matches'][] = 'Partenaire';
            } elseif (!empty($partners)) {
                $results['mismatches'][] = [
                    'field' => 'Partenaire',
                    'expected' => $formData['partner_name'],
                    'detected' => implode(', ', $partners)
                ];
            }
        }

        return $results;
    }

    /**
     * Extraire le numéro de facture
     */
    private function extractInvoiceNumber($text): ?string
    {
        $patterns = [
            '/Facture\s*#?[\s:]*(\w+[-]?\w+)/i',
            '/N°?\s*Facture[\s:]*(\w+[-]?\w+)/i',
            '/Facture\s*N°[\s:]*(\w+[-]?\w+)/i',
            '/Invoice\s*#?[\s:]*(\w+[-]?\w+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1]);
            }
        }
        return null;
    }

    /**
     * Extraire les dates du texte
     */
    private function extractDates($text): array
    {
        $dates = [];
        $patterns = [
            '/(?:Date|DATE)[\s:]*(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/i',
            '/(\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $dates = array_merge($dates, $matches[1]);
            }
        }

        return array_unique($dates);
    }

    /**
     * Vérifier si une date du document correspond à la date du formulaire
     */
    private function findMatchingDate($detectedDates, $formDate): bool
    {
        foreach ($detectedDates as $date) {
            // Normaliser les formats
            $normalized = $this->normalizeDate($date);
            $formNormalized = $this->normalizeDate($formDate);
            
            if ($normalized === $formNormalized) {
                return true;
            }
        }
        return false;
    }

    /**
     * Normaliser les dates pour comparaison (format YYYY-MM-DD)
     */
    private function normalizeDate($date): string
    {
        // Essayer plusieurs formats
        $formats = ['Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-Y', 'm/d/Y'];
        
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }
        
        return $date;
    }

    /**
     * Extraire un montant avec un label spécifique
     */
    private function extractAmount($text, $labels): ?float
    {
        $pattern = '/(?:' . $labels . ')[\s:]*(\d+[.,]\d{2})/i';
        
        if (preg_match_all($pattern, $text, $matches)) {
            // Retourner le plus grand montant trouvé (généralement le total)
            $amounts = [];
            foreach ($matches[1] as $amount) {
                $amounts[] = (float)str_replace(',', '.', $amount);
            }
            return !empty($amounts) ? max($amounts) : null;
        }
        
        return null;
    }

    /**
     * Extraire le taux TVA
     */
    private function extractTVARate($text): ?float
    {
        $patterns = [
            '/(?:Taux[\s])?TVA[\s:]*(\d+(?:[.,]\d{1,2})?)\s*%/i',
            '/TVA[\s:]*(\d+(?:[.,]\d{1,2})?)\s*%/i',
            '/(?:Tax|TAX)[\s:]*(\d+(?:[.,]\d{1,2})?)\s*%/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float)str_replace(',', '.', $matches[1]);
            }
        }
        
        return null;
    }

    /**
     * Extraire les noms de partenaires/entreprises
     */
    private function extractPartnerNames($text): array
    {
        $partners = [];
        $patterns = [
            '/(?:Fournisseur|Supplier|Vendeur|Vendor)[\s:]*([^\n]+)/i',
            '/(?:Client|Customer)[\s:]*([^\n]+)/i',
            '/(?:Entreprise|Company)[\s:]*([^\n]+)/i',
            '/(?:À (?:l\')?|To)\s+([A-Z][^\n]+?)(?:\n|$)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] as $partner) {
                    $partner = trim($partner);
                    if (strlen($partner) > 3 && strlen($partner) < 100) {
                        $partners[] = $partner;
                    }
                }
            }
        }

        return array_unique($partners);
    }

    /**
     * Vérifier si une entreprise extrait correspond au partenaire du formulaire
     */
    private function findMatchingPartner($detectedPartners, $formPartner): bool
    {
        foreach ($detectedPartners as $partner) {
            if ($this->stringMatch($partner, $formPartner)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Comparaison floue de chaînes (tolérance pour petites variations)
     */
    private function stringMatch($str1, $str2, $similarity = 0.7): bool
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) return true;
        
        // Comparaison rapide si l'une contient l'autre
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return true;
        }
        
        // Calcul de similarité Levenshtein
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        $matchPercentage = 1 - ($distance / $maxLength);
        
        return $matchPercentage >= $similarity;
    }
}

