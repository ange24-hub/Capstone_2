<?php

require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory=storage_path('app/import-sources/brbi-20261108');
$import=json_decode(file_get_contents($directory.'/import-result-summary.json'),true);
$before=[];$work=[];
$note='[Review: source birth date is after the reporting month. Confirm the original dates.]';
foreach(App\Models\BarangayRbiUpdate::where('source_file_path','like','import-sources/brbi-20261108/%')->where('status','draft')->get()as$report){
    $rows=$report->rows??[];$changed=false;
    foreach($rows as&$row){
        if(empty($row['birth_date'])||substr($row['birth_date'],0,7)<=$report->reporting_month->format('Y-m')||str_contains($row['remarks']??'',$note))continue;
        $row['remarks']=trim(($row['remarks']??'').' '.$note);$changed=true;
    }unset($row);
    if($changed){$before[$report->id]=$report->getAttributes();$work[$report->id]=$rows;}
}
if($work){
    $path=$import['backup_directory'].'/draft-date-review-before.json';
    if(file_exists($path))throw new RuntimeException('Date-review backup already exists.');
    file_put_contents($path,json_encode($before,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    Illuminate\Support\Facades\DB::transaction(function()use($work,$before){
        foreach($work as$id=>$rows){
            $report=App\Models\BarangayRbiUpdate::lockForUpdate()->findOrFail($id);
            if($report->getAttributes()!==$before[$id])throw new RuntimeException('Concurrent draft edit.');
            $report->update(['rows'=>$rows]);
            foreach($report->rbiFamilies()->with('members')->get()as$family)foreach($family->members as$member){
                $details=$member->details??[];
                foreach($rows as$row)if(($row['history_record_id']??null)===($details['history_record_id']??null)){
                    if(($details['remarks']??'')!==($row['remarks']??'')){$details['remarks']=$row['remarks'];$member->update(['details'=>$details]);}
                    break;
                }
            }
        }
    });
}
echo json_encode(['drafts_with_review_notes_added'=>count($work),'source_dates_changed'=>false,'classifications_changed'=>false],JSON_PRETTY_PRINT).PHP_EOL;
