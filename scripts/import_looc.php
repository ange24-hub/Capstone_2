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

$source = dirname(__DIR__).'/storage/app/import-sources/LOOC.xlsx';
if (! is_file($source)) {
    throw new RuntimeException("Missing source workbook: {$source}");
}

function loocWorkbook(string $path): array
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

    $styles = simplexml_load_string($zip->getFromName('xl/styles.xml'));
    $fills = [];
    foreach ($styles->fills->fill as $fill) $fills[] = strtoupper((string) $fill->patternFill->fgColor['rgb']);
    $cellFills = [];
    foreach ($styles->cellXfs->xf as $style) $cellFills[] = $fills[(int) $style['fillId']] ?? '';
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
                if ($column === 'C') $values['_fill'] = $cellFills[(int) $cell['s']] ?? '';
            }
            $rows[(int) $row['r']] = $values;
        }
        $sheets[(string) $sheet['name']] = $rows;
    }
    $zip->close();

    return $sheets;
}

function loocDate(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    if (is_numeric($value)) {
        $date = Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value));
        return $date->year < 1800 || $date->startOfDay()->isFuture() ? null : $date->toDateString();
    }
    if (preg_match('~^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$~', $value, $parts)) {
        if (! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[3])) return null;
        $value = sprintf('%04d-%02d-%02d', $parts[3], $parts[1], $parts[2]);
    } elseif (str_contains($value, '/')) {
        return null;
    }
    try {
        $date = Carbon::parse($value);
        return $date->year < 1800 || $date->startOfDay()->isFuture() ? null : $date->toDateString();
    } catch (Throwable) {
        return null;
    }
}

function loocAge(?string $value): ?int
{
    return is_numeric($value) && (float) $value >= 0 && (float) $value <= 150 ? (int) $value : null;
}

function loocSex(?string $value): ?string
{
    return match (strtoupper(trim((string) $value))) {
        'M', 'MALE' => 'Male',
        'F', 'FEMALE' => 'Female',
        default => null,
    };
}

function loocMonth(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (is_numeric($value)) return Carbon::parse(loocDate($value))->startOfMonth()->toDateString();
    // Keep month-only source values without inventing a reporting year.
    if (! preg_match('/\d{4}/', $value)) return null;
    try {
        return Carbon::parse('1 '.$value)->startOfMonth()->toDateString();
    } catch (Throwable) {
        return null;
    }
}

$sheets = loocWorkbook($source);
// Only an explicit destination-bearing transfer remark changes residency status.
$plan = \App\Support\LoocWorkbook::plan($sheets);
$barangay = Barangay::where('name', 'Looc')->firstOrFail();
$counts = fn () => [
    'households' => Household::where('barangay_id', $barangay->id)->where('household_number', '!=', 'Not recorded')->count(),
    'active_residents' => Inhabitant::where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_ACTIVE)->count(),
    'moved_out' => Inhabitant::where('barangay_id', $barangay->id)->where('status', Inhabitant::STATUS_MIGRATED_OUT)->count(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->count(),
    'new_inhabitants' => NewInhabitant::where('barangay_id', $barangay->id)->count(),
];
$invalidDates = [];
function loocPerson(array $row, array $columns): array
{
    global $invalidDates;
    $data = [];
    foreach ($columns as $field => $column) $data[$field] = $row[$column] ?? null;
    $remarks = trim((string) ($data['remarks'] ?? ''));
    foreach (['birth_date', 'death_date'] as $field) {
        if (! array_key_exists($field, $data)) continue;
        $raw = $data[$field];
        $data[$field] = loocDate($raw);
        if (filled($raw) && $data[$field] === null) {
            $remarks .= ' [Source '.$field.': '.$raw.']';
            $invalidDates[] = $row['_source'].' '.$field;
        }
    }
    $rawAge = $data['recorded_age'] ?? null;
    $data['recorded_age'] = loocAge($rawAge);
    if (filled($rawAge) && $data['recorded_age'] === null) $remarks .= ' [Source age: '.$rawAge.']';
    $rawSex = $data['sex'] ?? null;
    $data['sex'] = loocSex($rawSex);
    if ($data['sex'] === null) $remarks .= ' [Source sex: '.($rawSex ?: 'not provided').']';
    $data['remarks'] = trim($remarks.' [Source: LOOC.xlsx '.$row['_source'].']');
    return $data;
}
$activeColumns = ['last_name'=>'C','first_name'=>'D','middle_name'=>'E','suffix'=>'F','relationship_to_head'=>'G',
    'birth_place'=>'I','birth_date'=>'J','recorded_age'=>'K','sex'=>'L','civil_status'=>'M','education_level'=>'N',
    'religion'=>'O','occupation'=>'P','remarks'=>'Q'];
$deceasedColumns = ['household_number'=>'A','last_name'=>'B','first_name'=>'C','middle_name'=>'D','suffix'=>'E',
    'relationship_to_head'=>'F','purok'=>'G','birth_place'=>'H','birth_date'=>'I','recorded_age'=>'J','sex'=>'K',
    'civil_status'=>'L','education_level'=>'M','religion'=>'N','occupation'=>'O','remarks'=>'P','death_date'=>'Q'];
