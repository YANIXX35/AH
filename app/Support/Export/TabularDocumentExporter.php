<?php

namespace App\Support\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
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
    public function excel(string $filename, string $title, array $summary, array $tableHeaders, array $tableRows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($title, 0, 31));

        $sheet->setCellValue('A1', $title);
        $lastCol = $this->columnLetter(count($tableHeaders));
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $row = 3;
        foreach ($summary as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $headerRow = $row + 1;
        foreach ($tableHeaders as $i => $header) {
            $sheet->setCellValue($this->columnLetter($i + 1).$headerRow, $header);
        }
        $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('0F2747');
        $sheet->getStyle('A'.$headerRow.':'.$lastCol.$headerRow)->getFont()->getColor()->setRGB('FFFFFF');

        $dataRow = $headerRow + 1;
        foreach ($tableRows as $tableRow) {
            foreach ($tableRow as $i => $value) {
                $sheet->setCellValue($this->columnLetter($i + 1).$dataRow, $value);
            }
            $dataRow++;
        }

        foreach (range(1, count($tableHeaders)) as $i) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

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
    public function word(string $filename, string $title, array $summary, array $tableHeaders, array $tableRows): StreamedResponse
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText($title, ['bold' => true, 'size' => 16]);
        $section->addTextBreak(1);

        $summaryTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'D9DEE7', 'cellMargin' => 80]);
        foreach ($summary as [$label, $value]) {
            $summaryTable->addRow();
            $summaryTable->addCell(3200)->addText((string) $label, ['bold' => true]);
            $summaryTable->addCell(4800)->addText((string) $value);
        }

        $section->addTextBreak(1);

        $colWidth = (int) floor(9000 / max(1, count($tableHeaders)));
        $dataTable = $section->addTable(['borderSize' => 6, 'borderColor' => 'D9DEE7', 'cellMargin' => 80]);
        $dataTable->addRow();
        foreach ($tableHeaders as $header) {
            $dataTable->addCell($colWidth, ['bgColor' => '0F2747'])->addText($header, ['bold' => true, 'color' => 'FFFFFF']);
        }
        foreach ($tableRows as $tableRow) {
            $dataTable->addRow();
            foreach ($tableRow as $value) {
                $dataTable->addCell($colWidth)->addText((string) $value);
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
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }
}
