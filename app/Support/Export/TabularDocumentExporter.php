<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV / Excel / Word générique pour un document "fiche" — un bloc de
 * résumé (paires libellé/valeur) suivi d'un tableau. Réutilisé par les
 * fiches stock et les factures pour éviter de dupliquer PhpSpreadsheet/
 * PhpWord dans chaque contrôleur. Le PDF reste spécifique à chaque document
 * (mise en page soignée via une vue Blade dédiée), ce générateur ne couvre
 * que les trois formats tabulaires.
 */
class TabularDocumentExporter
{
    private const NAVY = '0F2747';

    private const SLATE_BG = 'F1F5F9';

    private const BORDER = 'D9DEE7';

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $summary
     * @param  array<int, string>  $tableHeaders
     * @param  array<int, array<int, mixed>>  $tableRows
     */
    public function csv(string $filename, string $title, array $summary, array $tableHeaders, array $tableRows): StreamedResponse
    {
        $callback = function () use ($title, $summary, $tableHeaders, $tableRows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : garantit un affichage correct des accents dans Excel.
            fputcsv($out, [$title], ';');
            fputcsv($out, [], ';');
            foreach ($summary as [$label, $value]) {
                fputcsv($out, [$label, $value], ';');
            }
            fputcsv($out, [], ';');
            fputcsv($out, $tableHeaders, ';');
            foreach ($tableRows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.csv"',
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $summary
     * @param  array<int, string>  $tableHeaders
     * @param  array<int, array<int, mixed>>  $tableRows
     */
    public function excel(string $filename, string $title, array $summary, array $tableHeaders, array $tableRows, ?string $logoPath = null): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));
        $lastCol = $this->columnLetter(max(count($tableHeaders), 2));

        $row = 1;
        if ($logoPath) {
            $drawing = new Drawing();
            $drawing->setPath($logoPath);
            $drawing->setHeight(48);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(2);
            $drawing->setOffsetY(2);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension(1)->setRowHeight(38);
            $row = 3;
        }

        $sheet->setCellValue('A'.$row, $title);
        $sheet->mergeCells('A'.$row.':'.$lastCol.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::NAVY);
        $row += 2;

        foreach ($summary as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SLATE_BG);
            $sheet->getStyle('A'.$row.':B'.$row)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
            $row++;
        }

        $row++;
        $headerRow = $row;
        foreach ($tableHeaders as $i => $header) {
            $sheet->setCellValue($this->columnLetter($i + 1).$headerRow, $header);
        }
        $headerRange = 'A'.$headerRow.':'.$this->columnLetter(count($tableHeaders)).$headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);

        $dataRow = $headerRow + 1;
        foreach ($tableRows as $tableRow) {
            foreach ($tableRow as $i => $value) {
                $sheet->setCellValue($this->columnLetter($i + 1).$dataRow, $value);
            }
            $rowRange = 'A'.$dataRow.':'.$this->columnLetter(count($tableHeaders)).$dataRow;
            $sheet->getStyle($rowRange)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::BORDER);
            if ($dataRow % 2 === 0) {
                $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::SLATE_BG);
            }
            $dataRow++;
        }

        foreach (range(1, count($tableHeaders)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $sheet->getColumnDimension('B')->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: mixed}>  $summary
     * @param  array<int, string>  $tableHeaders
     * @param  array<int, array<int, mixed>>  $tableRows
     */
    public function word(string $filename, string $title, array $summary, array $tableHeaders, array $tableRows, ?string $logoPath = null): StreamedResponse
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('DejaVu Sans');
        $phpWord->setDefaultFontSize(10);
        $section = $phpWord->addSection(['marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900]);

        if ($logoPath) {
            $section->addImage($logoPath, ['height' => 50, 'width' => 50, 'wrappingStyle' => 'inline']);
        }

        $section->addText($title, ['bold' => true, 'size' => 16, 'color' => self::NAVY]);
        $section->addTextBreak(1);

        $summaryTable = $section->addTable([
            'borderSize' => 4, 'borderColor' => self::BORDER, 'cellMargin' => 100, 'width' => 100 * 50, 'unit' => 'pct',
        ]);
        foreach ($summary as [$label, $value]) {
            $summaryTable->addRow();
            $summaryTable->addCell(3200, ['bgColor' => self::SLATE_BG])->addText((string) $label, ['bold' => true, 'color' => self::NAVY]);
            $summaryTable->addCell(4800)->addText((string) $value);
        }

        $section->addTextBreak(1);
        $section->addText('Détail', ['bold' => true, 'size' => 12, 'color' => self::NAVY]);

        $colWidth = (int) floor(9000 / max(1, count($tableHeaders)));
        $dataTable = $section->addTable(['borderSize' => 4, 'borderColor' => self::BORDER, 'cellMargin' => 90]);
        $dataTable->addRow();
        foreach ($tableHeaders as $header) {
            $dataTable->addCell($colWidth, ['bgColor' => self::NAVY, 'valign' => 'center'])
                ->addText($header, ['bold' => true, 'color' => 'FFFFFF', 'size' => 9]);
        }
        foreach ($tableRows as $index => $tableRow) {
            $dataTable->addRow();
            $bg = $index % 2 === 1 ? self::SLATE_BG : 'FFFFFF';
            foreach ($tableRow as $value) {
                $dataTable->addCell($colWidth, ['bgColor' => $bg])->addText((string) $value, ['size' => 9]);
            }
        }

        return response()->streamDownload(function () use ($phpWord) {
            $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        }, $filename.'.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    private function columnLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }
}
