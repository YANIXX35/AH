<?php

namespace App\Services;

use App\Support\Scoring360Defaults;
use PhpOffice\PhpSpreadsheet\IOFactory;

class Scoring360ExcelImporter
{
    /**
     * Importe les paramètres de scoring/décision depuis le classeur Excel.
     *
     * Lecture des cellules attendues (onglets Parametres / Score_Interne).
     *
     * @return array<string, mixed>
     */
    public function import(string $path, ?string $originalName = null): array
    {
        $config = Scoring360Defaults::defaults();

        $spreadsheet = IOFactory::load($path);

        $param = $spreadsheet->getSheetByName('Parametres');
        if ($param !== null) {
            // Seuils Banque (B5:B14) et Investisseur (F5:F14)
            $config['bank']['thresholds']['dscr']['strong'] = $this->num($param->getCell('B5')->getValue());
            $config['bank']['thresholds']['dscr']['medium'] = $this->num($param->getCell('B6')->getValue());
            $config['bank']['thresholds']['interest_coverage']['strong'] = $this->num($param->getCell('B7')->getValue());
            $config['bank']['thresholds']['interest_coverage']['medium'] = $this->num($param->getCell('B8')->getValue());
            $config['bank']['thresholds']['current_ratio']['strong'] = $this->num($param->getCell('B9')->getValue());
            $config['bank']['thresholds']['current_ratio']['medium'] = $this->num($param->getCell('B10')->getValue());
            $config['bank']['thresholds']['debt_asset']['strong'] = $this->num($param->getCell('B11')->getValue());
            $config['bank']['thresholds']['debt_asset']['medium'] = $this->num($param->getCell('B12')->getValue());
            $config['bank']['thresholds']['bfr_days']['strong'] = $this->num($param->getCell('B13')->getValue());
            $config['bank']['thresholds']['bfr_days']['medium'] = $this->num($param->getCell('B14')->getValue());

            $config['investor']['thresholds']['revenue_growth']['strong'] = $this->num($param->getCell('F5')->getValue());
            $config['investor']['thresholds']['revenue_growth']['medium'] = $this->num($param->getCell('F6')->getValue());
            $config['investor']['thresholds']['ebitda_margin']['strong'] = $this->num($param->getCell('F7')->getValue());
            $config['investor']['thresholds']['ebitda_margin']['medium'] = $this->num($param->getCell('F8')->getValue());
            $config['investor']['thresholds']['roe']['strong'] = $this->num($param->getCell('F9')->getValue());
            $config['investor']['thresholds']['roe']['medium'] = $this->num($param->getCell('F10')->getValue());
            $config['investor']['thresholds']['fcf_margin']['strong'] = $this->num($param->getCell('F11')->getValue());
            $config['investor']['thresholds']['fcf_margin']['medium'] = $this->num($param->getCell('F12')->getValue());
            $config['investor']['thresholds']['asset_turnover']['strong'] = $this->num($param->getCell('F13')->getValue());
            $config['investor']['thresholds']['asset_turnover']['medium'] = $this->num($param->getCell('F14')->getValue());

            // Poids Banque (B19:B23) / Investisseur (F19:F23)
            $config['bank']['weights']['dscr'] = $this->num($param->getCell('B19')->getValue());
            $config['bank']['weights']['interest_coverage'] = $this->num($param->getCell('B20')->getValue());
            $config['bank']['weights']['current_ratio'] = $this->num($param->getCell('B21')->getValue());
            $config['bank']['weights']['debt_asset'] = $this->num($param->getCell('B22')->getValue());
            $config['bank']['weights']['bfr_days'] = $this->num($param->getCell('B23')->getValue());

            $config['investor']['weights']['revenue_growth'] = $this->num($param->getCell('F19')->getValue());
            $config['investor']['weights']['ebitda_margin'] = $this->num($param->getCell('F20')->getValue());
            $config['investor']['weights']['roe'] = $this->num($param->getCell('F21')->getValue());
            $config['investor']['weights']['fcf_margin'] = $this->num($param->getCell('F22')->getValue());
            $config['investor']['weights']['asset_turnover'] = $this->num($param->getCell('F23')->getValue());

            // Poids composite (B32:B34)
            $config['composite']['weights']['bank'] = $this->num($param->getCell('B32')->getValue());
            $config['composite']['weights']['investor'] = $this->num($param->getCell('B33')->getValue());
            $config['composite']['weights']['internal'] = $this->num($param->getCell('B34')->getValue());
        }

        // Paramètres internes : onglet Score_Interne (seuils/poids).
        $internal = $spreadsheet->getSheetByName('Score_Interne');
        if ($internal !== null) {
            $config['internal']['weights']['net_margin'] = $this->num($internal->getCell('C4')->getValue());
            $config['internal']['thresholds']['net_margin']['strong'] = $this->num($internal->getCell('D4')->getValue());
            $config['internal']['thresholds']['net_margin']['medium'] = $this->num($internal->getCell('E4')->getValue());

            $config['internal']['weights']['quick_ratio'] = $this->num($internal->getCell('C5')->getValue());
            $config['internal']['thresholds']['quick_ratio']['strong'] = $this->num($internal->getCell('D5')->getValue());
            $config['internal']['thresholds']['quick_ratio']['medium'] = $this->num($internal->getCell('E5')->getValue());

            $config['internal']['weights']['receivable_days'] = $this->num($internal->getCell('C6')->getValue());
            $config['internal']['thresholds']['receivable_days']['strong'] = $this->num($internal->getCell('D6')->getValue());
            $config['internal']['thresholds']['receivable_days']['medium'] = $this->num($internal->getCell('E6')->getValue());

            $config['internal']['weights']['inventory_days'] = $this->num($internal->getCell('C7')->getValue());
            $config['internal']['thresholds']['inventory_days']['strong'] = $this->num($internal->getCell('D7')->getValue());
            $config['internal']['thresholds']['inventory_days']['medium'] = $this->num($internal->getCell('E7')->getValue());

            $config['internal']['weights']['ebitda_growth'] = $this->num($internal->getCell('C8')->getValue());
            $config['internal']['thresholds']['ebitda_growth']['strong'] = $this->num($internal->getCell('D8')->getValue());
            $config['internal']['thresholds']['ebitda_growth']['medium'] = $this->num($internal->getCell('E8')->getValue());
        }

        $config['meta']['imported_at'] = now()->toIso8601String();
        if ($originalName) {
            $config['meta']['imported_from'] = $originalName;
        }

        return $config;
    }

    private function num(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '.', (string) $value);
    }
}
