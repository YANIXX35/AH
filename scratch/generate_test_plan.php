<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

$accounts = [
    // CLASSE 1 : COMPTES DE RESSOURCES DURABLES
    ['101000', 'Capital social', 'Passif', 'Capitaux Propres', '1'],
    ['102000', 'Capital par dotation', 'Passif', 'Capitaux Propres', '1'],
    ['111000', 'Réserve légale', 'Passif', 'Capitaux Propres', '1'],
    ['112000', 'Réserves statutaires ou contractuelles', 'Passif', 'Capitaux Propres', '1'],
    ['121000', 'Report à nouveau créditeur', 'Passif', 'Capitaux Propres', '1'],
    ['131000', 'Résultat net de l\'exercice (Bénéfice)', 'Passif', 'Capitaux Propres', '1'],
    ['162000', 'Emprunts auprès des établissements de crédit', 'Passif', 'Dettes Financières', '1'],
    ['164000', 'Avances reçues de l\'État', 'Passif', 'Dettes Financières', '1'],

    // CLASSE 2 : COMPTES D'ACTIF IMMOBILISÉ
    ['211000', 'Terrains nus', 'Actif', 'Immobilisations Incorporelles', '2'],
    ['212000', 'Bâtiments et installations complexes', 'Actif', 'Immobilisations Corporelles', '2'],
    ['231000', 'Bâtiments en cours', 'Actif', 'Immobilisations Corporelles', '2'],
    ['241000', 'Matériel et outillage industriel', 'Actif', 'Immobilisations Corporelles', '2'],
    ['244000', 'Matériel informatique', 'Actif', 'Immobilisations Corporelles', '2'],
    ['245000', 'Matériel de transport', 'Actif', 'Immobilisations Corporelles', '2'],
    ['271000', 'Titres de participation', 'Actif', 'Immobilisations Financières', '2'],
    ['284100', 'Amortissements du matériel industriel', 'Actif', 'Amortissements', '2'],
    ['284500', 'Amortissements du matériel de transport', 'Actif', 'Amortissements', '2'],

    // CLASSE 3 : COMPTES DE STOCKS
    ['311000', 'Marchandises A', 'Actif', 'Stocks', '3'],
    ['312000', 'Marchandises B', 'Actif', 'Stocks', '3'],
    ['321000', 'Matières premières', 'Actif', 'Stocks', '3'],
    ['335000', 'Emballages', 'Actif', 'Stocks', '3'],
    ['361000', 'Produits finis', 'Actif', 'Stocks', '3'],

    // CLASSE 4 : COMPTES DE TIERS
    ['401100', 'Fournisseurs d\'exploitation', 'Passif', 'Passif Circulant', '4'],
    ['402100', 'Fournisseurs, Effets à payer', 'Passif', 'Passif Circulant', '4'],
    ['411100', 'Clients d\'exploitation', 'Actif', 'Actif Circulant', '4'],
    ['412100', 'Clients, Effets à recevoir', 'Actif', 'Actif Circulant', '4'],
    ['421000', 'Personnel, rémunérations dues', 'Passif', 'Passif Circulant', '4'],
    ['431000', 'Sécurité Sociale (CNPS / IPRES)', 'Passif', 'Passif Circulant', '4'],
    ['442100', 'État, Impôts et taxes retenus à la source', 'Passif', 'Passif Circulant', '4'],
    ['445100', 'État, TVA facturée sur ventes', 'Passif', 'Passif Circulant', '4'],
    ['445200', 'État, TVA récupérable sur achats', 'Actif', 'Actif Circulant', '4'],

    // CLASSE 5 : COMPTES DE TRÉSORERIE
    ['512100', 'Banque BOA (Bank of Africa)', 'Actif', 'Trésorerie Actif', '5'],
    ['512200', 'Banque Ecobank Bénin / CI', 'Actif', 'Trésorerie Actif', '5'],
    ['521000', 'Instruments de trésorerie (Mobile Money / Wave)', 'Actif', 'Trésorerie Actif', '5'],
    ['571100', 'Caisse principale Siège', 'Actif', 'Trésorerie Actif', '5'],

    // CLASSE 6 : COMPTES DE CHARGES
    ['601100', 'Achats de marchandises dans la UEMOA', 'Charge', 'Charges d\'exploitation', '6'],
    ['602100', 'Achats de matières premières', 'Charge', 'Charges d\'exploitation', '6'],
    ['605100', 'Fournitures non stockables (Eau, Électricité SENELEC/CIE)', 'Charge', 'Charges d\'exploitation', '6'],
    ['622100', 'Locations immobilières et charges locatives', 'Charge', 'Charges d\'exploitation', '6'],
    ['625100', 'Primes d\'assurances', 'Charge', 'Charges d\'exploitation', '6'],
    ['631100', 'Frais bancaires et commissions', 'Charge', 'Charges d\'exploitation', '6'],
    ['661100', 'Appointerments et salaires du personnel', 'Charge', 'Charges de personnel', '6'],
    ['681300', 'Dotations aux amortissements des immobilisations', 'Charge', 'Charges d\'exploitation', '6'],

    // CLASSE 7 : COMPTES DE PRODUITS
    ['701100', 'Ventes de marchandises dans le pays', 'Produit', 'Produits d\'exploitation', '7'],
    ['701200', 'Ventes de marchandises à l\'exportation', 'Produit', 'Produits d\'exploitation', '7'],
    ['706100', 'Services vendus / Prestations de conseil', 'Produit', 'Produits d\'exploitation', '7'],
    ['771000', 'Intérêts des prêts et créances', 'Produit', 'Produits financiers', '7'],
    ['781100', 'Reprises d\'amortissements et dépréciations', 'Produit', 'Produits d\'exploitation', '7'],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Plan_Comptable');

// En-têtes
$sheet->setCellValue('A1', 'Compte');
$sheet->setCellValue('B1', 'Intitulé du compte');
$sheet->setCellValue('C1', 'Type');
$sheet->setCellValue('D1', 'Catégorie');
$sheet->setCellValue('E1', 'Classe');

$sheet->getStyle('A1:E1')->getFont()->setBold(true);

$rowNum = 2;
foreach ($accounts as $acc) {
    $sheet->setCellValue('A' . $rowNum, $acc[0]);
    $sheet->setCellValue('B' . $rowNum, $acc[1]);
    $sheet->setCellValue('C' . $rowNum, $acc[2]);
    $sheet->setCellValue('D' . $rowNum, $acc[3]);
    $sheet->setCellValue('E' . $rowNum, $acc[4]);
    $rowNum++;
}

$publicDir = __DIR__ . '/../public/downloads';
if (!file_exists($publicDir)) {
    mkdir($publicDir, 0755, true);
}

$xlsxPath = $publicDir . '/Plan_Comptable_SYSCOHADA_Test.xlsx';
$csvPath = $publicDir . '/Plan_Comptable_SYSCOHADA_Test.csv';

$writerXlsx = new Xlsx($spreadsheet);
$writerXlsx->save($xlsxPath);

$writerCsv = new Csv($spreadsheet);
$writerCsv->setDelimiter(';');
$writerCsv->save($csvPath);

echo "Fichiers générés avec succès !\n";
echo "XLSX : " . $xlsxPath . "\n";
echo "CSV : " . $csvPath . "\n";
