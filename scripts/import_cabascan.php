<?php

use App\Models\Barangay;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\NewInhabitant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = dirname(__DIR__).'/storage/app/import-sources/CABASCAN.xlsx';
if (! is_file($source)) {
    throw new RuntimeException("Missing source workbook: {$source}");
}

function cabascanWorkbook(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("Unable to open {$path}");
    }

    $shared = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $document = simplexml_load_string($xml);
        foreach ($document->si as $item) {
            $texts = $item->xpath('.//*[local-name()="t"]');
            $shared[] = implode('', array_map(fn ($text) => (string) $text, $texts));
        }
    }

    $book = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
    $relationships = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
    $targets = [];
    foreach ($relationships->Relationship as $relationship) {
        $targets[(string) $relationship['Id']] = 'xl/'.ltrim((string) $relationship['Target'], '/');
    }

    $sheets = [];
    foreach ($book->sheets->sheet as $sheet) {
        $attributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheetXml = simplexml_load_string($zip->getFromName($targets[(string) $attributes['id']]));
        $rows = [];
        foreach ($sheetXml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                $column = $match[0];
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's' && $value !== '') {
                    $value = $shared[(int) $value] ?? '';
                } elseif ((string) $cell['t'] === 'inlineStr') {
                    $value = implode('', array_map(fn ($text) => (string) $text, $cell->is->xpath('.//*[local-name()="t"]')));
                }
                $values[$column] = trim($value);
            }
            $rows[(int) $row['r']] = $values;
        }
        $sheets[(string) $sheet['name']] = $rows;
    }
    $zip->close();

    return $sheets;
}

function cabascanDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if (is_numeric($value)) {
        return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
    }
    try {
        return Carbon::parse($value)->toDateString();
    } catch (Throwable) {
        return null;
    }
}

function cabascanSex(?string $value): ?string
{
    return match (strtoupper(trim((string) $value))) {
        'M', 'MALE' => 'Male',
        'F', 'FEMALE' => 'Female',
        default => null,
    };
}

function cabascanMonth(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (! preg_match('/\d{4}/', $value)) $value .= ' 2020';
    try {
        return Carbon::parse('1 '.$value)->startOfMonth()->toDateString();
    } catch (Throwable) {
        return null;
    }
}

$sheets = cabascanWorkbook($source);
$barangay = Barangay::where('name', 'Cabascan')->firstOrFail();
$activeRows = collect($sheets['CONSOLIDATED RBI'] ?? [])->filter(fn ($row, $number) => $number >= 11
    && filled($row['B'] ?? null) && filled($row['C'] ?? null) && filled($row['D'] ?? null)
    && cabascanSex($row['L'] ?? null) !== null);
$deceasedRows = collect($sheets['DECEASED'] ?? [])->filter(fn ($row, $number) => $number >= 2 && filled($row['A'] ?? null) && filled($row['B'] ?? null) && filled($row['C'] ?? null));
$newRows = collect($sheets['NEW'] ?? [])->filter(fn ($row, $number) => $number >= 2 && filled($row['A'] ?? null) && filled($row['B'] ?? null) && filled($row['C'] ?? null));

$backupDirectory = dirname(__DIR__).'/storage/app/import-backups';
if (! is_dir($backupDirectory)) mkdir($backupDirectory, 0775, true);
$backupPath = $backupDirectory.'/CABASCAN-before-'.now()->format('Ymd-His').'.json';
file_put_contents($backupPath, json_encode([
    'households' => Household::where('barangay_id', $barangay->id)->get(),
    'inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->get(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->get(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->get(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

DB::transaction(function () use ($barangay, $activeRows, $deceasedRows, $newRows): void {
    NewInhabitant::where('barangay_id', $barangay->id)->delete();
    DeceasedInhabitant::where('barangay_id', $barangay->id)->delete();
    Inhabitant::where('barangay_id', $barangay->id)->delete();
    Household::where('barangay_id', $barangay->id)->delete();

    $households = [];
    foreach ($activeRows as $position => $row) {
        $number = trim($row['B']);
        if (! isset($households[$number])) {
            $households[$number] = Household::create([
                'barangay_id' => $barangay->id,
                'household_number' => $number,
                'purok' => $row['H'] ?? null,
                'address' => $row['H'] ?? null,
            ]);
        }
        Inhabitant::create([
            'barangay_id' => $barangay->id,
            'household_id' => $households[$number]->id,
            'registry_sequence' => $row['A'] ?? null,
            'family_number' => $number,
            'individual_number' => $row['S'] ?? null,
            'last_name' => $row['C'], 'first_name' => $row['D'], 'middle_name' => $row['E'] ?? null,
            'suffix' => $row['F'] ?? null, 'relationship_to_head' => $row['G'] ?? null,
            'birth_place' => $row['I'] ?? null, 'birth_date' => cabascanDate($row['J'] ?? null),
            'recorded_age' => is_numeric($row['K'] ?? null) ? (int) $row['K'] : null,
            'sex' => cabascanSex($row['L'] ?? null), 'civil_status' => $row['M'] ?? null,
            'education_level' => $row['N'] ?? null, 'religion' => $row['O'] ?? null,
            'occupation' => $row['P'] ?? null, 'remarks' => $row['Q'] ?? null,
            'status' => Inhabitant::STATUS_ACTIVE,
        ]);
    }

    foreach ($deceasedRows as $position => $row) {
        DeceasedInhabitant::create([
            'barangay_id' => $barangay->id, 'household_number' => $row['A'],
            'last_name' => $row['B'], 'first_name' => $row['C'], 'middle_name' => $row['D'] ?? null,
            'suffix' => $row['E'] ?? null, 'relationship_to_head' => $row['F'] ?? null,
            'purok' => $row['G'] ?? null, 'birth_place' => $row['H'] ?? null,
            'birth_date' => cabascanDate($row['I'] ?? null), 'recorded_age' => is_numeric($row['J'] ?? null) ? (int) $row['J'] : null,
            'sex' => cabascanSex($row['K'] ?? null), 'civil_status' => $row['L'] ?? null,
            'education_level' => $row['M'] ?? null, 'religion' => $row['N'] ?? null,
            'occupation' => $row['O'] ?? null, 'remarks' => $row['P'] ?? null,
            'death_date' => cabascanDate($row['Q'] ?? null), 'source_position' => $position,
        ]);
    }

    foreach ($newRows as $position => $row) {
        NewInhabitant::create([
            'barangay_id' => $barangay->id, 'household_number' => $row['A'],
            'last_name' => $row['B'], 'first_name' => $row['C'], 'middle_name' => $row['D'] ?? null,
            'suffix' => $row['E'] ?? null, 'birth_date' => cabascanDate($row['F'] ?? null),
            'recorded_age' => is_numeric($row['G'] ?? null) ? (int) $row['G'] : null,
            'sex' => cabascanSex($row['H'] ?? null), 'civil_status' => $row['I'] ?? null,
            'education_level' => $row['J'] ?? null, 'religion' => $row['K'] ?? null,
            'occupation' => $row['L'] ?? null, 'remarks' => $row['M'] ?? null,
            'month_submitted' => $row['N'] ?? null, 'reporting_month' => cabascanMonth($row['N'] ?? null),
            'source_position' => $position,
        ]);
    }
});

echo json_encode([
    'barangay' => $barangay->name,
    'households' => Household::where('barangay_id', $barangay->id)->count(),
    'active_inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->count(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->count(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->count(),
    'backup' => $backupPath,
], JSON_PRETTY_PRINT).PHP_EOL;
