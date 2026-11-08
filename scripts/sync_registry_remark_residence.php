<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Inhabitant, MigrationRecord, RegistryActivity};
use App\Support\RegistryRemarkResidence;
use Illuminate\Support\Facades\DB;

$updates=[];$counts=[];
foreach(Inhabitant::with('barangay')->where('status',Inhabitant::STATUS_ACTIVE)->whereNotNull('remarks')
    ->where('remarks','!=',App\Services\MigrationForecastHistory::DEMO_MARKER)->get()as$person){
    $decision=RegistryRemarkResidence::decide($person->remarks,$person->barangay->name);
    if(!$decision['action'])continue;
    $before=$person->getAttributes();RegistryRemarkResidence::apply($person);
    if($before['status']===$person->status && $before['residence_status']===$person->residence_status)continue;
    $attributes=$person->only(['status','residence_status','residence_source']);
    $updates[]=['id'=>$person->id,'barangay_id'=>$person->barangay_id,'before'=>$before,'after'=>$attributes,'action'=>$decision['action']];
    $counts[$person->barangay->name][$decision['action']]=($counts[$person->barangay->name][$decision['action']]??0)+1;
}
if(!in_array('--apply',$argv,true)){echo json_encode(['rows_to_update'=>count($updates),'by_barangay'=>$counts,'migration_dates_created'=>0],JSON_PRETTY_PRINT).PHP_EOL;exit(0);}
$driver=DB::connection()->getDriverName();
if($driver!=='sqlite'&&!in_array(config('database.connections.'.config('database.default').'.host'),['127.0.0.1','localhost','::1'],true))throw new RuntimeException('Local database only.');
$directory=storage_path('app/import-backups/remarks-residence-'.now()->format('Ymd-His-u'));
if(!mkdir($directory,0775,true))throw new RuntimeException('Cannot create private backup.');
file_put_contents($directory.'/before-and-plan.json',json_encode($updates,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
$ledgerBefore=hash('sha256',json_encode(DB::table('migration_records')->orderBy('id')->get(),JSON_THROW_ON_ERROR));
$total=Inhabitant::count();
DB::transaction(function()use($updates,$ledgerBefore,$total){
    foreach($updates as$update){
        $person=Inhabitant::lockForUpdate()->findOrFail($update['id']);
        if($person->getAttributes()!==$update['before'])throw new RuntimeException('A resident changed after backup.');
        $person->update($update['after']);
        RegistryActivity::create(['barangay_id'=>$person->barangay_id,'inhabitant_id'=>$person->id,'user_id'=>null,
            'description'=>'Applied requested consolidated remarks rule: '.$update['action'],
            'changes'=>['record_type'=>'registry_remarks_residence','before'=>array_intersect_key($update['before'],$update['after']),
                'after'=>$update['after'],'movement_date_created'=>false]]);
    }
    if(Inhabitant::count()!==$total)throw new RuntimeException('A profile was deleted.');
    if(hash('sha256',json_encode(DB::table('migration_records')->orderBy('id')->get(),JSON_THROW_ON_ERROR))!==$ledgerBefore)throw new RuntimeException('Movement dates changed.');
});
foreach($updates as$update){$person=Inhabitant::findOrFail($update['id']);foreach($update['after']as$field=>$value)if($person->$field!==$value)throw new RuntimeException('Reconciliation failed.');}
$result=['updated'=>count($updates),'by_barangay'=>$counts,'profiles_deleted'=>0,'movement_dates_created'=>0,'backup_directory'=>$directory];
file_put_contents($directory.'/result.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
