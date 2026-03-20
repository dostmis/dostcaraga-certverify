<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$psgcPath = __DIR__ . '/../resources/data/psgc.json';
if (!file_exists($psgcPath)) {
    fwrite(STDERR, "PSGC data not found at {$psgcPath}\n");
    exit(1);
}
$psgc = json_decode(file_get_contents($psgcPath), true);
$regionName = 'REGION XIII (Caraga)';
$region = $psgc[$regionName] ?? null;
if (!$region || !is_array($region)) {
    fwrite(STDERR, "Region data missing for {$regionName}\n");
    exit(1);
}

// Build flat rows for Caraga only to keep the file light
$rows = [];
foreach ($region as $prov => $cities) {
    if ($prov === 'population' || !is_array($cities)) continue;
    foreach ($cities as $city => $barangays) {
        if ($city === 'population' || $city === 'class' || $city === 'cityClass' || !is_array($barangays)) continue;
        foreach ($barangays as $brgy => $meta) {
            if ($brgy === 'population') continue;
            $rows[] = [$regionName, $prov, $city, $brgy];
        }
    }
}

$spreadsheet = new Spreadsheet();
$main = $spreadsheet->getActiveSheet();
$main->setTitle('Participants');

$headers = ['participant name','Gender','Age','Industry','Region','Province/State','City/Municipality','Barangay','Block/Lot/Purok'];
$main->fromArray($headers, null, 'A1');
$sample = ['CJ Hyca Villadores','Female',18,'NGA',$regionName,'Agusan Del Norte','City of Butuan (Capital)','Ampayon','Block 2, Lot 8'];
$main->fromArray($sample, null, 'A2');

// PSGC sheet
$psgcSheet = new Worksheet($spreadsheet, 'PSGC');
$spreadsheet->addSheet($psgcSheet, 1);
$psgcSheet->fromArray(['Region','Province','CityMunicipality','Barangay'], null, 'A1');
$psgcSheet->fromArray($rows, null, 'A2');

// Helper spill formulas on Participants sheet
$lastRow = count($rows) + 1;
$main->setCellValue('J1', "=SORT(UNIQUE(PSGC!A2:A{$lastRow}))");

for ($r = 2; $r <= 200; $r++) {
    $main->setCellValue("K{$r}", "=SORT(UNIQUE(FILTER(PSGC!B:B,PSGC!A:A=E{$r})))");
    $main->setCellValue("L{$r}", "=SORT(UNIQUE(FILTER(PSGC!C:C,(PSGC!A:A=E{$r})*(PSGC!B:B=F{$r}))))");
    $main->setCellValue("M{$r}", "=SORT(UNIQUE(FILTER(PSGC!D:D,(PSGC!A:A=E{$r})*(PSGC!B:B=F{$r})*(PSGC!C:C=G{$r}))))");
}

// Data validations helper
$addListValidation = function(Worksheet $sheet, string $cell, string $formula) {
    $dv = $sheet->getCell($cell)->getDataValidation();
    $dv->setType(DataValidation::TYPE_LIST);
    $dv->setErrorStyle(DataValidation::STYLE_STOP);
    $dv->setAllowBlank(true);
    $dv->setShowDropDown(true);
    $dv->setFormula1($formula);
};

for ($r = 2; $r <= 200; $r++) {
    $addListValidation($main, "E{$r}", '=$J$1#');
    $addListValidation($main, "F{$r}", "=\$K{$r}#");
    $addListValidation($main, "G{$r}", "=\$L{$r}#");
    $addListValidation($main, "H{$r}", "=\$M{$r}#");
}

// Freeze top row
$main->freezePane('A2');

// Hide helper columns
foreach (['J','K','L','M'] as $col) {
    $main->getColumnDimension($col)->setVisible(false);
}

$output = __DIR__ . '/../public/participants_template.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($output);

fwrite(STDOUT, "Created template at {$output}\n");
