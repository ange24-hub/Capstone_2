<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$barangay = App\Models\Barangay::where('name', 'Biasong')->firstOrFail();
$backup = json_decode(file_get_contents(storage_path('app/import-backups/residence-3-20260905-183940-710817.json')), true, 512, JSON_THROW_ON_ERROR);
$rows = App\Models\Inhabitant::where('barangay_id', $barangay->id)->whereIn('id', array_column($backup['before'], 'id'))->get()->keyBy('id');
if ($rows->count() !== count($backup['before'])) throw new RuntimeException('Backup ownership/count mismatch');
$restore = [];
foreach ($backup['before'] as $before) {
    $row = $rows[$before['id']];
    if ($row->residence_status === $before['residence_status'] && $row->residence_source === $before['residence_source']) continue;
    $expected = $backup['plan']['updates'][$row->id];
    if ($row->residence_status !== $expected['residence_status'] || $row->residence_source !== $expected['residence_source']) throw new RuntimeException('Residence changed after sync: '.$row->id);
    $restore[] = $before;
}
$path = storage_path('app/import-backups/biasong-residence-rollback-'.now()->format('Ymd-His-u').'.json');
if (file_put_contents($path, $rows->toJson(JSON_PRETTY_PRINT)) === false) throw new RuntimeException('Backup failed');
Illuminate\Support\Facades\DB::transaction(function () use ($restore, $barangay) {
    foreach ($restore as $before) Illuminate\Support\Facades\DB::table('inhabitants')->where('barangay_id', $barangay->id)->where('id', $before['id'])->update(['residence_status'=>$before['residence_status'],'residence_source'=>$before['residence_source']]);
});
echo json_encode(['barangay'=>$barangay->name,'restored'=>count($restore),'backup'=>$path], JSON_PRETTY_PRINT).PHP_EOL;
