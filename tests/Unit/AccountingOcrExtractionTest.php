<?php

namespace Tests\Unit;

use App\Http\Controllers\AccountingController;
use App\Services\OcrService;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use Tests\TestCase;

class AccountingOcrExtractionTest extends TestCase
{
    public function test_purchase_invoice_extraction_prefers_supplier_and_amount_totals(): void
    {
        $text = <<<'TEXT'
FACTURE D'ACHAT
N° FAC-ACHAT-2026-001

Fournisseur: SARL TECHNO SUPPLY
Adresse: 123 Rue de l'Industrie, Dakar, Sénégal
Téléphone: +221 33 123 45 67
Email: contact@technosupply.sn

Client: Sitiam Capitale
Adresse: 456 Avenue des Affaires, Dakar, Sénégal

Date de facture: 07/04/2026
Date d'échéance: 07/05/2026

DESIGNATION                    QUANTITE    PRIX UNIT    MONTANT HT
Ordinateur portable Dell       2           750000      1500000
Clavier mécanique Logitech     5           25000       125000
Souris optique                 10          5000        50000

SOUS-TOTAL HT:                 1675000 FCFA
TVA (18%):                     301500 FCFA
TOTAL TTC:                     1976500 FCFA
TEXT;

        $ocrService = new OcrService;
        $richData = $ocrService->extractRichDocumentData($text);

        $this->assertSame('FAC-ACHAT-2026-001', $richData['primary']['invoice_number']);
        $this->assertSame('SARL TECHNO SUPPLY', $richData['primary']['supplier_name']);
        $this->assertSame('Sitiam Capitale', $richData['primary']['client_name']);
        $this->assertSame('SARL TECHNO SUPPLY', $richData['primary']['partner_name']);
        $this->assertSame(1675000.0, (float) $richData['primary']['amount_ht']);
        $this->assertSame(301500.0, (float) $richData['primary']['amount_tva']);
        $this->assertSame(1976500.0, (float) $richData['primary']['amount_ttc']);
        $this->assertSame(18.0, (float) $richData['primary']['tva_rate']);

        $controller = new AccountingController;
        $reflection = new ReflectionClass($controller);

        $detectType = $reflection->getMethod('detectDocumentTypeFromOcrText');
        $detectType->setAccessible(true);
        $documentType = $detectType->invoke($controller, $text, 'Justificatif');
        $this->assertSame('Achat', $documentType);

        $buildValidation = $reflection->getMethod('buildValidationExtractedData');
        $buildValidation->setAccessible(true);
        $normalized = $buildValidation->invoke($controller, $text, [], $richData, $documentType);

        $this->assertSame('SARL TECHNO SUPPLY', $normalized['partner']);
        $this->assertSame('FAC-ACHAT-2026-001', $normalized['invoice_number']);
        $this->assertSame('2026-04-07', $normalized['invoice_date']);
        $this->assertSame(1675000.0, (float) $normalized['amount_ht']);
        $this->assertSame(301500.0, (float) $normalized['tva']);
        $this->assertSame(1976500.0, (float) $normalized['amount_ttc']);
    }

    public function test_local_paddleocr_runner_contract_is_normalized(): void
    {
        $relativePath = 'tests/local-runner-success.pdf';
        $absolutePath = $this->createDummyPdf($relativePath);
        $runnerPath = base_path('tests/Fixtures/fake_paddle_runner_success.py');

        File::ensureDirectoryExists(dirname($runnerPath));
        file_put_contents(
            $runnerPath,
            <<<'PY'
import json
import sys

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

print(json.dumps({
    "success": True,
    "message": "OCR local simulé",
    "text": "FACTURE TEST\nTOTAL TTC: 1200 FCFA",
    "confidence": 88.5,
    "mode": "cpu",
    "raw_response": {"pages": [{"page_index": 0, "text": "FACTURE TEST"}]}
}, ensure_ascii=False))
PY
        );

        config([
            'services.paddle_ocr.enabled' => true,
            'services.paddle_ocr.python_path' => 'python',
            'services.paddle_ocr.runner_path' => $runnerPath,
            'services.paddle_ocr.timeout' => 30,
            'services.paddle_ocr.preferred_device' => 'cpu',
            'services.paddle_ocr.fallback_to_cpu' => true,
            'services.paddle_ocr.page_num' => 0,
            'services.paddle_ocr.language' => 'fr',
            'services.paddle_ocr.max_file_size_kb' => 20480,
        ]);

        $service = new OcrService;
        $result = $service->extractText($relativePath);

        $this->assertTrue($result['success']);
        $this->assertSame('FACTURE TEST'."\n".'TOTAL TTC: 1200 FCFA', $result['text']);
        $this->assertSame(88.5, (float) $result['confidence']);
        $this->assertSame('local_paddleocr_runner', $result['endpoint']);
        $this->assertSame('cpu', $result['mode']);

        File::delete($absolutePath);
        File::delete($runnerPath);
    }

    public function test_amounts_are_extracted_when_label_and_value_are_on_separate_lines(): void
    {
        $text = <<<'TEXT'
FACTURE
BURINFORT
N° FACTURE
2034
DATE
21/02/2018
DESCRIPTION
QTÉ
PRIX UNITAIRE
MONTANT
Frais de service
1
200,00 F CFA
200,00 F CFA
Main-d'œuvre : 5 heures à 75 €/h
5
75,00 F CFA
375,00 F CFA
SOUS-TOTAL
525,00 F CFA
TAUX TVA
18%
TAXE
94,50 F CFA
TOTAL
619,50 F CFA
TEXT;

        $ocrService = new OcrService;
        $richData = $ocrService->extractRichDocumentData($text);

        $this->assertSame(525.0, (float) $richData['primary']['amount_ht']);
        $this->assertSame(94.5, (float) $richData['primary']['amount_tva']);
        $this->assertSame(619.5, (float) $richData['primary']['amount_ttc']);
        $this->assertSame(18.0, (float) $richData['primary']['tva_rate']);

        $controller = new AccountingController;
        $reflection = new ReflectionClass($controller);
        $buildValidation = $reflection->getMethod('buildValidationExtractedData');
        $buildValidation->setAccessible(true);

        $normalized = $buildValidation->invoke($controller, $text, [], $richData, 'Achat');

        $this->assertSame(525.0, (float) $normalized['amount_ht']);
        $this->assertSame(94.5, (float) $normalized['tva']);
        $this->assertSame(619.5, (float) $normalized['amount_ttc']);
    }

    public function test_local_paddleocr_returns_clear_error_when_disabled(): void
    {
        $relativePath = 'tests/local-runner-disabled.pdf';
        $absolutePath = $this->createDummyPdf($relativePath);

        config([
            'services.paddle_ocr.enabled' => false,
        ]);

        $service = new OcrService;
        $result = $service->extractText($relativePath);

        $this->assertFalse($result['success']);
        $this->assertSame('LOCAL_OCR_DISABLED', $result['error_code']);
        $this->assertSame('local_paddleocr_runner', $result['endpoint']);

        File::delete($absolutePath);
    }

    private function createDummyPdf(string $relativePath): string
    {
        $absolutePath = storage_path('app/public/'.$relativePath);
        File::ensureDirectoryExists(dirname($absolutePath));

        file_put_contents(
            $absolutePath,
            "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF"
        );

        return $absolutePath;
    }
}
