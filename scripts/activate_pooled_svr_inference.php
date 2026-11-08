<?php
// Activate only the reviewed covered-record model. Never change municipal verification/targets.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$run=$argv[1]??'pooled-actual-review-20261108';
if(!preg_match('/\A[a-zA-Z0-9_-]+\z/',$run))throw new InvalidArgumentException('Use a simple run name.');
$directory=base_path('output/prediction-analysis/'.$run);
$bytes=file_get_contents($directory.'/pooled-svr-inference-v1.json');
$artifact=json_decode($bytes,true,flags:JSON_THROW_ON_ERROR);
$disk=Illuminate\Support\Facades\Storage::disk('local');
$evidence=json_decode($disk->get('migration-reconstruction/published-pooled-evidence.json'),true,flags:JSON_THROW_ON_ERROR);
if($artifact['run']!==$run||$artifact['source_sha256']!==$evidence['result']['source_sha256']
    ||hash_file('sha256',$directory.'/sample-svr/all-observed-sample-refit.joblib')!==$artifact['refit_model_sha256'])throw new RuntimeException('Refit/source provenance mismatch.');
$path=App\Services\ExperimentalPooledSvrForecast::PATH;
$previous=$disk->exists($path)?$disk->get($path):null;
if($previous!==null)$disk->put('migration-reconstruction/pooled-svr-inference-before-'.now()->format('YmdHis').'.json',$previous);
if(!$disk->put($path,$bytes))throw new RuntimeException('Unable to activate numeric model export.');
try{
    $service=app(App\Services\ExperimentalPooledSvrForecast::class);
    foreach($artifact['parity_cases']as$case){
        $prediction=$service->predictArea($case['month'],$case['area_id']);
        if(abs($prediction['raw_prediction']-$case['raw_prediction'])>1e-10)throw new RuntimeException('PHP/sklearn inference parity failed.');
    }
    $future=$service->build($evidence);
    if(!$future['ready'])throw new RuntimeException('Future estimate readiness failed.');
}catch(Throwable $e){
    if($previous===null)$disk->delete($path);else$disk->put($path,$previous);
    throw $e;
}
file_put_contents($directory.'/experimental-future-preview.json',json_encode($future,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$result=['activated_at'=>now()->toIso8601String(),'run'=>$run,'parity_cases_passed'=>count($artifact['parity_cases']),
    'inference_artifact_sha256'=>hash('sha256',$bytes),'refit_model_sha256'=>$artifact['refit_model_sha256'],
    'source_records_changed'=>false,'future_target'=>$future['target_month'],'covered_sample_sum'=>$future['covered_sample_sum'],
    'municipal_total'=>null,'experimental_covered_record_inference_only'=>true];
file_put_contents($directory.'/experimental-inference-activation.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
