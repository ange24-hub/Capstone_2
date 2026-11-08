<?php

use App\Models\Barangay;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use App\Models\NewInhabitant;
use App\Services\MigrationForecastHistory;
use App\Support\ResidenceWorkbookReader;
use App\Support\TinagoWorkbook;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$source = storage_path('app/import-sources/TINAGO.xlsx');
if (! is_file($source)) throw new RuntimeException('Missing TINAGO.xlsx source workbook.');
$sourceHash = hash_file('sha256', $source);
$plan = TinagoWorkbook::plan(ResidenceWorkbookReader::read($source, [TinagoWorkbook::PRIMARY_SHEET, 'DECEASED', 'NEW']));
$area = Barangay::where('name', 'Tinago')->firstOrFail();

function tinagoDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') return null;
    if (is_numeric($value)) {
        $date = Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value));
        return $date->year >= 1800 && ! $date->startOfDay()->isFuture() ? $date->toDateString() : null;
    }
    foreach (['!Y-m-d', '!m/d/Y', '!n/j/Y', '!m-d-Y', '!F j, Y', '!F j Y', '!M j, Y'] as $format) {
        $date = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date && ($errors === false || (! $errors['warning_count'] && ! $errors['error_count']))
            && (int) $date->format('Y') >= 1800 && $date->format('Y-m-d') <= now()->toDateString()) return $date->format('Y-m-d');
    }
    return null;
}

function tinagoState(Barangay $area): array
{
    return ['households' => Household::where('barangay_id', $area->id)->orderBy('id')->get()->toArray(),
        'inhabitants' => Inhabitant::where('barangay_id', $area->id)->orderBy('id')->get()->toArray(),
        'deceased' => DeceasedInhabitant::where('barangay_id', $area->id)->orderBy('id')->get()->toArray(),
        'new' => NewInhabitant::where('barangay_id', $area->id)->orderBy('id')->get()->toArray(),
        'migration_records' => MigrationRecord::where('barangay_id', $area->id)->orderBy('id')->get()->toArray()];
}

function tinagoCheckExisting(Barangay $area): array
{
    $state = tinagoState($area);
    foreach ($state['households'] as $row) if ($row['household_number'] !== 'DEMO-MIGRATION-V1') throw new RuntimeException('Tinago has existing real households; refusing to overwrite them.');
    foreach ($state['inhabitants'] as $row) {
        if ($row['remarks'] !== MigrationForecastHistory::DEMO_MARKER || $row['resident_user_id'] !== null) throw new RuntimeException('Tinago has real or account-linked residents; refusing to overwrite them.');
    }
    foreach ($state['migration_records'] as $row) if ($row['reason'] !== MigrationForecastHistory::DEMO_MARKER) throw new RuntimeException('Tinago has actual movement records; refusing to overwrite them.');
    if ($state['deceased'] || $state['new'] || App\Models\BarangayRbiUpdate::where('barangay_name', $area->name)->exists()) throw new RuntimeException('Tinago has existing actual history; refusing to overwrite it.');
    $ids = array_column($state['inhabitants'], 'id');
    foreach (['document_requests', 'barangay_rbi_members', 'barangay_rbi_deceased_records', 'registry_activities'] as $table) {
        if ($ids && Schema::hasColumn($table, 'inhabitant_id') && DB::table($table)->whereIn('inhabitant_id', $ids)->exists()) throw new RuntimeException('Existing demo residents have linked '.$table.'; manual review is required.');
    }
    if ($ids && (MigrationRecord::whereIn('inhabitant_id', $ids)->where('barangay_id', '!=', $area->id)->exists()
        || NewInhabitant::whereIn('active_inhabitant_id', $ids)->exists())) throw new RuntimeException('Demo residents are referenced by other registry records; manual review is required.');
    $householdIds = array_column($state['households'], 'id');
    foreach (['barangay_rbi_families', 'household_offline_syncs'] as $table) {
        if ($householdIds && Schema::hasColumn($table, 'household_id') && DB::table($table)->whereIn('household_id', $householdIds)->exists()) throw new RuntimeException('Existing demo household is linked to '.$table.'; manual review is required.');
    }
    return $state;
}

