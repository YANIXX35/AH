<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;
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
                    if (! empty($cleanRow)) {
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
            $zip = new ZipArchive;
            if ($zip->open($filePath) === true) {
                if (($index = $zip->locateName('word/document.xml')) !== false) {
                    $data = $zip->getFromIndex($index);
                    $zip->close();

                    // Dans un tableau Word, "Nom" et "Jean Kouassi" sont deux cellules
                    // (<w:tc>) séparées, sans texte entre elles : sans traitement dédié,
                    // elles ressortiraient collées ou sur des lignes séparées, cassant
                    // la détection "Libellé : Valeur". On les regroupe sur une seule
                    // ligne, séparées par une tabulation, une ligne par rangée (<w:tr>).
                    $data = preg_replace_callback('/<w:tbl\b.*?<\/w:tbl>/s', function ($m) {
                        $table = preg_replace('/<\/w:tc>/', "\t", $m[0]);
                        $table = preg_replace('/<\/w:tr>/', "\n", $table);

                        return preg_replace('/<w:p[^>]*>/', '', $table);
                    }, $data);

                    // Paragraphes hors tableau : un saut de ligne par paragraphe.
                    $data = preg_replace('/<w:p[^>]*>/', "\n", $data);

                    return trim(strip_tags($data));
                }
                $zip->close();
            }
        } catch (Throwable $e) {
        }

        return '';
    }

    private function extractTextFromPdf(string $filePath): string
    {
        try {
            $pdf = (new PdfParser)->parseFile($filePath);
            $text = trim($pdf->getText());
            if ($text !== '') {
                return $text;
            }
        } catch (Throwable $e) {
            // PDF chiffré, corrompu ou non standard : on retente le fallback ci-dessous.
        }

        // Fallback pour les rares PDF non compressés que smalot/pdfparser ne lirait pas.
        $content = @file_get_contents($filePath) ?: '';
        preg_match_all('/(BT[\s\S]*?ET)/', $content, $matches);
        if (! empty($matches[0])) {
            $rawText = implode(' ', $matches[0]);

            return preg_replace('/[^\w\s@.+:-]/u', ' ', strip_tags($rawText));
        }

        return '';
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

        // 2. Line-by-line label mapping
        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            $parts = preg_split('/[:;\t=]/', $line, 2);
            $label = strtolower(trim($parts[0] ?? ''));
            $val = trim($parts[1] ?? '');

            // Téléphone (priorité à une ligne explicitement libellée, pour éviter
            // de confondre un NIF/RCCM avec un numéro de téléphone).
            if (is_null($fields['phone']) && (str_contains($label, 'telephone') || str_contains($label, 'téléphone') || str_contains($label, 'tel') || str_contains($label, 'gsm') || str_contains($label, 'mobile') || str_contains($label, 'contact'))) {
                if (! empty($val) && strlen(preg_replace('/\D/', '', $val)) >= 8) {
                    $fields['phone'] = $val;
                }
            }

            // Name (Dirigeant)
            if (is_null($fields['name']) && (str_contains($label, 'dirigeant') || str_contains($label, 'gerant') || str_contains($label, 'gérant') || str_contains($label, 'representant') || str_contains($label, 'nom complet') || $label === 'nom')) {
                if (! empty($val)) {
                    $fields['name'] = $val;
                }
            }

            // Company Name
            if (is_null($fields['company_name']) && (str_contains($label, 'raison sociale') || str_contains($label, 'entreprise') || str_contains($label, 'societe') || str_contains($label, 'société') || str_contains($label, 'pme') || str_contains($label, 'etablissement') || str_contains($label, 'compagnie'))) {
                if (! empty($val)) {
                    $fields['company_name'] = $val;
                }
            }

            // Sigle
            if (is_null($fields['company_sigle']) && (str_contains($label, 'sigle') || str_contains($label, 'acronyme'))) {
                if (! empty($val)) {
                    $fields['company_sigle'] = strtoupper($val);
                }
            }

            // NIF / Tax ID
            if (is_null($fields['company_tax_id']) && (str_contains($label, 'nif') || str_contains($label, 'matricule fiscal') || str_contains($label, 'tax id') || str_contains($label, 'ifu') || str_contains($label, 'ncc'))) {
                if (! empty($val)) {
                    $fields['company_tax_id'] = strtoupper($val);
                }
            }

            // RCCM / SIRET
            if (is_null($fields['rccm']) && (str_contains($label, 'rccm') || str_contains($label, 'siret') || str_contains($label, 'siren') || str_contains($label, 'registre de commerce'))) {
                if (! empty($val)) {
                    $fields['rccm'] = $val;
                }
            }

            // Sector
            if (is_null($fields['sector']) && (str_contains($label, 'secteur') || str_contains($label, 'activite') || str_contains($label, 'activité') || str_contains($label, 'domaine'))) {
                if (! empty($val)) {
                    $fields['sector'] = $val;
                }
            }

            // City
            if (is_null($fields['city']) && (str_contains($label, 'ville') || str_contains($label, 'commune') || str_contains($label, 'localite'))) {
                if (! empty($val)) {
                    $fields['city'] = $val;
                }
            }

            // Address
            if (is_null($fields['address']) && (str_contains($label, 'adresse') || str_contains($label, 'siege') || str_contains($label, 'siège') || str_contains($label, 'localisation') || str_contains($label, 'boite postale') || str_contains($label, 'boîte postale') || $label === 'bp')) {
                if (! empty($val)) {
                    $fields['address'] = $val;
                }
            }
        }

        // Repli : aucune ligne "Téléphone :" trouvée, on cherche un numéro plausible
        // n'importe où dans le texte (moins fiable, donc en dernier recours seulement).
        if (is_null($fields['phone']) && preg_match('/(?:\+?\d{1,3}[\s.-]*)?(?:\d{2}[\s.-]*){4,5}/', $text, $match)) {
            $phone = trim($match[0]);
            if (strlen(preg_replace('/\D/', '', $phone)) >= 8) {
                $fields['phone'] = $phone;
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

        return array_filter($fields, fn ($v) => ! is_null($v) && trim((string) $v) !== '');
    }
}
