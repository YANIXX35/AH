<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;
use ZipArchive;

class CompanyDocumentParserService
{
    /**
     * Parse an uploaded file (Word, Excel, PDF, Text, CSV) and extract company/client fields.
     */
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $text = '';

        try {
            if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
                $text = $this->extractTextFromSpreadsheet($file->getPathname());
            } elseif ($extension === 'docx') {
                $text = $this->extractTextFromDocx($file->getPathname());
            } elseif ($extension === 'pdf') {
                $text = $this->extractTextFromPdf($file->getPathname());
            } else {
                // txt, doc, html or fallback raw text
                $text = @file_get_contents($file->getPathname()) ?: '';
            }
        } catch (Throwable $e) {
            $text = @file_get_contents($file->getPathname()) ?: '';
        }

        return $this->extractFieldsFromText($text);
    }

    private function extractTextFromSpreadsheet(string $filePath): string
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $lines = [];
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach ($sheet->toArray() as $row) {
                    $cleanRow = array_filter(array_map('trim', $row));
                    if (!empty($cleanRow)) {
                        $lines[] = implode(' : ', $cleanRow);
                    }
                }
            }
            return implode("\n", $lines);
        } catch (Throwable $e) {
            return '';
        }
    }

    private function extractTextFromDocx(string $filePath): string
    {
        try {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $data = $zip->getFromIndex($index);
                    $zip->close();
                    $data = preg_replace('/<w:p[^>]*>/', "\n", $data);
                    return trim(strip_tags($data));
                }
                $zip->close();
            }
        } catch (Throwable $e) {}
        return '';
    }

    private function extractTextFromPdf(string $filePath): string
    {
        $content = @file_get_contents($filePath) ?: '';
        preg_match_all('/(BT[\s\S]*?ET)/', $content, $matches);
        if (!empty($matches[0])) {
            $rawText = implode(' ', $matches[0]);
            return preg_replace('/[^\w\s@.+:-]/u', ' ', strip_tags($rawText));
        }
        return preg_replace('/[^\w\s@.+:-]/u', ' ', $content);
    }

    /**
     * Extract target form fields using regex pattern matching.
     */
    public function extractFieldsFromText(string $text): array
    {
        $fields = [
            'name' => null,
            'email' => null,
            'phone' => null,
            'company_name' => null,
            'company_sigle' => null,
            'company_tax_id' => null,
            'rccm' => null,
            'sector' => null,
            'city' => null,
            'address' => null,
        ];

        if (empty(trim($text))) {
            return [];
        }

        $lines = array_map('trim', explode("\n", $text));

        // 1. Email extraction
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $match)) {
            $fields['email'] = strtolower(trim($match[0]));
        }

        // 2. Phone extraction
        if (preg_match('/(?:\+?\d{1,3}[\s.-]*)?(?:\d{2}[\s.-]*){4,5}/', $text, $match)) {
            $phone = trim($match[0]);
            if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                $fields['phone'] = $phone;
            }
        }

        // 3. Line-by-line label mapping
        foreach ($lines as $line) {
            if (empty($line)) continue;

            $parts = preg_split('/[:;\t=]/', $line, 2);
            $label = strtolower(trim($parts[0] ?? ''));
            $val = trim($parts[1] ?? '');

            // Name (Dirigeant)
            if (is_null($fields['name']) && (str_contains($label, 'dirigeant') || str_contains($label, 'gerant') || str_contains($label, 'gérant') || str_contains($label, 'representant') || str_contains($label, 'nom complet') || $label === 'nom')) {
                if (!empty($val)) $fields['name'] = $val;
            }

            // Company Name
            if (is_null($fields['company_name']) && (str_contains($label, 'raison sociale') || str_contains($label, 'entreprise') || str_contains($label, 'societe') || str_contains($label, 'société') || str_contains($label, 'pme') || str_contains($label, 'etablissement') || str_contains($label, 'compagnie'))) {
                if (!empty($val)) $fields['company_name'] = $val;
            }

            // Sigle
            if (is_null($fields['company_sigle']) && (str_contains($label, 'sigle') || str_contains($label, 'acronyme'))) {
                if (!empty($val)) $fields['company_sigle'] = strtoupper($val);
            }

            // NIF / Tax ID
            if (is_null($fields['company_tax_id']) && (str_contains($label, 'nif') || str_contains($label, 'matricule fiscal') || str_contains($label, 'tax id') || str_contains($label, 'ifu') || str_contains($label, 'ncc'))) {
                if (!empty($val)) $fields['company_tax_id'] = strtoupper($val);
            }

            // RCCM / SIRET
            if (is_null($fields['rccm']) && (str_contains($label, 'rccm') || str_contains($label, 'siret') || str_contains($label, 'siren') || str_contains($label, 'registre de commerce'))) {
                if (!empty($val)) $fields['rccm'] = $val;
            }

            // Sector
            if (is_null($fields['sector']) && (str_contains($label, 'secteur') || str_contains($label, 'activite') || str_contains($label, 'activité') || str_contains($label, 'domaine'))) {
                if (!empty($val)) $fields['sector'] = $val;
            }

            // City
            if (is_null($fields['city']) && (str_contains($label, 'ville') || str_contains($label, 'commune') || str_contains($label, 'localite'))) {
                if (!empty($val)) $fields['city'] = $val;
            }

            // Address
            if (is_null($fields['address']) && (str_contains($label, 'adresse') || str_contains($label, 'siege') || str_contains($label, 'siège') || str_contains($label, 'localisation'))) {
                if (!empty($val)) $fields['address'] = $val;
            }
        }

        // Fallbacks if company_name not found by label, look for legal form keywords
        if (is_null($fields['company_name'])) {
            foreach ($lines as $line) {
                if (preg_match('/\b(sarl|sas|sa|suarl|eurl|gie|inc|ltd)\b/i', $line)) {
                    $fields['company_name'] = trim(preg_replace('/^(entreprise|societe|raison sociale)[:\s]*/i', '', $line));
                    break;
                }
            }
        }

        return array_filter($fields, fn ($v) => !is_null($v) && trim((string)$v) !== '');
    }
}
