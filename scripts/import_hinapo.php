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

$source = dirname(__DIR__).'/storage/app/import-sources/HINAPO.xlsx';
if (! is_file($source)) {
    throw new RuntimeException("Missing source workbook: {$source}");
}

function hinapoWorkbook(string $path): array
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

function hinapoDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if (is_numeric($value)) {
        return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
    }
    $value = preg_replace('~/+~', '/', $value);
    if (preg_match('/^\d{1,2}-\d{1,2}-\d{2,4}$/', $value)) $value = str_replace('-', '/', $value);
    try {
        return Carbon::parse($value)->toDateString();
    } catch (Throwable) {
        return null;
    }
}

function hinapoAge(?string $value): ?int
{
    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 150 ? (int) $value : null;
}

function hinapoSex(?string $value): ?string
{
    return match (strtoupper(trim((string) $value))) {
        'M', 'MALE' => 'Male',
        'F', 'FEMALE' => 'Female',
        default => null,
    };
}

function hinapoMonth(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (is_numeric($value)) return Carbon::parse(hinapoDate($value))->startOfMonth()->toDateString();
    // Keep month-only source values without inventing a reporting year.
    if (! preg_match('/\d{4}/', $value)) return null;
    try {
        return Carbon::parse('1 '.$value)->startOfMonth()->toDateString();
    } catch (Throwable) {
        return null;
    }
}

$sheets = hinapoWorkbook($source);
$barangay = Barangay::where('name', 'Hinapo')->firstOrFail();
if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? null) !== 'HINAPO') {
    throw new RuntimeException('The consolidated worksheet is not for Hinapo.');
}
// Row 81 continues household 19; row 116 starts household 29,
// confirmed by its HH sequence and the numbered spouse immediately below.
foreach ([81 => '19', 116 => '29'] as $position => $number) {
    if (blank($sheets['CONSOLIDATED RBI'][$position]['B'] ?? null)) {
        $sheets['CONSOLIDATED RBI'][$position]['B'] = $number;
    }
}
$activeRows = collect($sheets['CONSOLIDATED RBI'] ?? [])->filter(fn ($row, $number) => $number >= 11
    && filled($row['B'] ?? null) && filled($row['C'] ?? null) && filled($row['D'] ?? null)
);
$deceasedRows = collect($sheets['DECEASED'] ?? [])->filter(fn ($row, $number) => $number >= 2 && filled($row['A'] ?? null) && filled($row['B'] ?? null) && filled($row['C'] ?? null));
$newRows = collect($sheets['NEW'] ?? [])->filter(fn ($row, $number) => $number >= 2 && filled($row['A'] ?? null) && filled($row['B'] ?? null) && filled($row['C'] ?? null));
$unassignedRows = collect($sheets['CONSOLIDATED RBI'] ?? [])->filter(fn ($row, $number) => $number >= 11
    && filled($row['C'] ?? null) && filled($row['D'] ?? null) && blank($row['B'] ?? null))->keys()->all();

$sourceHouseholds = collect($sheets['CONSOLIDATED RBI'] ?? [])
    ->filter(fn ($row, $position) => $position >= 11 && filled($row['B'] ?? null))->pluck('B')->unique();
$incompleteNewRows = collect($sheets['NEW'] ?? [])->filter(fn ($row, $position) => $position >= 2
    && (filled($row['B'] ?? null) || filled($row['C'] ?? null))
    && (blank($row['A'] ?? null) || blank($row['B'] ?? null) || blank($row['C'] ?? null)))->keys()->all();
$existingCounts = [
    'households' => Household::where('barangay_id', $barangay->id)->count(),
    'active_inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->count(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->count(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->count(),
];
if (! isset($sheets['CONSOLIDATED RBI'], $sheets['DECEASED'], $sheets['NEW']) || $activeRows->isEmpty()) {
    throw new RuntimeException('The expected Hinapo worksheets or active records are missing.');
}
if (in_array('--dry-run', $argv, true)) {
    echo json_encode([
        'source_households' => $sourceHouseholds->count(),
        'source_active' => $activeRows->count(),
        'source_deceased' => $deceasedRows->count(),
        'source_new' => $newRows->count(),
        'rows_missing_household_number' => $unassignedRows,
        'incomplete_new_rows' => $incompleteNewRows,
        'rows_with_unconfirmed_sex' => $activeRows->filter(fn ($row) => hinapoSex($row['L'] ?? null) === null)->keys()->all(),
        'invalid_active_birth_date_rows' => $activeRows->filter(fn ($row) => filled($row['J'] ?? null) && hinapoDate($row['J']) === null)->keys()->all(),
        'existing' => $existingCounts,
    ], JSON_PRETTY_PRINT).PHP_EOL;
    exit(0);
}

