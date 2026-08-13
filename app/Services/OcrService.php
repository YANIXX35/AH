<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class OcrService
{
    /**
     * Extraire le texte du document uploadé via OCR local.
     */
    public function extractText($filePath)
    {
        try {
            $fullPath = storage_path('app/public/'.$filePath);

            if (! file_exists($fullPath)) {
                return [
                    'success' => false,
                    'message' => 'Fichier non trouvé',
                    'text' => '',
                    'error_code' => 'FILE_NOT_FOUND',
                    'error_location' => $fullPath,
                    'endpoint' => $this->endpoint(),
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
            if (! in_array($mimeType, $supportedMimes, true)) {
                return [
                    'success' => false,
                    'message' => 'Format non supporté. Utilisez: JPG, PNG, PDF, XLS, XLSX ou CSV',
                    'text' => '',
                    'error_code' => 'UNSUPPORTED_MIME',
                    'error_location' => $mimeType,
                    'endpoint' => $this->endpoint(),
                ];
            }

            $maxFileSizeKb = (int) config('services.ocr_space.max_file_size_kb', 1024);
            $fileSizeBytes = filesize($fullPath) ?: 0;
            if ($maxFileSizeKb > 0 && $fileSizeBytes > ($maxFileSizeKb * 1024)) {
                return [
                    'success' => false,
                    'message' => "Fichier trop volumineux pour l'OCR (max {$maxFileSizeKb} KB).",
                    'text' => '',
                    'error_code' => 'OCR_FILE_TOO_LARGE',
                    'error_location' => basename($fullPath),
                    'endpoint' => $this->endpoint(),
                ];
            }

            return $this->callOcrSpace($fullPath, $mimeType);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur OCR: '.$e->getMessage(),
                'text' => '',
                'error_code' => 'OCR_EXCEPTION',
                'error_location' => 'HTTP request to OCR API',
                'endpoint' => $this->endpoint(),
            ];
        }
    }

    private function endpoint(): string
    {
        return 'ocr_space_api';
    }

    /**
     * Envoie le fichier à l'API cloud OCR.space pour extraction de texte.
     * Ne nécessite aucune dépendance serveur (Python, GPU...) — un simple appel
     * HTTP sortant, ce qui fonctionne sur n'importe quel hébergement, y compris
     * mutualisé. Remplace l'ancien moteur PaddleOCR local, qui nécessitait un
     * environnement Python indisponible sur l'hébergement de production actuel.
     */
    private function callOcrSpace(string $fullPath, string $mimeType): array
    {
        $apiKey = trim((string) config('services.ocr_space.api_key', ''));
        if ($apiKey === '') {
            return [
                'success' => false,
                'message' => 'Clé API OCR.space manquante. Renseignez OCR_SPACE_API_KEY.',
                'text' => '',
                'error_code' => 'OCR_SPACE_CONFIG_MISSING',
                'error_location' => 'config/services.php',
                'endpoint' => $this->endpoint(),
            ];
        }

        $endpoint = (string) config('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        $timeout = max(5, (int) config('services.ocr_space.timeout', 60));
        $fileType = match ($mimeType) {
            'application/pdf' => 'PDF',
            'image/png' => 'PNG',
            default => 'JPG',
        };

        try {
            $response = Http::timeout($timeout)
                ->attach('file', fopen($fullPath, 'rb'), basename($fullPath))
                ->asMultipart()
                ->post($endpoint, [
                    ['name' => 'apikey', 'contents' => $apiKey],
                    ['name' => 'filetype', 'contents' => $fileType],
                    ['name' => 'language', 'contents' => (string) config('services.ocr_space.language', 'fre')],
                    ['name' => 'OCREngine', 'contents' => (string) config('services.ocr_space.engine', 2)],
                    ['name' => 'isTable', 'contents' => $this->toBooleanString((bool) config('services.ocr_space.is_table', true))],
                    ['name' => 'scale', 'contents' => $this->toBooleanString((bool) config('services.ocr_space.scale', true))],
                    ['name' => 'detectOrientation', 'contents' => $this->toBooleanString((bool) config('services.ocr_space.detect_orientation', true))],
                    ['name' => 'isOverlayRequired', 'contents' => 'false'],
                ]);
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Impossible de contacter le service OCR.space: '.$exception->getMessage(),
                'text' => '',
                'error_code' => 'OCR_SPACE_HTTP_ERROR',
                'error_location' => $endpoint,
                'endpoint' => $this->endpoint(),
            ];
        }

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => 'Le service OCR.space a répondu avec une erreur HTTP '.$response->status().'.',
                'text' => '',
                'error_code' => 'OCR_SPACE_HTTP_'.$response->status(),
                'error_location' => $endpoint,
                'endpoint' => $this->endpoint(),
                'raw_response' => ['body' => $response->body()],
            ];
        }

        $result = $response->json();
        if (! is_array($result)) {
            return [
                'success' => false,
                'message' => 'Le service OCR.space a renvoyé une réponse invalide.',
                'text' => '',
                'error_code' => 'OCR_SPACE_INVALID_JSON',
                'error_location' => $endpoint,
                'endpoint' => $this->endpoint(),
                'raw_response' => ['body' => $response->body()],
            ];
        }

        if ((bool) ($result['IsErroredOnProcessing'] ?? false)) {
            $errorMessage = $result['ErrorMessage'] ?? $result['ErrorDetails'] ?? 'Erreur inconnue OCR.space.';
            $errorMessage = is_array($errorMessage) ? implode(' ', $errorMessage) : (string) $errorMessage;

            return [
                'success' => false,
                'message' => 'Erreur OCR.space: '.$errorMessage,
                'text' => '',
                'error_code' => 'OCR_SPACE_PROCESSING_ERROR',
                'error_location' => $endpoint,
                'endpoint' => $this->endpoint(),
                'raw_response' => $result,
            ];
        }

        $parsedText = $this->extractParsedText($result);
        if ($parsedText === '') {
            return [
                'success' => false,
                'message' => 'Aucun texte détecté par OCR.space.',
                'text' => '',
                'error_code' => 'OCR_SPACE_EMPTY_TEXT',
                'error_location' => $fullPath,
                'endpoint' => $this->endpoint(),
                'raw_response' => $result,
            ];
        }

        return [
            'success' => true,
            'message' => 'OCR réalisé avec succès via OCR.space.',
            'text' => $parsedText,
            'confidence' => $this->estimateConfidence($result, $parsedText),
            'endpoint' => $this->endpoint(),
            'raw_response' => $result,
        ];
    }

    private function runLocalPaddleOcr(string $fullPath): array
    {
        if (! (bool) config('services.paddle_ocr.enabled', false)) {
            return [
                'success' => false,
                'message' => 'Le moteur PaddleOCR local est désactivé. Activez PADDLE_OCR_ENABLED.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_DISABLED',
                'error_location' => 'config/services.php',
                'endpoint' => $this->endpoint(),
            ];
        }

        $pythonPath = trim((string) config('services.paddle_ocr.python_path', 'python'));
        $runnerPath = trim((string) config('services.paddle_ocr.runner_path', ''));
        if ($pythonPath === '' || $runnerPath === '') {
            return [
                'success' => false,
                'message' => 'Configuration PaddleOCR incomplète. Vérifiez PADDLE_OCR_PYTHON_PATH et PADDLE_OCR_RUNNER_PATH.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_CONFIG_MISSING',
                'error_location' => 'config/services.php',
                'endpoint' => $this->endpoint(),
            ];
        }

        if (! file_exists($runnerPath)) {
            return [
                'success' => false,
                'message' => 'Runner PaddleOCR introuvable.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_RUNNER_NOT_FOUND',
                'error_location' => $runnerPath,
                'endpoint' => $this->endpoint(),
            ];
        }

        $command = [
            $pythonPath,
            $runnerPath,
            '--input',
            $fullPath,
            '--preferred-device',
            (string) config('services.paddle_ocr.preferred_device', 'gpu'),
            '--fallback-to-cpu',
            $this->toBooleanString((bool) config('services.paddle_ocr.fallback_to_cpu', true)),
            '--language',
            (string) config('services.paddle_ocr.language', 'fr'),
            '--page-num',
            (string) max(0, (int) config('services.paddle_ocr.page_num', 0)),
        ];

        $process = new Process($command);
        $timeout = max(1, (int) config('services.paddle_ocr.timeout', 180));
        $this->extendPhpExecutionTime($timeout);
        $process->setTimeout($timeout);
        $process->setEnv([
            'PYTHONUTF8' => '1',
            'PYTHONUNBUFFERED' => '1',
            'PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK' => 'True',
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            return [
                'success' => false,
                'message' => 'Le runner PaddleOCR a dépassé le délai autorisé.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_TIMEOUT',
                'error_location' => $fullPath,
                'endpoint' => $this->endpoint(),
            ];
        } catch (\Throwable $exception) {
            return [
                'success' => false,
                'message' => 'Impossible d’exécuter le runner PaddleOCR: '.$exception->getMessage(),
                'text' => '',
                'error_code' => 'LOCAL_OCR_PROCESS_ERROR',
                'error_location' => $runnerPath,
                'endpoint' => $this->endpoint(),
            ];
        }

        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());
        $decoded = json_decode($stdout, true);

        if (! is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Le runner PaddleOCR a renvoyé une réponse JSON invalide.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_INVALID_JSON',
                'error_location' => $runnerPath,
                'endpoint' => $this->endpoint(),
                'raw_response' => [
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                    'exit_code' => $process->getExitCode(),
                ],
            ];
        }

        $decoded['endpoint'] = $this->endpoint();
        if (! empty($stderr)) {
            $decoded['raw_stderr'] = $stderr;
        }

        if (
            ($decoded['success'] ?? false)
            && is_string($stderr)
            && str_contains($stderr, 'Switching to CPU instead')
        ) {
            $decoded['mode'] = 'cpu';
            $decoded['message'] = 'OCR local réalisé avec succès via PaddleOCR (cpu).';
        }

        if (! ($decoded['success'] ?? false)) {
            $decoded['message'] = (string) ($decoded['message'] ?? 'Erreur PaddleOCR locale.');
            $decoded['text'] = (string) ($decoded['text'] ?? '');
            $decoded['error_code'] = (string) ($decoded['error_code'] ?? 'LOCAL_OCR_FAILED');
            $decoded['error_location'] = (string) ($decoded['error_location'] ?? $fullPath);

            return $decoded;
        }

        $decoded['message'] = (string) ($decoded['message'] ?? 'OCR local réalisé avec succès.');
        $decoded['text'] = trim((string) ($decoded['text'] ?? ''));
        $decoded['confidence'] = (float) ($decoded['confidence'] ?? 0);
        $decoded['raw_response'] = (array) ($decoded['raw_response'] ?? []);

        if ($decoded['text'] === '') {
            return [
                'success' => false,
                'message' => 'Aucun texte détecté par PaddleOCR.',
                'text' => '',
                'error_code' => 'LOCAL_OCR_EMPTY_TEXT',
                'error_location' => $fullPath,
                'endpoint' => $this->endpoint(),
                'raw_response' => $decoded['raw_response'],
            ];
        }

        return $decoded;
    }

    /**
     * Allonge temporairement la limite d'exécution PHP pour laisser le runner OCR finir.
     */
    private function extendPhpExecutionTime(int $timeout): void
    {
        if (! function_exists('set_time_limit')) {
            return;
        }

        $targetSeconds = max(120, $timeout + 30);

        try {
            @set_time_limit($targetSeconds);
        } catch (\Throwable $exception) {
            // Certains environnements verrouillent set_time_limit ; on garde alors la limite courante.
        }
    }

    private function toBooleanString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }

    private function extractParsedText(array $result): string
    {
        $pages = [];
        foreach ((array) ($result['ParsedResults'] ?? []) as $parsedResult) {
            $pageText = trim((string) ($parsedResult['ParsedText'] ?? ''));
            if ($pageText !== '') {
                $pages[] = $pageText;
            }
        }

        if (! empty($pages)) {
            return trim(implode("\n\n", $pages));
        }

        return trim((string) ($result['ParsedText'] ?? ''));
    }

    private function estimateConfidence(array $result, string $parsedText): float
    {
        $parsedResults = (array) ($result['ParsedResults'] ?? []);
        if (empty($parsedResults)) {
            return mb_strlen($parsedText) >= 30 ? 70.0 : 55.0;
        }

        $successfulPages = 0;
        foreach ($parsedResults as $parsedResult) {
            if ((int) ($parsedResult['FileParseExitCode'] ?? 0) === 1 && trim((string) ($parsedResult['ParsedText'] ?? '')) !== '') {
                $successfulPages++;
            }
        }

        if ($successfulPages === 0) {
            return 35.0;
        }

        $ratio = $successfulPages / max(1, count($parsedResults));
        $lengthBonus = mb_strlen($parsedText) >= 100 ? 12.0 : (mb_strlen($parsedText) >= 30 ? 6.0 : 0.0);

        return round(min(100.0, max(35.0, ($ratio * 82.0) + $lengthBonus)), 2);
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

                if (! empty($values)) {
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
                            'detected_amount' => (float) $amount,
                            'message' => 'Montant vérifié ✓',
                        ];
                    }
                }
            }
        }

        return [
            'verified' => false,
            'detected_amount' => null,
            'message' => 'Montant non trouvé ou ne correspond pas',
        ];
    }

    /**
     * Vérifier si deux montants correspondent (tolérance ±5%)
     */
    private function amountsMatch($detected, $form)
    {
        $form = (float) $form;
        $detected = (float) $detected;

        if ($form == 0) {
            return false;
        }

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
        $parties = $this->extractDocumentParties($ocrText);

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
            'partner_name' => $parties['supplier'] ?? $partners[0] ?? $parties['client'] ?? null,
            'supplier_name' => $parties['supplier'] ?? null,
            'client_name' => $parties['client'] ?? null,
        ];
    }

    /**
     * Extrait un jeu de données OCR étendu pour exposer un maximum d'informations.
     */
    public function extractRichDocumentData(string $ocrText): array
    {
        $base = $this->extractDocumentInfo($ocrText);
        $partnerCandidates = $this->extractPartnerNames($ocrText);
        $supplier = $base['supplier_name'] ?? null;
        $client = $base['client_name'] ?? null;

        return [
            'document_title' => $this->extractDocumentTitle($ocrText),
            'primary' => [
                'invoice_number' => $base['invoice_number'] ?? null,
                'invoice_date' => $base['date'] ?? null,
                'partner_name' => $base['partner_name'] ?? null,
                'supplier_name' => $base['supplier_name'] ?? null,
                'client_name' => $base['client_name'] ?? null,
                'currency' => $base['currency'] ?? 'FCFA',
                'amount_ht' => $base['amount_ht_fcfa'] ?? $base['amount_ht'] ?? null,
                'amount_tva' => $base['amount_tva_fcfa'] ?? $base['amount_tva'] ?? null,
                'amount_ttc' => $base['amount_ttc_fcfa'] ?? $base['amount_ttc'] ?? null,
                'tva_rate' => $base['tva_rate'] ?? null,
            ],
            'parties' => [
                'supplier' => $supplier,
                'client' => $client,
                'partner_candidates' => $partnerCandidates,
            ],
            'contacts' => [
                'emails' => $this->extractEmails($ocrText),
                'phones' => $this->extractPhones($ocrText),
                'websites' => $this->extractWebsites($ocrText),
            ],
            'addresses' => [
                'supplier_address' => $this->extractAddressForParty($ocrText, ['fournisseur', 'supplier', 'vendeur', 'vendor']),
                'client_address' => $this->extractAddressForParty($ocrText, ['client', 'customer', 'destinataire']),
                'address_candidates' => $this->extractAddressCandidates($ocrText),
            ],
            'identifiers' => [
                'tax_ids' => $this->extractTaxIdentifiers($ocrText),
                'business_ids' => $this->extractBusinessIdentifiers($ocrText),
                'references' => $this->extractReferenceCandidates($ocrText),
            ],
            'payment' => [
                'terms' => $this->extractPaymentTerms($ocrText),
                'due_dates' => $this->extractDueDateCandidates($ocrText),
                'payment_methods' => $this->extractPaymentMethodCandidates($ocrText),
            ],
            'banking' => [
                'iban' => $this->extractIbanCandidates($ocrText),
                'bic_swift' => $this->extractBicCandidates($ocrText),
                'account_numbers' => $this->extractBankAccountCandidates($ocrText),
            ],
            'line_items' => $this->extractLineItems($ocrText),
            'dates' => $this->extractDateCandidates($ocrText),
            'amount_candidates' => $this->extractAmountCandidates($ocrText),
            'key_values' => $this->extractKeyValueLines($ocrText),
            'meta' => [
                'line_count' => $this->countTextLines($ocrText),
                'character_count' => mb_strlen($ocrText),
            ],
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
            'overall_status' => 'verified',
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
            $extracted = (float) ($extractedInfo['amount_ht_fcfa'] ?? $extractedInfo['amount_ht']);
            $form = (float) $formData['amount_ht'];
            if ($this->amountsMatch($extracted, $form)) {
                $verification['details']['amount_ht'] = '✅ Montant HT: '.number_format($form, 2, ',', ' ').' FCFA';
                $verification['verified_fields']++;
            } else {
                $verification['details']['amount_ht'] = '⚠️ HT ne correspond pas: OCR='.$extracted.' vs Formulaire='.$form;
                $verification['overall_status'] = 'mismatch';
            }
        }

        // Vérifier le montant TVA
        $verification['total_fields']++;
        if (isset($extractedInfo['amount_tva']) && isset($formData['amount_tva'])) {
            $extracted = (float) ($extractedInfo['amount_tva_fcfa'] ?? $extractedInfo['amount_tva']);
            $form = (float) $formData['amount_tva'];
            if ($this->amountsMatch($extracted, $form)) {
                $verification['details']['amount_tva'] = '✅ Montant TVA: '.number_format($form, 2, ',', ' ').' FCFA';
                $verification['verified_fields']++;
            } else {
                $verification['details']['amount_tva'] = '⚠️ TVA ne correspond pas: OCR='.$extracted.' vs Formulaire='.$form;
                $verification['overall_status'] = 'mismatch';
            }
        }

        // Vérifier le montant TTC
        $verification['total_fields']++;
        $extracted = isset($extractedInfo['amount_ttc'])
            ? (float) ($extractedInfo['amount_ttc_fcfa'] ?? $extractedInfo['amount_ttc'])
            : null;
        $formHT = (float) ($formData['amount_ht'] ?? $formData['amount'] ?? 0);
        $formTVA = (float) ($formData['amount_tva'] ?? 0);
        $formTTC = $formHT + $formTVA;

        if ($extracted && $this->amountsMatch($extracted, $formTTC)) {
            $verification['details']['amount_ttc'] = '✅ Montant TTC: '.number_format($formTTC, 2, ',', ' ').' FCFA';
            $verification['verified_fields']++;
        } elseif ($extracted) {
            $verification['details']['amount_ttc'] = '⚠️ TTC ne correspond pas: OCR='.$extracted.' vs Calculé='.$formTTC;
            $verification['overall_status'] = 'mismatch';
        }

        // Vérifier le taux TVA
        $verification['total_fields']++;
        if (isset($extractedInfo['tva_rate']) && isset($formData['tva_rate'])) {
            $extracted = (float) $extractedInfo['tva_rate'];
            $form = (float) $formData['tva_rate'];
            if (abs($extracted - $form) < 0.5) {
                $verification['details']['tva_rate'] = '✅ Taux TVA: '.$form.'%';
                $verification['verified_fields']++;
            } else {
                $verification['details']['tva_rate'] = '⚠️ Taux ne correspond pas: OCR='.$extracted.'% vs Formulaire='.$form.'%';
                $verification['overall_status'] = 'mismatch';
            }
        }

        // Vérifier le N° facture
        if (isset($extractedInfo['invoice_number'])) {
            if (! isset($formData['document_reference']) || strpos($extractedInfo['invoice_number'], $formData['document_reference']) !== false) {
                $verification['details']['invoice_number'] = '✅ N° facture: '.$extractedInfo['invoice_number'];
            } else {
                $verification['alerts']['invoice_number'] = '⚠️ N°: OCR='.$extractedInfo['invoice_number'].' / Formulaire='.($formData['document_reference'] ?? 'N/A');
            }
        }

        // Vérifier la date
        if (isset($extractedInfo['date'])) {
            $verification['details']['date'] = '✅ Date: '.$extractedInfo['date'];
        }

        // Vérifier le partenaire
        if (isset($extractedInfo['partner_name'])) {
            if (! isset($formData['partner_name']) || stripos($extractedInfo['partner_name'], $formData['partner_name']) !== false) {
                $verification['details']['partner_name'] = '✅ Partenaire: '.substr($extractedInfo['partner_name'], 0, 50);
            } else {
                $verification['alerts']['partner_name'] = '⚠️ Partenaire OCR: '.substr($extractedInfo['partner_name'], 0, 40);
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

        if (preg_match('/\b(?:F\s*CFA|FCFA|XOF|XAF|CFA)\b/iu', $upper)) {
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
     * Détecte un titre de document probable sur les premières lignes OCR.
     */
    private function extractDocumentTitle(string $text): ?string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach (array_slice($lines, 0, 8) as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || mb_strlen($candidate) < 4) {
                continue;
            }
            if (preg_match('/\b(facture|invoice|reçu|recu|bon de commande|avoir)\b/iu', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Extrait les paires clé/valeur présentes dans le document.
     */
    private function extractKeyValueLines(string $text): array
    {
        $rows = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || mb_strlen($candidate) > 180) {
                continue;
            }
            if (preg_match('/^([^:]{2,80})\s*:\s*(.{1,120})$/u', $candidate, $matches)) {
                $rows[] = [
                    'label' => trim($matches[1]),
                    'value' => trim($matches[2]),
                ];
            }
            if (count($rows) >= 30) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Extrait les adresses e-mail reconnues dans le texte OCR.
     */
    private function extractEmails(string $text): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', $text, $matches);

        return array_values(array_unique(array_map('trim', $matches[0] ?? [])));
    }

    /**
     * Extrait les numéros de téléphone probables.
     */
    private function extractPhones(string $text): array
    {
        preg_match_all('/(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?){2,5}\d{2,4}/u', $text, $matches);
        $phones = [];
        foreach ($matches[0] ?? [] as $rawPhone) {
            $phone = trim((string) $rawPhone);
            $digits = preg_replace('/\D/u', '', $phone) ?? '';
            if (strlen($digits) < 8 || strlen($digits) > 15) {
                continue;
            }
            $phones[] = $phone;
        }

        return array_values(array_unique($phones));
    }

    /**
     * Extrait les identifiants fiscaux possibles (IFU, RCCM, NIF, etc.).
     */
    private function extractTaxIdentifiers(string $text): array
    {
        $patterns = [
            '/\b(?:NIF|IFU|RCCM|CCM|IDU|TVA)\s*[:#-]?\s*([A-Z0-9\-\/]{4,})/iu',
            '/\b(?:RC|R\.C\.)\s*[:#-]?\s*([A-Z0-9\-\/]{4,})/iu',
        ];

        $ids = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] ?? [] as $id) {
                    $ids[] = trim((string) $id);
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Extrait les références génériques (facture, BC, BL, commande...).
     */
    private function extractReferenceCandidates(string $text): array
    {
        $patterns = [
            '/\b(?:facture|invoice|référence|reference|ref|commande|bon de commande|bc|bl)\s*(?:n[°ºo.]?|num(?:[ée]ro)?)?\s*[:#-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu',
        ];
        $refs = [];
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] ?? [] as $reference) {
                    $refs[] = trim((string) $reference);
                }
            }
        }

        return array_values(array_unique(array_filter($refs)));
    }

    /**
     * Extrait toutes les dates détectées dans le texte OCR.
     */
    private function extractDateCandidates(string $text): array
    {
        $dates = $this->extractDates($text);
        $extraPatterns = [
            '/\b(\d{4}[\/.-]\d{1,2}[\/.-]\d{1,2})\b/u',
        ];
        foreach ($extraPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[1] ?? [] as $date) {
                    $dates[] = trim((string) $date);
                }
            }
        }

        return array_values(array_unique(array_filter($dates)));
    }

    /**
     * Extrait les montants détectés avec leurs libellés de proximité.
     */
    private function extractAmountCandidates(string $text): array
    {
        $rows = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '') {
                continue;
            }
            foreach ($this->extractNumericAmountsFromLine($candidate) as $amountData) {
                $label = null;
                if (preg_match('/\b(ht|tva|ttc|total|net a payer|net à payer|sous-total)\b/iu', $candidate, $labelMatch)) {
                    $label = mb_strtolower(trim((string) $labelMatch[1]));
                }
                $rows[] = [
                    'label' => $label,
                    'value' => $amountData['value'],
                    'raw' => $amountData['raw'],
                ];
                if (count($rows) >= 50) {
                    break 2;
                }
            }
        }

        return $rows;
    }

    /**
     * Extrait des sites web présents dans le document.
     */
    private function extractWebsites(string $text): array
    {
        preg_match_all('/\b(?:https?:\/\/)?(?:www\.)?[a-z0-9\-]+\.[a-z]{2,}(?:\/[^\s]*)?/iu', $text, $matches);
        $sites = [];
        foreach ($matches[0] ?? [] as $rawSite) {
            $site = trim((string) $rawSite);
            if ($site === '') {
                continue;
            }
            $sites[] = rtrim($site, '.,;)');
        }

        return array_values(array_unique($sites));
    }

    /**
     * Extrait les identifiants business (RCCM, registre, etc.).
     */
    private function extractBusinessIdentifiers(string $text): array
    {
        $patterns = [
            '/\b(?:RCCM|RC|R\.C\.|Registre(?:\s+de\s+commerce)?)\s*[:#-]?\s*([A-Z0-9\-\/.]{4,})/iu',
            '/\b(?:IDU|IFU|NCCM|CCM)\s*[:#-]?\s*([A-Z0-9\-\/.]{4,})/iu',
        ];
        $ids = [];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            foreach ($matches[1] ?? [] as $value) {
                $ids[] = trim((string) $value);
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Détecte les conditions de paiement (30 jours, comptant, etc.).
     */
    private function extractPaymentTerms(string $text): array
    {
        $terms = [];
        $patterns = [
            '/\b(?:conditions?\s+de\s+paiement|payment\s+terms?)\s*[:\-]?\s*([^\n\r]{3,120})/iu',
            '/\b(?:paiement\s+(?:à|a)\s+\d+\s*jours?|net\s*\d+\s*jours?)\b/iu',
            '/\b(?:paiement\s+comptant|cash|virement|ch[èe]que|mobile\s*money)\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            $source = $matches[1] ?? $matches[0] ?? [];
            foreach ($source as $term) {
                $candidate = trim((string) $term);
                if ($candidate !== '') {
                    $terms[] = $candidate;
                }
            }
        }

        return array_values(array_unique($terms));
    }

    /**
     * Extrait les dates d'échéance possibles.
     */
    private function extractDueDateCandidates(string $text): array
    {
        $dates = [];
        $patterns = [
            '/\b(?:[ée]ch[ée]ance|date\s+d[\' ]?échéance|due\s+date)\s*[:\-]?\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/iu',
            '/\b(?:due\s+on|payable\s+on)\s*[:\-]?\s*(\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2})/iu',
        ];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            foreach ($matches[1] ?? [] as $value) {
                $dates[] = trim((string) $value);
            }
        }

        return array_values(array_unique(array_filter($dates)));
    }

    /**
     * Extrait les moyens de paiement probables.
     */
    private function extractPaymentMethodCandidates(string $text): array
    {
        $methods = [];
        $keywords = [
            'virement', 'carte', 'esp[eè]ces', 'ch[èe]que', 'mobile money',
            'wave', 'orange money', 'mtn money', 'moov money', 'cash',
        ];
        foreach ($keywords as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/iu', $text)) {
                $methods[] = mb_strtolower($keyword);
            }
        }

        return array_values(array_unique($methods));
    }

    /**
     * Extrait les IBAN potentiels.
     */
    private function extractIbanCandidates(string $text): array
    {
        preg_match_all('/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/u', strtoupper($text), $matches);

        return array_values(array_unique(array_map('trim', $matches[0] ?? [])));
    }

    /**
     * Extrait les codes BIC/SWIFT potentiels.
     */
    private function extractBicCandidates(string $text): array
    {
        preg_match_all('/\b[A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?\b/u', strtoupper($text), $matches);

        return array_values(array_unique(array_map('trim', $matches[0] ?? [])));
    }

    /**
     * Extrait les numéros de compte bancaire locaux probables.
     */
    private function extractBankAccountCandidates(string $text): array
    {
        $accounts = [];
        $patterns = [
            '/\b(?:compte|account|rib)\s*[:#-]?\s*([A-Z0-9\- ]{6,40})/iu',
            '/\b\d{8,24}\b/u',
        ];
        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $text, $matches)) {
                continue;
            }
            $source = $matches[1] ?? $matches[0] ?? [];
            foreach ($source as $raw) {
                $candidate = trim((string) $raw);
                $digits = preg_replace('/\D/u', '', $candidate) ?? '';
                if (strlen($digits) < 8) {
                    continue;
                }
                $accounts[] = $candidate;
            }
        }

        return array_values(array_unique($accounts));
    }

    /**
     * Extrait l'adresse liée à une partie (client/fournisseur) si trouvée.
     */
    private function extractAddressForParty(string $text, array $labels): ?string
    {
        $escapedLabels = array_map(
            fn (string $label) => preg_quote($label, '/'),
            $labels
        );
        $pattern = '/\b(?:'.implode('|', $escapedLabels).')\b[^\n\r]*\R([^\n\r]{6,140})/iu';
        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }
        $candidate = trim((string) ($matches[1] ?? ''));

        return $this->looksLikeAddress($candidate) ? $candidate : null;
    }

    /**
     * Extrait des adresses candidates globales.
     */
    private function extractAddressCandidates(string $text): array
    {
        $candidates = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if (! $this->looksLikeAddress($candidate)) {
                continue;
            }
            $candidates[] = $candidate;
            if (count($candidates) >= 12) {
                break;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Heuristique simple pour reconnaître une ligne d'adresse.
     */
    private function looksLikeAddress(string $line): bool
    {
        if ($line === '' || mb_strlen($line) < 8 || mb_strlen($line) > 160) {
            return false;
        }

        if (preg_match('/\b(?:rue|avenue|av\.?|boulevard|bd|quartier|zone|bp|bo[iî]te\s+postale|lot|immeuble|city|ville)\b/iu', $line)) {
            return true;
        }

        return (bool) preg_match('/\d{2,}.*[A-Za-zÀ-ÿ]{3,}/u', $line);
    }

    /**
     * Extrait les lignes de détail facture (quantité, prix, total).
     */
    private function extractLineItems(string $text): array
    {
        $items = [];
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || mb_strlen($candidate) < 10) {
                continue;
            }

            if (! preg_match('/\b(?:qt[ée]|quantit[eé]|pu|prix|montant|total)\b/iu', $candidate)) {
                continue;
            }

            $amounts = $this->extractNumericAmountsFromLine($candidate);
            $items[] = [
                'raw_line' => $candidate,
                'amounts' => $amounts,
            ];

            if (count($items) >= 25) {
                break;
            }
        }

        return $items;
    }

    /**
     * Compte les lignes non vides du texte OCR.
     */
    private function countTextLines(string $text): int
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $nonEmpty = array_filter($lines, fn ($line) => trim((string) $line) !== '');

        return count($nonEmpty);
    }

    /**
     * Normalise un montant OCR pour faciliter la lecture.
     */
    private function normalizeSimpleAmount(string $rawAmount): ?float
    {
        $value = preg_replace('/[^\d,.\s]/u', '', $rawAmount);
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } else {
            if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        $value = str_replace(' ', '', $value);
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
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
            'extracted_data' => [],
        ];

        // 1. Vérification du N° de facture
        if (! empty($formData['document_reference'])) {
            $invoiceNum = $this->extractInvoiceNumber($ocrText);
            $results['extracted_data']['invoice_number'] = $invoiceNum;

            if ($invoiceNum && $this->stringMatch($invoiceNum, $formData['document_reference'])) {
                $results['matches'][] = 'N° facture';
            } elseif ($invoiceNum) {
                $results['mismatches'][] = [
                    'field' => 'N° facture',
                    'expected' => $formData['document_reference'],
                    'detected' => $invoiceNum,
                ];
            }
        }

        // 2. Vérification de la date
        if (! empty($formData['date'])) {
            $dates = $this->extractDates($ocrText);
            $results['extracted_data']['dates_found'] = $dates;

            $dateMatch = $this->findMatchingDate($dates, $formData['date']);
            if ($dateMatch) {
                $results['matches'][] = 'Date facture';
            } elseif (! empty($dates)) {
                $results['mismatches'][] = [
                    'field' => 'Date facture',
                    'expected' => $formData['date'],
                    'detected' => implode(', ', $dates),
                ];
            }
        }

        // 3. Vérification du montant HT
        if (! empty($formData['amount'])) {
            $amountHT = $this->extractAmount($ocrText, 'HT|Montant|Total');
            $results['extracted_data']['amount_ht'] = $amountHT;

            if ($amountHT && $this->amountsMatch($amountHT, $formData['amount'])) {
                $results['matches'][] = 'Montant HT';
                $results['verified'] = true; // Au minimum HT match = document vérifié
            } elseif ($amountHT) {
                $results['mismatches'][] = [
                    'field' => 'Montant HT',
                    'expected' => $formData['amount'],
                    'detected' => $amountHT,
                ];
            }
        }

        // 4. Vérification du montant TVA (si fourni)
        if (! empty($formData['tva_amount'])) {
            $amountTVA = $this->extractAmount($ocrText, 'TVA|Tva|Tax');
            $results['extracted_data']['amount_tva'] = $amountTVA;

            if ($amountTVA && $this->amountsMatch($amountTVA, $formData['tva_amount'])) {
                $results['matches'][] = 'Montant TVA';
            } elseif ($amountTVA) {
                $results['mismatches'][] = [
                    'field' => 'Montant TVA',
                    'expected' => $formData['tva_amount'],
                    'detected' => $amountTVA,
                ];
            }
        }

        // 5. Vérification du montant TTC (si fourni)
        if (! empty($formData['ttc_amount'])) {
            $amountTTC = $this->extractAmount($ocrText, 'TTC|Total|Somme');
            $results['extracted_data']['amount_ttc'] = $amountTTC;

            if ($amountTTC && $this->amountsMatch($amountTTC, $formData['ttc_amount'])) {
                $results['matches'][] = 'Montant TTC';
            } elseif ($amountTTC) {
                $results['mismatches'][] = [
                    'field' => 'Montant TTC',
                    'expected' => $formData['ttc_amount'],
                    'detected' => $amountTTC,
                ];
            }
        }

        // 6. Vérification du taux TVA (si fourni)
        if (! empty($formData['tva_rate'])) {
            $tvaRate = $this->extractTVARate($ocrText);
            $results['extracted_data']['tva_rate'] = $tvaRate;

            if ($tvaRate && abs($tvaRate - $formData['tva_rate']) < 2) { // Tolérance 2%
                $results['matches'][] = 'Taux TVA';
            } elseif ($tvaRate) {
                $results['mismatches'][] = [
                    'field' => 'Taux TVA',
                    'expected' => $formData['tva_rate'].'%',
                    'detected' => $tvaRate.'%',
                ];
            }
        }

        // 7. Vérification du partenaire (si fourni)
        if (! empty($formData['partner_name'])) {
            $partners = $this->extractPartnerNames($ocrText);
            $results['extracted_data']['partners'] = $partners;

            $partnerMatch = $this->findMatchingPartner($partners, $formData['partner_name']);
            if ($partnerMatch) {
                $results['matches'][] = 'Partenaire';
            } elseif (! empty($partners)) {
                $results['mismatches'][] = [
                    'field' => 'Partenaire',
                    'expected' => $formData['partner_name'],
                    'detected' => implode(', ', $partners),
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
            '/\b(?:facture|invoice)\s*(?:n[°ºo.]?|num(?:[ée]ro)?)\s*[:#-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu',
            '/\b(?:n[°ºo.]?|num(?:[ée]ro)?)\s*[:#-]?\s*([A-Z0-9]{2,}[A-Z0-9\-\/_.]*)/iu',
            '/\b(?:ref(?:erence)?|r[ée]f)\s*(?:n[°ºo.]?)?\s*[:#-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $candidate = trim((string) $matches[1]);
                if (! preg_match('/\d/u', $candidate)) {
                    continue;
                }

                return $candidate;
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
        $labelPattern = '(?:'.$labels.')';
        $amounts = [];
        $lines = preg_split('/\R/u', $text) ?: [];

        foreach ($lines as $index => $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || ! preg_match('/'.$labelPattern.'/iu', $candidate)) {
                continue;
            }

            $amounts = array_merge($amounts, $this->extractAmountsNearLine($lines, (int) $index));
        }

        return ! empty($amounts) ? max($amounts) : null;
    }

    /**
     * Cherche les montants sur la ligne courante puis sur les lignes suivantes
     * pour gérer les sorties OCR où le libellé et la valeur sont séparés.
     */
    private function extractAmountsNearLine(array $lines, int $index): array
    {
        $amounts = [];
        $maxLookahead = 2;
        $maxNonEmptyLines = 3;
        $visited = 0;

        for ($offset = 0; $offset <= $maxLookahead; $offset++) {
            $currentIndex = $index + $offset;
            if (! isset($lines[$currentIndex])) {
                break;
            }

            $candidate = trim((string) $lines[$currentIndex]);
            if ($candidate === '') {
                continue;
            }

            $visited++;
            if ($this->looksLikeSectionHeader($candidate) && $offset > 0) {
                break;
            }

            foreach ($this->extractNumericAmountsFromLine($candidate) as $amountData) {
                $amounts[] = $amountData['value'];
            }

            if (! empty($amounts) || $visited >= $maxNonEmptyLines) {
                break;
            }
        }

        return $amounts;
    }

    private function looksLikeSectionHeader(string $line): bool
    {
        if (preg_match('/^[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9\s\-:()%\/]{4,}$/u', $line)) {
            return true;
        }

        return (bool) preg_match('/\b(?:facture|description|quantit[eé]|prix|montant|total|sous-total|tva|taxe|client|fournisseur|date|r[ée]f)\b/iu', $line);
    }

    private function extractNumericAmountsFromLine(string $line): array
    {
        $amounts = [];
        if (! preg_match_all('/\b(?:\d{1,3}(?:[ \x{00A0}.]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)\b/u', $line, $matches)) {
            return $amounts;
        }

        foreach ((array) ($matches[0] ?? []) as $rawAmount) {
            $normalized = $this->normalizeSimpleAmount((string) $rawAmount);
            if ($normalized === null) {
                continue;
            }

            $amounts[] = [
                'raw' => trim((string) $rawAmount),
                'value' => $normalized,
            ];
        }

        return $amounts;
    }

    /**
     * Extraire le taux TVA
     */
    private function extractTVARate($text): ?float
    {
        $patterns = [
            '/(?:taux\s*)?TVA(?:\s*\(|[\s:])\s*(\d+(?:[.,]\d{1,2})?)\s*%/iu',
            '/\(\s*(\d+(?:[.,]\d{1,2})?)\s*%\s*\)\s*[:\-]?\s*[0-9]/iu',
            '/(?:Tax|TAX)[\s:]*(\d+(?:[.,]\d{1,2})?)\s*%/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return (float) str_replace(',', '.', $matches[1]);
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
        $parties = $this->extractDocumentParties($text);
        foreach ($parties as $party) {
            if ($party !== null) {
                $partners[] = $party;
            }
        }

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
     * Extrait les parties nommées pour éviter de confondre client et fournisseur.
     */
    private function extractDocumentParties(string $text): array
    {
        return [
            'supplier' => $this->extractLabeledParty($text, ['fournisseur', 'supplier', 'vendeur', 'vendor']),
            'client' => $this->extractLabeledParty($text, ['client', 'customer', 'destinataire']),
        ];
    }

    private function extractLabeledParty(string $text, array $labels): ?string
    {
        $escapedLabels = array_map(
            fn (string $label) => preg_quote($label, '/'),
            $labels
        );
        $pattern = '/\b(?:'.implode('|', $escapedLabels).')\b\s*[:\-]\s*([^\n\r]{3,120})/iu';

        if (! preg_match($pattern, $text, $matches)) {
            return null;
        }

        $candidate = trim((string) ($matches[1] ?? ''));
        $candidate = preg_replace('/\s{2,}/u', ' ', $candidate) ?? $candidate;
        $candidate = trim((string) preg_replace('/[;,.:\-]+$/u', '', $candidate));

        return $candidate !== '' ? $candidate : null;
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

        if ($str1 === $str2) {
            return true;
        }

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
