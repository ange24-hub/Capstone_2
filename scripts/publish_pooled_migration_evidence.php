<?php
// Publish an already verified aggregate result to the local admin page, not a production prediction model.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=$argv[1]??'pooled-actual-review-20261108';
if(!preg_match('/\A[a-zA-Z0-9_-]+\z/',$run))throw new InvalidArgumentException('Use a simple run name.');
$directory=base_path('output/prediction-analysis/'.$run);
$read=fn($file)=>json_decode(file_get_contents($directory.'/'.$file),true,flags:JSON_THROW_ON_ERROR);
$result=$read('pooled-result.json');$source=$read('reconstructed-source.json');$checks=$read('independent-verification.json');
if(!$checks['passed']||$result['source_sha256']!==hash_file('sha256',$directory.'/reconstructed-source.json'))throw new RuntimeException('Evidence verification failed.');
$bundle=['run'=>$run,'published_at'=>now()->toIso8601String(),'result'=>$result,'source'=>$source,
    'workbooks'=>$read('workbook-review-summary.json'),'verification'=>$checks,
    'note'=>'Static retrospective research evidence. Publishing this report does not activate a future municipal forecast or verify any source record.'];
$disk=Illuminate\Support\Facades\Storage::disk('local');
$path='migration-reconstruction/published-pooled-evidence.json';
if($disk->exists($path))$disk->copy($path,'migration-reconstruction/published-pooled-evidence-before-'.now()->format('YmdHis').'.json');
if(!$disk->put($path,json_encode($bundle,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)))throw new RuntimeException('Unable to publish evidence.');
echo json_encode(['admin_route'=>route('municipal.prediction-forecast'),'run'=>$run,'future_forecast_activated'=>false],JSON_PRETTY_PRINT).PHP_EOL;
