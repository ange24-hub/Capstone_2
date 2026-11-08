<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=$argv[1]??'pooled-actual-review-20261108';
if(!preg_match('/\A[a-zA-Z0-9_-]+\z/',$run))throw new InvalidArgumentException('Use a simple run name.');
$directory=base_path('output/prediction-analysis/'.$run);
$integrity=json_decode(file_get_contents($directory.'/source-integrity.json'),true,flags:JSON_THROW_ON_ERROR);
$current=[];
foreach($integrity['before']as$table=>$old){
    if(!in_array($table,['inhabitants','new_inhabitants','migration_records','barangay_rbi_updates','migration_reporting_periods','rbi_arrival_periods'],true))throw new RuntimeException('Unexpected source table.');
    $hash=hash_init('sha256');$count=0;
    foreach(Illuminate\Support\Facades\DB::table($table)->orderBy('id')->cursor()as$row){hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;}
    $current[$table]=['rows'=>$count,'sha256'=>hash_final($hash)];
}
if($current!==$integrity['before'])throw new RuntimeException('Source changed after the reconstruction; published results are a historical snapshot.');
$books=json_decode(file_get_contents($directory.'/workbook-review-summary.json'),true,flags:JSON_THROW_ON_ERROR);
foreach($books['files']as$file){
    $source=storage_path('app/import-sources/brbi-20261108/'.basename($file['file']));
    if(hash_file('sha256',$source)!==$file['sha256'])throw new RuntimeException('Original workbook changed.');
}
$result=['passed'=>true,'source_tables'=>count($current),'original_workbooks'=>count($books['files']),
    'source_tables_and_workbooks_unchanged'=>true,'verified_at'=>now()->toIso8601String(),
    'classifications_written'=>0,'movement_dates_written'=>0,'official_verifications_written'=>0];
file_put_contents($directory.'/final-source-check.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