$columns = [
    'active' => ['last_name'=>'C','first_name'=>'D','middle_name'=>'E','suffix'=>'F','relationship_to_head'=>'G','complete_address'=>'H',
        'birth_place'=>'I','birth_date'=>'J','recorded_age'=>'K','sex'=>'L','civil_status'=>'M','education_level'=>'N','religion'=>'O','occupation'=>'P','remarks'=>'Q'],
    'deceased' => ['household_number'=>'A','last_name'=>'B','first_name'=>'C','middle_name'=>'D','suffix'=>'E','relationship_to_head'=>'F','purok'=>'G',
        'birth_place'=>'H','birth_date'=>'I','recorded_age'=>'J','sex'=>'K','civil_status'=>'L','education_level'=>'M','religion'=>'N','occupation'=>'O','remarks'=>'P','death_date'=>'Q'],
    'new' => ['household_number'=>'A','last_name'=>'B','first_name'=>'C','middle_name'=>'D','suffix'=>'E','birth_date'=>'F','recorded_age'=>'G','sex'=>'H',
        'civil_status'=>'I','education_level'=>'J','religion'=>'K','occupation'=>'L','remarks'=>'M'],
];
$prepared = $issues = [];
foreach (['active', 'moved', 'deceased', 'new'] as $group) {
    foreach ($plan[$group] as $position => $row) {
        $data = [];
        foreach ($columns[$group === 'moved' ? 'active' : $group] as $field => $column) $data[$field] = $row[$column] ?? null;
        $remarks = trim((string) ($data['remarks'] ?? ''));
        foreach (['birth_date', 'death_date'] as $field) {
            if (! array_key_exists($field, $data)) continue;
            $raw = $data[$field];
            $data[$field] = tinagoDate($raw);
            if (filled($raw) && $data[$field] === null) {
                $remarks .= ' [Source '.$field.': '.$raw.']';
                $issues[] = ['source' => $row['_source'], 'field' => $field, 'raw' => $raw];
            }
        }
        $age = $data['recorded_age'] ?? null;
        $data['recorded_age'] = is_numeric($age) && (float) $age >= 0 && (float) $age <= 150 ? (int) $age : null;
        if (filled($age) && $data['recorded_age'] === null) $remarks .= ' [Source age: '.$age.']';
        $sex = $data['sex'] ?? '';
        $data['sex'] = match (strtoupper(trim($sex))) { 'M', 'MALE' => 'Male', 'F', 'FEMALE' => 'Female', default => null };
        if ($data['sex'] === null) $remarks .= ' [Source sex: '.($sex ?: 'not provided').']';
        if (in_array($group, ['active', 'moved'], true)) {
            $data['sex'] ??= '';
            if ($row['B'] === '') $remarks .= ' [Household assignment requires confirmation]';
        }
        $data['remarks'] = trim($remarks.' [Source: TINAGO.xlsx '.$row['_source'].']');
        $prepared[$group][$position] = $data;
    }
}
$before = tinagoCheckExisting($area);
$summary = ['barangay' => $area->name, 'primary_sheet' => TinagoWorkbook::PRIMARY_SHEET, 'source_sha256' => $sourceHash,
    'source_counts' => array_map('count', $plan), 'existing_counts' => array_map('count', $before),
    'residence_counts' => array_count_values(array_map(fn ($row) => TinagoWorkbook::residence($row)[0], $plan['active'])),
    'invalid_date_fields' => count($issues), 'invalid_dates' => $issues,
    'undated_history_rows' => count(array_filter($plan['new'], fn ($r) => $r['_reporting_month'] === null)),
    'unassigned_residents' => count(array_filter($plan['active'], fn ($r) => $r['B'] === ''))];
