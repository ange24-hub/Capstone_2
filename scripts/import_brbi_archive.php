<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\BrbiArchiveImport;
use Illuminate\Support\Facades\DB;

$source = storage_path('app/import-sources/brbi-20261108');
$tables = ['households','inhabitants','deceased_inhabitants','new_inhabitants','migration_records','barangay_rbi_updates',
    'barangay_rbi_families','barangay_rbi_members','barangay_rbi_deceased_records','registry_activities'];
function brbiHash($query): array {
    $hash=hash_init('sha256');$count=0;
    foreach ($query->orderBy('id')->cursor() as $row) {hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;}
    return ['rows'=>$count,'sha256'=>hash_final($hash)];
}
function brbiState(array $tables): array { $state=[];foreach($tables as$table)$state[$table]=brbiHash(DB::table($table));return $state; }
$before=brbiState($tables);
$importer=app(BrbiArchiveImport::class);
$plan=$importer->plan($source);
if (!in_array('--apply',$argv,true)) {echo json_encode($importer->summary($plan),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;exit(0);}
$driver=DB::connection()->getDriverName();
if ($driver!=='sqlite' && !($driver==='mysql' && in_array(config('database.connections.'.config('database.default').'.host'),['127.0.0.1','localhost','::1'],true))) throw new RuntimeException('Local database only.');
$directory=storage_path('app/import-backups/brbi-20261108-'.now()->format('Ymd-His-u'));
if(!mkdir($directory,0775,true))throw new RuntimeException('Cannot create private backup.');
foreach($tables as$table){
    $stream=fopen($directory.'/'.$table.'.jsonl','x');if(!$stream)throw new RuntimeException('Backup open failed.');
    foreach(DB::table($table)->orderBy('id')->cursor()as$row)if(fwrite($stream,json_encode($row,JSON_THROW_ON_ERROR)."\n")===false)throw new RuntimeException('Backup write failed.');
    fclose($stream);
}
file_put_contents($directory.'/before-state.json',json_encode($before,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
file_put_contents($directory.'/source-plan.json',json_encode($plan,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$targets=array_column(array_filter($plan['areas'],fn($a)=>$a['registry']!==null),'barangay_id');
$preserved=[];
foreach(['households','inhabitants','deceased_inhabitants']as$table)$preserved[$table]=brbiHash(DB::table($table)->whereNotIn('barangay_id',$targets));
$oldHistoryIds=DB::table('new_inhabitants')->pluck('id');
$oldHistory=brbiHash(DB::table('new_inhabitants')->whereIn('id',$oldHistoryIds));
$oldForms=App\Models\BarangayRbiUpdate::all();
$submittedIds=$oldForms->where('status','submitted')->modelKeys();
$submitted=brbiHash(DB::table('barangay_rbi_updates')->whereIn('id',$submittedIds));
$result=DB::transaction(function()use($before,$tables,$plan,$importer,$targets,$preserved,$oldHistoryIds,$oldHistory,$oldForms,$submittedIds,$submitted){
    App\Models\Barangay::orderBy('id')->lockForUpdate()->get();
    if(brbiState($tables)!==$before)throw new RuntimeException('Database changed during planning/backup. Rerun required.');
    $result=$importer->apply($plan);
    foreach($preserved as$table=>$hash)if(brbiHash(DB::table($table)->whereNotIn('barangay_id',$targets))!==$hash)throw new RuntimeException('Existing real registry changed: '.$table);
    if(brbiHash(DB::table('new_inhabitants')->whereIn('id',$oldHistoryIds))!==$oldHistory)throw new RuntimeException('Existing NEW history changed.');
    if(brbiHash(DB::table('barangay_rbi_updates')->whereIn('id',$submittedIds))!==$submitted)throw new RuntimeException('Submitted forms changed.');
    foreach($oldForms as$old){$new=$old->fresh();if(array_slice($new->rows??[],0,count($old->rows??[]))!==($old->rows??[]))throw new RuntimeException('Existing draft rows were overwritten.');}
    return $result;
});
file_put_contents($directory.'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$after=brbiState($tables);file_put_contents($directory.'/after-state.json',json_encode($after,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['backup_directory'=>$directory,'preserved_existing_real_registries'=>true,'preserved_existing_history'=>true,'preserved_submitted_forms'=>true,
    'summary'=>array_map(fn($r)=>array_diff_key($r,['pending'=>true])+['pending_review'=>count($r['pending'])],$result)],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
