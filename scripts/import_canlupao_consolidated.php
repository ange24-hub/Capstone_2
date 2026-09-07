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

$source = dirname(__DIR__).'/storage/app/import-sources/CANLUPAO.xlsx';
if (! is_file($source)) {
    throw new RuntimeException("Missing source workbook: {$source}");
}

function canlupaoWorkbook(string $path): array
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

function canlupaoDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if (is_numeric($value)) {
        return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
    }
    if (preg_match('/^\d{1,2}-\d{1,2}-\d{2,4}$/', $value)) $value = str_replace('-', '/', $value);
    try {
        return Carbon::parse($value)->toDateString();
    } catch (Throwable) {
        return null;
    }
}

function canlupaoSex(?string $value): ?string
{
    return match (strtoupper(trim((string) $value))) {
        'M', 'MALE' => 'Male',
        'F', 'FEMALE' => 'Female',
        default => null,
    };
}

// Only the consolidated worksheet supplies Active Household records.
$sheets = canlupaoWorkbook($source);
if (! isset($sheets['CONSOLIDATED RBI'])) throw new RuntimeException('Missing CONSOLIDATED RBI worksheet.');
$barangay = Barangay::where('name', 'Canlupao')->firstOrFail();
$existing = Inhabitant::with('household')->where('barangay_id', $barangay->id)->get();
$normalize = fn ($value) => mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));
$key = fn ($last, $first, $middle, $suffix) => implode('|', array_map($normalize, [$last, $first, $middle, $suffix]));
$byName = $existing->groupBy(fn ($person) => $key($person->last_name, $person->first_name, $person->middle_name, $person->suffix));
$plans = []; $used = []; $sourceKeys = []; $sourceHouseholds = []; $number = null; $invalidDates = [];
foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
    if ($position < 11) continue;
    // Blank household cells continue the preceding household's members.
    if (filled($row['B'] ?? null)) {
        $number = trim($row['B']);
        $sourceHouseholds[$number] = true;
    }
    if (blank($row['C'] ?? null) || blank($row['D'] ?? null)) continue;
    $sex = canlupaoSex($row['L'] ?? null);
    if (! $number || ! $sex) throw new RuntimeException("Incomplete consolidated resident at row {$position}");
    $birthDate = canlupaoDate($row['J'] ?? null);
    if (filled($row['J'] ?? null) && ! $birthDate) $invalidDates[] = $position;
    $name = $key($row['C'], $row['D'], $row['E'] ?? null, $row['F'] ?? null);
    if (isset($sourceKeys[$name])) throw new RuntimeException("Ambiguous duplicate name at row {$position}");
    $sourceKeys[$name] = true;
    $matches = ($byName->get($name) ?? collect())->reject(fn ($person) => isset($used[$person->id]));
    if ($matches->count() > 1) $matches = $matches->filter(fn ($person) => $person->birth_date?->toDateString() === $birthDate);
    if ($matches->count() > 1) $matches = $matches->filter(fn ($person) => $person->household->household_number === $number);
    if ($matches->count() > 1) throw new RuntimeException("Ambiguous existing residents at row {$position}");
    $person = $matches->first();
    if ($person) $used[$person->id] = true;
    $remarks = trim(($row['Q'] ?? '').(filled($row['J'] ?? null) && ! $birthDate ? ' [Source birth date: '.$row['J'].']' : '')) ?: null;
    $plans[] = ['position' => $position, 'number' => $number, 'purok' => $row['H'] ?? null, 'person' => $person, 'data' => [
        'registry_sequence' => $row['A'] ?? null,
        'family_number' => $number,
        'last_name' => $row['C'], 'first_name' => $row['D'], 'middle_name' => $row['E'] ?? null,
        'suffix' => $row['F'] ?? null, 'relationship_to_head' => $row['G'] ?? null,
        'birth_place' => $row['I'] ?? null, 'birth_date' => $birthDate,
        'recorded_age' => is_numeric($row['K'] ?? null) ? (int) $row['K'] : null,
        'sex' => $sex, 'civil_status' => $row['M'] ?? null,
        'education_level' => $row['N'] ?? null, 'religion' => $row['O'] ?? null,
        'occupation' => $row['P'] ?? null, 'remarks' => $remarks,
        'status' => Inhabitant::STATUS_ACTIVE,
    ]];
}
if (! $plans) throw new RuntimeException('No consolidated residents found.');
$unmatched = $existing->reject(fn ($person) => isset($used[$person->id]));
$summary = [
    'worksheet' => 'CONSOLIDATED RBI', 'residents' => count($plans),
    'households' => count($sourceHouseholds),
    'households_without_residents' => array_values(array_diff(array_keys($sourceHouseholds), array_column($plans, 'number'))),
    'matched_existing' => count($used), 'new_residents' => count($plans) - count($used),
    'previous_records_to_archive' => $unmatched->where('status', Inhabitant::STATUS_ACTIVE)->count(),
    'unparsed_birth_date_rows' => $invalidDates,
];
if (in_array('--dry-run', $argv, true)) { echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL; exit(0); }
$backupDirectory = dirname(__DIR__).'/storage/app/import-backups';
if (! is_dir($backupDirectory)) mkdir($backupDirectory, 0775, true);
$backupPath = $backupDirectory.'/CANLUPAO-consolidated-before-'.now()->format('Ymd-His').'.json';
if (file_put_contents($backupPath, json_encode([
    'source_sha256' => hash_file('sha256', $source),
    'households' => Household::where('barangay_id', $barangay->id)->get(),
    'inhabitants' => $existing->map(fn ($person) => $person->getAttributes()),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Backup failed.');
DB::transaction(function () use ($barangay, $plans, $unmatched, $sourceHouseholds): void {
    Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
    foreach (array_keys($sourceHouseholds) as $number) {
        Household::firstOrCreate(['barangay_id' => $barangay->id, 'household_number' => (string) $number]);
    }
    $households = [];
    foreach ($plans as $plan) {
        $number = $plan['number'];
        if (! isset($households[$number])) {
            $households[$number] = Household::updateOrCreate(
                ['barangay_id' => $barangay->id, 'household_number' => $number],
                ['purok' => $plan['purok'], 'address' => $plan['purok']]
            );
        }
        $person = $plan['person'] ?? new Inhabitant();
        $person->fill($plan['data'] + ['barangay_id' => $barangay->id, 'household_id' => $households[$number]->id]);
        $person->save();
    }
    // Preserve prior records and their relationships outside the active register.
    Inhabitant::where('barangay_id', $barangay->id)->whereIn('id', $unmatched->modelKeys())
        ->where('status', Inhabitant::STATUS_ACTIVE)->update(['status' => Inhabitant::STATUS_INACTIVE]);
});
$summary['active_after_import'] = Inhabitant::where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)->count();
$summary['backup'] = $backupPath;
\App\Support\SourceResidenceSync::apply($barangay, $source);

echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL;
