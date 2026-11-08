<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$targets = [
    'Anahawan' => ['ANAHAWAN.xlsx', ['CONSOLIDATED RBI', 'DECEASED', 'NIF']],
    'Banday' => ['BANDAY.xlsx', ['CONSOLIDATED RBI (2026)', 'DECEASED', 'NEW', 'Sheet 2026']],
    'Bogo' => ['BOGO.xlsx', ['CONSOLIDATED RBI (2)2026', 'DECEASED', 'NEW']],
    'Cambite' => ['CAMBITE.xlsx', ['CONSOLIDATED BRBI', 'RBI FORM', 'DECEASED', 'TRANSFERED']],
    'Maanyag' => ['MAANYAG.xlsx', ['CONSOLIDATED RBI', 'DECEASED', 'NEW']],
    'Mapgap' => ['MAPGAP.xlsx', ['CONSOLIDATED RBI', 'Deceased']],
];
foreach ($targets as $area => [$file, $names]) {
    $sheets = App\Support\ResidenceWorkbookReader::read(storage_path('app/import-sources/brbi-20261108/'.$file), $names);
    $summary = [];
    foreach ($sheets as $name => $rows) {
        $headers = [];
        foreach ($rows as $number => $row) {
            if ($number > 11) break;
            foreach ($row as $column => $value) {
                if (is_string($value) && preg_match('/HOUSEHOLD|LAST NAME|FIRST NAME|MIDDLE NAME|SUFFIX|RELATION|ADDRESS|PLACE OF|DATE OF|AGE|SEX|CIVIL|EDUCAT|RELIGION|OCCUP|REMARK|MONTH|NEW.*INHAB|CONSOLIDAT|REGISTRY|UPDATED/i', $value)) $headers[$number][$column] = $value;
            }
        }
        $sectionRows = [];
        foreach ($rows as $number => $row) {
            foreach ($row as $column => $value) {
                if (is_string($value) && preg_match('/^(?:JANUARY|FEBRUARY|MARCH|APRIL|MAY|JUNE|JULY|AUGUST|SEPTEMBER|OCTOBER|NOVEMBER|DECEMBER)(?:\s+\d{1,2}[, ]*)?(?:\s+20\d{2})?$|NEW INHABITANTS.*20\d{2}/i', trim($value))) $sectionRows[$number][$column] = $value;
            }
        }
        $summary[] = ['sheet' => $name, 'nonempty_rows' => count($rows), 'headers' => $headers, 'month_sections' => $sectionRows];
    }
    echo json_encode(['barangay' => $area, 'sheets' => $summary], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
}
