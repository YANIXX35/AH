<?php

namespace Tests\Unit;

use App\Services\CompanyDocumentParserService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class CompanyDocumentParserServiceTest extends TestCase
{
    private CompanyDocumentParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CompanyDocumentParserService;
    }

    public function test_extracts_fields_from_simple_label_value_lines(): void
    {
        $text = "Dirigeant: Jean Kouassi\n"
            ."Raison sociale: Ivoire Agro SARL\n"
            ."NIF: CI-2026-12345\n"
            ."Ville: Abidjan\n"
            ."Email de contact: jean@ivoireagro.ci";

        $fields = $this->parser->extractFieldsFromText($text);

        $this->assertSame('Jean Kouassi', $fields['name']);
        $this->assertSame('Ivoire Agro SARL', $fields['company_name']);
        $this->assertSame('CI-2026-12345', $fields['company_tax_id']);
        $this->assertSame('Abidjan', $fields['city']);
        $this->assertSame('jean@ivoireagro.ci', $fields['email']);
    }

    public function test_extracts_fields_from_tab_separated_table_cells(): void
    {
        // Simule ce que produit désormais l'extraction d'un tableau Word :
        // cellules séparées par une tabulation, une ligne par rangée.
        $text = "Nom\tJean Kouassi\n"
            ."Raison Sociale\tIvoire Agro SARL\n"
            ."RCCM\tCI-ABJ-2026-B-1234\n"
            ."Boite Postale\t01 BP 123 Abidjan";

        $fields = $this->parser->extractFieldsFromText($text);

        $this->assertSame('Jean Kouassi', $fields['name']);
        $this->assertSame('Ivoire Agro SARL', $fields['company_name']);
        $this->assertSame('CI-ABJ-2026-B-1234', $fields['rccm']);
        $this->assertSame('01 BP 123 Abidjan', $fields['address']);
    }

    public function test_returns_empty_array_for_blank_text(): void
    {
        $this->assertSame([], $this->parser->extractFieldsFromText(''));
        $this->assertSame([], $this->parser->extractFieldsFromText('   '));
    }

    public function test_parses_a_real_docx_table_end_to_end(): void
    {
        $docxPath = $this->buildMinimalDocxWithTable();

        $file = new UploadedFile($docxPath, 'fiche.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

        $fields = $this->parser->parse($file);

        $this->assertSame('Jean Kouassi', $fields['name']);
        $this->assertSame('Ivoire Agro SARL', $fields['company_name']);

        @unlink($docxPath);
    }

    /**
     * Construit un .docx minimal mais valide contenant un tableau à deux
     * colonnes (Libellé / Valeur), pour tester l'extraction réelle sans
     * dépendre d'un fichier fixture externe.
     */
    private function buildMinimalDocxWithTable(): string
    {
        $documentXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
<w:tbl>
<w:tr><w:tc><w:p><w:r><w:t>Nom</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Jean Kouassi</w:t></w:r></w:p></w:tc></w:tr>
<w:tr><w:tc><w:p><w:r><w:t>Raison Sociale</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Ivoire Agro SARL</w:t></w:r></w:p></w:tc></w:tr>
</w:tbl>
</w:body>
</w:document>
XML;

        $path = tempnam(sys_get_temp_dir(), 'docxtest').'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $path;
    }
}