if (! in_array('--apply', $argv, true)) { echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL; exit(0); }
$driver = DB::connection()->getDriverName();
$host = config('database.connections.'.config('database.default').'.host');
if (! ($driver === 'sqlite' || ($driver === 'mysql' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)))) throw new RuntimeException('This import is restricted to the local database.');
if (! $plan['active'] || ! $plan['households']) throw new RuntimeException('No Tinago residents found.');
$directory = storage_path('app/import-backups');
if (! is_dir($directory) && ! mkdir($directory, 0775, true)) throw new RuntimeException('Cannot create backup directory.');
$stamp = now()->format('Ymd-His-u');
$backup = $directory.'/TINAGO-before-'.$stamp.'.json';
if (file_put_contents($backup, json_encode(['summary' => $summary, 'state' => $before, 'pending_source_rows' => $plan['pending']], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Backup failed.');
$reviewPath = $directory.'/TINAGO-review-'.$stamp.'.csv';
$review = fopen($reviewPath, 'x');
if ($review === false) throw new RuntimeException('Cannot create source review report.');
fputcsv($review, ['Source', 'Household', 'Last name', 'First name', 'Review reason'], ',', '"', '');
foreach ($plan['pending'] as $item) fputcsv($review, [$item['row']['_source'], $item['row']['B'] ?? '', $item['row']['C'] ?? '', $item['row']['D'] ?? '', $item['reason']], ',', '"', '');
foreach ($plan['active'] as $row) {
    [$status, $reason] = TinagoWorkbook::residence($row);
    if ($status === Inhabitant::RESIDENCE_UNCONFIRMED || $row['B'] === '') fputcsv($review, [$row['_source'], $row['B'], $row['C'], $row['D'], $reason.($row['B'] === '' ? '; household needs confirmation' : '')], ',', '"', '');
}
foreach ($issues as $issue) fputcsv($review, [$issue['source'], '', '', '', 'Invalid '.$issue['field'].' retained in remarks: '.$issue['raw']], ',', '"', '');
fclose($review);
DB::transaction(function () use ($area, $before, $source, $sourceHash, $plan, $prepared): void {
    Barangay::whereKey($area->id)->lockForUpdate()->firstOrFail();
    if (tinagoCheckExisting($area) !== $before) throw new RuntimeException('Tinago changed after the backup; import cancelled.');
    if (hash_file('sha256', $source) !== $sourceHash) throw new RuntimeException('Source workbook changed after preparation.');
    MigrationRecord::where('barangay_id', $area->id)->where('reason', MigrationForecastHistory::DEMO_MARKER)->delete();
    Inhabitant::where('barangay_id', $area->id)->where('remarks', MigrationForecastHistory::DEMO_MARKER)->delete();
    Household::where('barangay_id', $area->id)->where('household_number', 'DEMO-MIGRATION-V1')->delete();
    $households = [];
    foreach ($plan['households'] as $number => $address) $households[$number] = Household::create(['barangay_id'=>$area->id, 'household_number'=>(string)$number, 'purok'=>$address, 'address'=>$address]);
    foreach (['active', 'moved'] as $group) {
        foreach ($prepared[$group] ?? [] as $position => $data) {
            $row = $plan[$group][$position];
            $number = $row['B'] ?: 'Not recorded';
            $households[$number] ??= Household::create(['barangay_id'=>$area->id,'household_number'=>$number]);
            [$residence, $reason] = TinagoWorkbook::residence($row);
            Inhabitant::create($data + ['barangay_id'=>$area->id,'household_id'=>$households[$number]->id,
                'registry_sequence'=>null,'family_number'=>$row['B'] ?: null,'individual_number'=>null,
                'status'=>$group === 'active' ? Inhabitant::STATUS_ACTIVE : Inhabitant::STATUS_MIGRATED_OUT,
                'residence_status'=>$residence,'residence_source'=>'TINAGO.xlsx '.$row['_source'].': '.$reason]);
        }
    }
    foreach ($prepared['deceased'] ?? [] as $position => $data) DeceasedInhabitant::create($data + ['barangay_id'=>$area->id,'source_position'=>$position]);
    foreach ($prepared['new'] ?? [] as $position => $data) NewInhabitant::create($data + ['barangay_id'=>$area->id,'source_position'=>$position,
        'month_submitted'=>$plan['new'][$position]['O'],'reporting_month'=>$plan['new'][$position]['_reporting_month']]);
    $actual = tinagoState($area);
    if (count($actual['inhabitants']) !== count($plan['active']) + count($plan['moved']) || count($actual['deceased']) !== count($plan['deceased'])
        || count($actual['new']) !== count($plan['new']) || $actual['migration_records']) throw new RuntimeException('Import reconciliation failed.');
});
echo json_encode(['imported' => array_map('count', tinagoState($area)), 'source_summary' => $summary,
    'backup' => $backup, 'review_file' => $reviewPath], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