$newColumns = ['household_number'=>'A','last_name'=>'B','first_name'=>'C','middle_name'=>'D','suffix'=>'E',
    'birth_date'=>'F','recorded_age'=>'G','sex'=>'H','civil_status'=>'I','education_level'=>'J','religion'=>'K','occupation'=>'L','remarks'=>'M'];
$prepared = [];
foreach (['active', 'moved', 'deceased', 'new'] as $group) {
    $columns = match ($group) { 'deceased' => $deceasedColumns, 'new' => $newColumns, default => $activeColumns };
    foreach ($plan[$group] as $position => $row) {
        $prepared[$group][$position] = loocPerson($row, $columns);
        if (in_array($group, ['active', 'moved'], true)) {
            $prepared[$group][$position]['sex'] ??= '';
            if (blank($row['B'] ?? null)) {
                $prepared[$group][$position]['remarks'] .= ' [Household number missing in source; purok: '.($row['H'] ?? '').']';
            }
        }
    }
}
$summary = [
    'moved_out_rule' => 'TRANSFERRED TO followed by a destination; color alone does not change status',
    'source_sha256' => hash_file('sha256', $source),
    'source_counts' => array_map('count', $plan),
    'missing_household_numbers' => array_values(array_diff(range(1, 224), array_keys($plan['households']))),
    'pending_rows' => array_map(fn ($item) => $item['reason'], $plan['pending']),
    'active_without_household_number' => count(array_filter($plan['active'], fn ($row) => blank($row['B'] ?? null))),
    'invalid_dates' => $invalidDates, 'existing' => $counts(),
];
if (in_array('--dry-run', $argv, true)) { echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL; exit(0); }
if (empty($plan['active']) || empty($plan['households'])) throw new RuntimeException('No active Looc records found.');
$backupDirectory = dirname(__DIR__).'/storage/app/import-backups';
if (! is_dir($backupDirectory)) mkdir($backupDirectory, 0775, true);
$backupPath = $backupDirectory.'/LOOC-before-'.now()->format('Ymd-His').'.json';
if (file_put_contents($backupPath, json_encode([
    'summary' => $summary, 'pending_source_rows' => $plan['pending'],
    'households' => Household::where('barangay_id', $barangay->id)->get(),
    'inhabitants' => Inhabitant::where('barangay_id', $barangay->id)->get(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->get(),
    'new' => NewInhabitant::where('barangay_id', $barangay->id)->get(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Backup failed.');
$reviewPath = $backupDirectory.'/LOOC-review-'.now()->format('Ymd-His').'.csv';
$review = fopen($reviewPath, 'w');
if ($review === false) throw new RuntimeException('Unable to write the source review report.');
fputcsv($review, ['Source row', 'Household number', 'Last name', 'First name', 'Review reason'], ',', '"', '');
foreach ($plan['pending'] as $position => $item) {
    fputcsv($review, [$position, $item['row']['B'] ?? '', $item['row']['C'] ?? '', $item['row']['D'] ?? '', $item['reason']], ',', '"', '');
}
foreach ($plan['active'] as $position => $row) {
    if (blank($row['B'] ?? null)) fputcsv($review, [$position, '', $row['C'], $row['D'], 'Imported as active; household number needs confirmation'], ',', '"', '');
}
fclose($review);
DB::transaction(function () use ($barangay, $plan, $prepared): void {
    Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
    foreach ([Household::class, Inhabitant::class, DeceasedInhabitant::class, NewInhabitant::class] as $model) {
        if ($model::where('barangay_id', $barangay->id)->exists()) throw new RuntimeException('Looc already has records; refusing to overwrite or duplicate them.');
    }
    $households = [];
    foreach ($plan['households'] as $number => $purok) {
        $households[$number] = Household::create(['barangay_id'=>$barangay->id,'household_number'=>(string)$number,'purok'=>$purok,'address'=>$purok]);
    }
    foreach (['active', 'moved'] as $group) {
        foreach ($prepared[$group] ?? [] as $position => $data) {
            $row = $plan[$group][$position];
            $number = ($row['B'] ?? '') ?: 'Not recorded';
            $households[$number] ??= Household::create(['barangay_id'=>$barangay->id,'household_number'=>$number]);
            Inhabitant::create($data + [
                'barangay_id'=>$barangay->id, 'household_id'=>$households[$number]->id,
                'registry_sequence'=>$row['A'] ?? null, 'family_number'=>$row['B'] ?: null,
                'individual_number'=>is_numeric($row['S'] ?? null) ? $row['S'] : null,
                'status'=>$group === 'active' ? Inhabitant::STATUS_ACTIVE : Inhabitant::STATUS_MIGRATED_OUT,
            ]);
        }
    }
    foreach ($prepared['deceased'] ?? [] as $position => $data) {
        DeceasedInhabitant::create($data + ['barangay_id'=>$barangay->id,'source_position'=>$position]);
    }
    foreach ($prepared['new'] ?? [] as $position => $data) {
        $month = $plan['new'][$position]['O'] ?? null;
        NewInhabitant::create($data + ['barangay_id'=>$barangay->id,'source_position'=>$position,
            'month_submitted'=>$month,'reporting_month'=>loocMonth($month)]);
    }
});
\App\Support\SourceResidenceSync::apply($barangay, $source);

echo json_encode(['imported'=>$counts(),'pending_rows'=>array_keys($plan['pending']),'backup'=>$backupPath,'review_file'=>$reviewPath], JSON_PRETTY_PRINT).PHP_EOL;

