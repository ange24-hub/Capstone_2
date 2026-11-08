<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/** Inference from an exported fitted sklearn estimator, never assumed migration rates. */
class ExperimentalPooledSvrForecast
{
    public const PATH = 'migration-reconstruction/pooled-svr-inference.json';
    public const FEATURES = ['month_sin','month_cos','previous_observed','previous_missing','gap_months'];
    private ?array $model = null;
    private ?string $artifactHash = null;

    private function artifact(): array
    {
        if ($this->model !== null) return $this->model;
        $disk = Storage::disk('local');
        $bundled=resource_path('forecast-models/pooled-recorded-arrivals-svr.json');
        $bytes=$disk->exists(self::PATH)?$disk->get(self::PATH)
            : (config('services.experimental_pooled_svr.bundled_fallback',true)&&is_file($bundled)?file_get_contents($bundled):null);
        if ($bytes===null) throw new RuntimeException('Experimental SVR inference is not available yet. Historical evidence remains available.');
        try { $data=json_decode($bytes,true,512,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new RuntimeException('The experimental model export is invalid; estimates are unavailable.'); }
        $valid = is_array($data)&&($data['format']??null)==='rbim.pooled_recorded_arrivals_rbf_svr.v1'
            && ($data['target']??null)==='existing_classified_arrival_entries_in_observed_source_month'
            && ($data['numeric_features']??null)===self::FEATURES && is_array($data['areas']??null)&&!empty($data['areas'])
            && is_array($data['area_categories']??null)&&!empty($data['area_categories'])
            && is_array($data['scaler']['mean']??null)&&is_array($data['scaler']['scale']??null)
            && count($data['scaler']['mean'])===5 && count($data['scaler']['scale'])===5
            && isset($data['run'],$data['source_sha256'],$data['training']['rows'],$data['training']['end'],$data['training']['parameters'],
                $data['training']['max_observed_gap_months'],$data['refit_model_sha256'],$data['historical_evaluation']);
        $numeric=fn($v)=>is_numeric($v)&&is_finite((float)$v);
        if ($valid) {
            foreach ($data['scaler']['mean'] as $v) $valid=$valid&&$numeric($v);
            foreach ($data['scaler']['scale'] as $v) $valid=$valid&&$numeric($v)&&$v>0;
            $valid=$valid&&$numeric($data['svr']['gamma']??null)&&$data['svr']['gamma']>0&&$numeric($data['svr']['intercept']??null);
            $vectors=$data['svr']['support_vectors']??[];$coefficients=$data['svr']['dual_coefficients']??[];
            $valid=$valid&&is_array($vectors)&&is_array($coefficients)&&count($vectors)>0&&count($vectors)===count($coefficients);
            foreach ((array)$vectors as $vector) {
                $valid=$valid&&is_array($vector)&&count($vector)===5+count($data['area_categories']);
                foreach((array)$vector as$v)$valid=$valid&&$numeric($v);
            }
            foreach((array)$coefficients as$v)$valid=$valid&&$numeric($v);
            $ids=[];
            foreach($data['areas'] as$area){
                if(!is_array($area)){$valid=false;continue;}
                $ids[]=(string)($area['id']??'');
                $valid=$valid&&isset($area['name'])&&preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/',$area['last_observed_month']??'')
                    &&$numeric($area['last_observed_count']??null)&&$area['last_observed_count']>=0;
            }
            sort($ids);$categories=array_map('strval',$data['area_categories']);sort($categories);
            $valid=$valid&&$ids===$categories&&count(array_unique($ids))===count($ids);
        }
        if (!$valid) throw new RuntimeException('The experimental model export failed validation; estimates are unavailable.');
        $this->artifactHash=hash('sha256',$bytes);
        return $this->model=$data;
    }

    public function predictArea(string $month, int $areaId): array
    {
        if(!preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/',$month))throw new InvalidArgumentException('Choose a valid target month.');
        $model=$this->artifact();
        $area=collect($model['areas'])->firstWhere('id',$areaId);
        if(!$area)throw new InvalidArgumentException('This barangay was not represented in the fitted model.');
        if($month<=$area['last_observed_month'])throw new InvalidArgumentException('The target must be after the last observed source month.');
        $target=Carbon::createFromFormat('!Y-m',$month);$last=Carbon::createFromFormat('!Y-m',$area['last_observed_month']);
        $gap=($target->year-$last->year)*12+$target->month-$last->month;
        $numeric=[sin(2*M_PI*$target->month/12),cos(2*M_PI*$target->month/12),(float)$area['last_observed_count'],0.,(float)$gap];
        $scaled=[];
        foreach($numeric as$i=>$value)$scaled[]=($value-$model['scaler']['mean'][$i])/$model['scaler']['scale'][$i];
        foreach($model['area_categories']as$id)$scaled[]=(float)((string)$areaId===(string)$id);
        $raw=(float)$model['svr']['intercept'];$maxKernel=0.;
        foreach($model['svr']['support_vectors']as$i=>$support){
            $distance=0.;foreach($support as$j=>$value)$distance+=($scaled[$j]-$value)**2;
            $kernel=exp(-$model['svr']['gamma']*$distance);$maxKernel=max($maxKernel,$kernel);
            $raw+=$model['svr']['dual_coefficients'][$i]*$kernel;
        }
        if(!is_finite($raw))throw new RuntimeException('The model returned a non-finite estimate.');
        return ['barangay_id'=>$areaId,'barangay'=>$area['name'],'target_month'=>$month,
            'last_observed_month'=>$area['last_observed_month'],'last_observed_count'=>$area['last_observed_count'],
            'gap_months'=>$gap,'features'=>array_combine(self::FEATURES,$numeric),
            'raw_prediction'=>$raw,'prediction'=>max(0.,$raw),
            'outside_fitted_gap_range'=>$gap>$model['training']['max_observed_gap_months'],
            'intercept_dominated'=>$maxKernel<1e-10];
    }

    public function publishedEvidence(): ?array
    {
        $disk=Storage::disk('local');
        $path='migration-reconstruction/published-pooled-evidence.json';
        $bundled=resource_path('forecast-models/pooled-recorded-arrivals-evidence.json');
        $bytes=$disk->exists($path)?$disk->get($path)
            : (config('services.experimental_pooled_svr.bundled_fallback',true)&&is_file($bundled)?file_get_contents($bundled):null);
        return $bytes===null?null:json_decode($bytes,true,512,JSON_THROW_ON_ERROR);
    }

    public function preview(?int $barangayId=null, ?string $month=null): array
    {
        $evidence=$this->publishedEvidence();
        if($evidence===null)
            return ['ready'=>false,'message'=>'The reviewed experimental SVR evidence is not available yet.'];
        return $this->build($evidence,$month,$barangayId);
    }

    public function build(array $evidence, ?string $month=null, ?int $barangayId=null): array
    {
        $current=now()->timezone('Asia/Manila')->startOfMonth();
        $month??=$current->copy()->addMonth()->format('Y-m');
        if(!preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/',$month)||$month<$current->copy()->addMonth()->format('Y-m')||$month>$current->copy()->addMonths(3)->format('Y-m'))
            throw new InvalidArgumentException('Choose one of the next three months.');
        try { $model=$this->artifact(); }
        catch(RuntimeException $e){ return ['ready'=>false,'message'=>$e->getMessage()]; }
        if(($evidence['run']??null)!==$model['run']||($evidence['result']['source_sha256']??null)!==$model['source_sha256'])
            return ['ready'=>false,'message'=>'The fitted model and published evidence do not match. Re-export the matching model before displaying estimates.'];
        $areas=array_values(array_filter($model['areas'],fn($area)=>$barangayId===null||(int)$area['id']===$barangayId));
        if(!$areas)return ['ready'=>false,'target_month'=>$month,'selected_barangay_id'=>$barangayId,
            'message'=>'No trained in-migration SVR is available for the selected barangay. Estimates from other barangays are not substituted.'];
        $rows=[];foreach($areas as$area)$rows[]=$this->predictArea($month,(int)$area['id']);
        return ['ready'=>true,'format'=>'rbim.experimental_pooled_future.v1','generated_at'=>now()->toIso8601String(),
            'run'=>$model['run'],'target_month'=>$month,'target'=>$model['target'],'selected_barangay_id'=>$barangayId,
            'label'=>'Experimental future recorded-arrival estimate — covered barangays only',
            'training'=>$model['training'],'source_sha256'=>$model['source_sha256'],
            'refit_model_sha256'=>$model['refit_model_sha256'],
            'inference_artifact_sha256'=>$this->artifactHash,
            'rows'=>$rows,'covered_sample_sum'=>array_sum(array_column($rows,'prediction')),
            'municipal_total'=>null,'out_migration_prediction'=>null,
            'historical_evaluation'=>$model['historical_evaluation'],'future_accuracy'=>['mae'=>null,'rmse'=>null],
            'limitations'=>['This model predicts classified entries conditional on comparable future source reporting, not all physical arrivals.',
                'Only fitted barangays are covered. Their sum is not a Tomas Oppus municipal total.',
                'Inputs remain the last observed values from the frozen training snapshot. New records do not automatically retrain the model.',
                'No missing monthly counts, intervening actuals or movement dates are invented; no recursive forecasts are used as observations.',
                'Long gaps exceed training conditions. RBF outputs can collapse toward the intercept and do not establish real future stability.',
                'Historical MAE/RMSE describe five retrospective sample rows, not the accuracy of this future estimate.',
                'No calibrated prediction interval or out-migration estimate is available.'],
            'source_records_changed'=>false];
    }
}
