<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{BarangayRbiUpdate, DeceasedInhabitant, Inhabitant, NewInhabitant};
use App\Support\{BrbiArchiveWorkbook as Workbook, ResidenceWorkbookReader};
use Illuminate\Support\Facades\DB;

$directory=storage_path('app/import-sources/brbi-20261108');
$initial=json_decode(file_get_contents($directory.'/import-result-summary.json'),true,flags:JSON_THROW_ON_ERROR);
$plan=json_decode(file_get_contents($initial['backup_directory'].'/source-plan.json'),true,flags:JSON_THROW_ON_ERROR);
$changes=[];$checked=['residents'=>0,'deceased'=>0];$areas=[];
foreach($plan['areas']as$item){
    if(!$item['registry'])continue;
    $area=$item['barangay_id'];$areas[]=$area;
    $sheets=ResidenceWorkbookReader::read($directory.'/'.$item['source_file'],[Workbook::REGISTRY_SHEETS[$item['name']],'DECEASED','Deceased','TRANSFERED']);
    $expected=Workbook::registry($item['name'],$sheets);
    foreach(['residents'=>Inhabitant::class,'deceased'=>DeceasedInhabitant::class]as$group=>$model){
        $actual=$model::where('barangay_id',$area)->get();
        if($actual->count()!==count($expected[$group]))throw new RuntimeException('Row count differs: '.$item['name'].' '.$group);
        $old=collect($item['registry'][$group])->keyBy('_source');
        foreach($expected[$group]as$person){
            $matching=$actual->filter(fn($p)=>str_contains((string)$p->remarks,'[Source: '.$person['_source'].']'));
            if($matching->count()!==1)throw new RuntimeException('Source row has ambiguous database provenance: '.$person['_source']);
            $record=$matching->first();$data=array_intersect_key($person,array_flip((new $model)->getFillable()));
            if($group==='deceased')$data['household_number']??='';else$data['sex']??='';
            foreach($data as$field=>$value){
                if((string)$record->getRawOriginal($field)===(string)$value)continue;
                if($group!=='deceased'||!in_array($person['_source'],['Cambite DECEASED row 29','Cambite DECEASED row 30','Cambite DECEASED row 31'],true))throw new RuntimeException('Field differs: '.$person['_source'].' '.$field);
                if((string)$record->getRawOriginal($field)!==(string)($old[$person['_source']][$field]??''))throw new RuntimeException('Imported record was edited; do not overwrite.');
                $changes[$record->id]['data'][$field]=$value;$changes[$record->id]['before']=$record->getAttributes();
            }
            $checked[$group]++;
        }
    }
}
if($changes){
    if(!in_array('--repair-imported-layout',$argv,true))throw new RuntimeException('Three newly imported mixed-column deceased rows need layout correction.');
    $path=$initial['backup_directory'].'/mixed-layout-correction.json';
    if(file_exists($path))throw new RuntimeException('Correction audit already exists.');
    file_put_contents($path,json_encode($changes,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    DB::transaction(function()use($changes){foreach($changes as$id=>$change){$record=DeceasedInhabitant::lockForUpdate()->findOrFail($id);if($record->getAttributes()!==$change['before'])throw new RuntimeException('Concurrent edit.');$record->update($change['data']);}});
}
function verifyHash($query): array {$h=hash_init('sha256');$n=0;foreach($query->orderBy('id')->cursor()as$row){hash_update($h,json_encode($row,JSON_THROW_ON_ERROR)."\n");$n++;}return ['rows'=>$n,'sha256'=>hash_final($h)];}
foreach(['households','inhabitants','deceased_inhabitants']as$table){
    $h=hash_init('sha256');$n=0;$stream=fopen($initial['backup_directory'].'/'.$table.'.jsonl','r');
    while(($line=fgets($stream))!==false){$row=json_decode($line,true,flags:JSON_THROW_ON_ERROR);if(!in_array($row['barangay_id'],$areas,true)){hash_update($h,$line);$n++;}}fclose($stream);
    if(verifyHash(DB::table($table)->whereNotIn('barangay_id',$areas))!==['rows'=>$n,'sha256'=>hash_final($h)])throw new RuntimeException('A previously populated barangay changed.');
}
$before=json_decode(file_get_contents($initial['backup_directory'].'/before-state.json'),true);
$ids=[];$stream=fopen($initial['backup_directory'].'/new_inhabitants.jsonl','r');while(($line=fgets($stream))!==false)$ids[]=json_decode($line,true)['id'];fclose($stream);
if(verifyHash(DB::table('new_inhabitants')->whereIn('id',$ids))!==$before['new_inhabitants'])throw new RuntimeException('Existing history changed.');
$newHistory=NewInhabitant::whereNotIn('id',$ids)->get();$checkedNew=0;
foreach($plan['areas']as$item){
    if(!$item['new_rows'])continue;
    $allowed=array_column($item['new_rows'],'_source');
    $inventory=json_decode(file_get_contents($directory.'/archive-inventory.json'),true);
    $file=collect($inventory)->firstWhere('file',$item['source_file']);
    $names=array_values(array_filter($file['sheets'],fn($s)=>str_contains(strtoupper($s),'NEW')||strtoupper($s)==='NIF'));
    foreach(ResidenceWorkbookReader::read($directory.'/'.$item['source_file'],$names)as$sheet=>$sourceRows){
        foreach(Workbook::newEntries($item['name'],$sheet,$sourceRows)as$person){
            if(!in_array($person['_source'],$allowed,true))continue;
            $matching=$newHistory->filter(fn($p)=>$p->barangay_id===$item['barangay_id'] && (int)$p->source_position===$person['source_position']);
            if($matching->count()!==1)throw new RuntimeException('New history provenance mismatch.');
            $record=$matching->first();$data=array_intersect_key($person,array_flip((new NewInhabitant)->getFillable()));
            $data['household_number']??='';$data['reporting_month']=$person['reporting_month'] ? $person['reporting_month'].'-01' : null;
            foreach($data as$field=>$value)if((string)$record->getRawOriginal($field)!==(string)$value)throw new RuntimeException('NEW source field differs: '.$person['_source'].' '.$field);
            $checkedNew++;
        }
    }
}
if($checkedNew!==$newHistory->count())throw new RuntimeException('Unexpected newly imported history.');
$oldForms=[];$stream=fopen($initial['backup_directory'].'/barangay_rbi_updates.jsonl','r');while(($line=fgets($stream))!==false)$oldForms[]=json_decode($line,true);fclose($stream);
foreach($oldForms as$old){$now=DB::table('barangay_rbi_updates')->where('id',$old['id'])->first();if((array)$now!==$old)throw new RuntimeException('An original RBI form changed.');}
$newReports=BarangayRbiUpdate::whereNotIn('id',array_column($oldForms,'id'))->get();$rows=0;$birthIssues=0;
foreach($newReports as$report){
    if($report->status!=='draft'||$report->submitted_at||$report->prepared_signature_path||$report->attested_signature_path)throw new RuntimeException('An import draft was submitted or signed.');
    if($report->barangayUser?->barangay?->name!==$report->barangay_name)throw new RuntimeException('Wrong assigned secretary.');
    app(App\Services\RbiDuplicateEntries::class)->validate($report->rows??[],[],$report->barangay_name,$report->reporting_month->format('Y-m'),$report->id);
    foreach($report->rows??[]as$row){
        if(!isset($row['history_record_id'],$row['source_row_id'],$row['first_saved_at']))throw new RuntimeException('Missing source linkage.');
        if(!empty($row['entry_type']))throw new RuntimeException('Classification was invented during import.');
        if(!empty($row['birth_date'])&&substr($row['birth_date'],0,7)>$report->reporting_month->format('Y-m'))$birthIssues++;
        $rows++;
    }
    if($report->rbiFamilies()->withCount('members')->get()->sum('members_count')!==count($report->rows??[]))throw new RuntimeException('Draft rows and form members differ.');
}
$again=app(App\Services\BrbiArchiveImport::class)->plan($directory);
foreach($again['areas']as$item)if($item['registry']||$item['new_rows'])throw new RuntimeException('Rerun would reimport source records.');
$eligibleMissing=0;$withoutMonth=0;$future=0;
foreach(App\Models\Barangay::all()as$area){
    $represented=[];
    foreach(BarangayRbiUpdate::where('barangay_name',$area->name)->get()as$report)foreach($report->rows??[]as$row){
        $row=App\Support\HouseholdRbi::normalize($row);
        if(!empty($row['history_record_id']))$represented['id:'.$row['history_record_id']]=true;
        $represented['person:'.Workbook::key($row)]=true;
    }
    foreach(NewInhabitant::where('barangay_id',$area->id)->get()as$record){
        if(isset($represented['id:'.$record->id])||isset($represented['person:'.Workbook::key($record->getAttributes())]))continue;
        $month=$record->reporting_month?->format('Y-m');
        if(!$month)$withoutMonth++;elseif($month>now()->format('Y-m'))$future++;else$eligibleMissing++;
    }
}
if($eligibleMissing)throw new RuntimeException('Some dated NEW history is not represented in a form.');
$result=['passed'=>true,'verified_registry_residents'=>$checked['residents'],'verified_deceased_records'=>$checked['deceased'],
    'new_unsigned_drafts'=>$newReports->count(),'new_form_rows'=>$rows,'history_records_total'=>NewInhabitant::count(),'verified_new_history_rows'=>$checkedNew,
    'previously_populated_barangays_unchanged'=>true,'original_rbi_forms_unchanged'=>true,'original_history_unchanged'=>true,
    'duplicate_source_reimport_prevented'=>true,'classifications_not_invented'=>true,
    'mixed_layout_rows_corrected'=>is_file($initial['backup_directory'].'/mixed-layout-correction.json') ? count(json_decode(file_get_contents($initial['backup_directory'].'/mixed-layout-correction.json'),true)) : 0,
    'new_draft_rows_with_birth_after_reporting_month'=>$birthIssues,
    'eligible_history_not_in_forms'=>$eligibleMissing,'pending_missing_reporting_month'=>$withoutMonth,'pending_future_reporting_month'=>$future];
file_put_contents($directory.'/verification.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