$backupDirectory = dirname(__DIR__).'/storage/app/import-backups';
if (! is_dir($backupDirectory)) mkdir($backupDirectory, 0775, true);
$backupPath = $backupDirectory.'/HINAPO-before-'.now()->format('Ymd-His').'.json';
if (file_put_contents($backupPath, json_encode([
    'households' => Household::where('barangay_id', $barangay->id)->get(),
    'inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->get(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->get(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->get(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Unable to back up existing records.');
}

DB::transaction(function () use ($barangay, $activeRows, $deceasedRows, $newRows, $sourceHouseholds): void {
    $barangay = Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
    foreach ([Household::class, Inhabitant::class, DeceasedInhabitant::class, NewInhabitant::class] as $model) {
        if ($model::where('barangay_id', $barangay->id)->exists()) {
            throw new RuntimeException('Hinapo already has records; refusing to overwrite or duplicate them.');
        }
    }

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
            'individual_number' => is_numeric($row['S'] ?? null) ? $row['S'] : null,
            'last_name' => $row['C'], 'first_name' => $row['D'], 'middle_name' => $row['E'] ?? null,
            'suffix' => $row['F'] ?? null, 'relationship_to_head' => $row['G'] ?? null,
            'birth_place' => $row['I'] ?? null, 'birth_date' => hinapoDate($row['J'] ?? null),
            'recorded_age' => hinapoAge($row['K'] ?? null),
            'sex' => hinapoSex($row['L'] ?? null) ?? '', 'civil_status' => $row['M'] ?? null,
            'education_level' => $row['N'] ?? null, 'religion' => $row['O'] ?? null,
            'occupation' => $row['P'] ?? null,
            'remarks' => trim(($row['Q'] ?? '')
                .(filled($row['K'] ?? null) && hinapoAge($row['K']) === null ? ' [Source age: '.$row['K'].']' : '')
                .(filled($row['J'] ?? null) && hinapoDate($row['J']) === null ? ' [Source birth date: '.$row['J'].']' : '')
                .(hinapoSex($row['L'] ?? null) === null ? ' [Source sex: '.(($row['L'] ?? '') ?: 'not provided').']' : '')) ?: null,
            'status' => Inhabitant::STATUS_ACTIVE,
        ]);
    }

    foreach ($sourceHouseholds as $number) {
        Household::firstOrCreate(['barangay_id' => $barangay->id, 'household_number' => $number]);
    }

    foreach ($deceasedRows as $position => $row) {
        DeceasedInhabitant::create([
            'barangay_id' => $barangay->id, 'household_number' => $row['A'],
            'last_name' => $row['B'], 'first_name' => $row['C'], 'middle_name' => $row['D'] ?? null,
            'suffix' => $row['E'] ?? null, 'relationship_to_head' => $row['F'] ?? null,
            'purok' => $row['G'] ?? null, 'birth_place' => $row['H'] ?? null,
            'birth_date' => hinapoDate($row['I'] ?? null), 'recorded_age' => hinapoAge($row['J'] ?? null),
            'sex' => hinapoSex($row['K'] ?? null), 'civil_status' => $row['L'] ?? null,
            'education_level' => $row['M'] ?? null, 'religion' => $row['N'] ?? null,
            'occupation' => $row['O'] ?? null, 'remarks' => trim(($row['P'] ?? '').(filled($row['J'] ?? null) && hinapoAge($row['J']) === null ? ' [Source age: '.$row['J'].']' : '')) ?: null,
            'death_date' => hinapoDate($row['Q'] ?? null), 'source_position' => $position,
        ]);
    }

    foreach ($newRows as $position => $row) {
        NewInhabitant::create([
            'barangay_id' => $barangay->id, 'household_number' => $row['A'],
            'last_name' => $row['B'], 'first_name' => $row['C'], 'middle_name' => $row['D'] ?? null,
            'suffix' => $row['E'] ?? null, 'birth_date' => hinapoDate($row['F'] ?? null),
            'recorded_age' => hinapoAge($row['G'] ?? null),
            'sex' => hinapoSex($row['H'] ?? null), 'civil_status' => $row['I'] ?? null,
            'education_level' => $row['J'] ?? null, 'religion' => $row['K'] ?? null,
            'occupation' => $row['L'] ?? null,
            'remarks' => trim(($row['M'] ?? '').(filled($row['G'] ?? null) && ! is_numeric($row['G']) ? ' [Source age: '.$row['G'].']' : '')) ?: null,
            'month_submitted' => $row['N'] ?? null, 'reporting_month' => hinapoMonth($row['N'] ?? null),
            'source_position' => $position,
        ]);
    }
});

\App\Support\SourceResidenceSync::apply($barangay, $source);

echo json_encode([
    'barangay' => $barangay->name,
    'households' => Household::where('barangay_id', $barangay->id)->count(),
    'active_inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->count(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->count(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->count(),
    'rows_missing_household_number' => $unassignedRows,
        'incomplete_new_rows' => $incompleteNewRows,
        'rows_with_unconfirmed_sex' => $activeRows->filter(fn ($row) => hinapoSex($row['L'] ?? null) === null)->keys()->all(),
        'invalid_active_birth_date_rows' => $activeRows->filter(fn ($row) => filled($row['J'] ?? null) && hinapoDate($row['J']) === null)->keys()->all(),
    'backup' => $backupPath,
], JSON_PRETTY_PRINT).PHP_EOL;
