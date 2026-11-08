<?php

// Only aggregate, explicitly classified/verified records. Never age-derived training labels.
require dirname(__DIR__).'/vendor/autoload.php';
$app=require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Barangay, BarangayRbiUpdate, Inhabitant};
use App\Services\{MigrationForecastHistory, MunicipalRbiReportHistory};
use Illuminate\Support\Facades\DB;

$name=$argv[1]??'actual-only-verified-20261108';
if(!preg_match('/\A[a-zA-Z0-9_-]+\z/',$name))throw new InvalidArgumentException('Use a simple output directory name.');
$out=base_path('output/prediction-analysis/'.$name);
if(file_exists($out))throw new RuntimeException('Use a fresh output directory.');
mkdir($out,0775,true);
$tables=['inhabitants','new_inhabitants','migration_records','barangay_rbi_updates','migration_reporting_periods','rbi_arrival_periods'];
$state=function()use($tables){$a=[];foreach($tables as$t)$a[$t]=['rows'=>DB::table($t)->count(),'latest_update'=>DB::table($t)->max('updated_at')];return $a;};
$before=$state();
$inventory=app(MunicipalRbiReportHistory::class)->build(false);
$classified=['confirmed_arrival_entries'=>0,'other_classified_entries'=>0,'unclassified_dated_entries'=>0,'fully_classified_observed_area_months'=>0];
foreach($inventory['datasets']as$dataset)foreach($dataset['entries']as$month=>$entries){
    $pending=0;
    foreach($entries as$entry){
        if(!$entry['confirmed']){$classified['unclassified_dated_entries']++;$pending++;}
        elseif($entry['type']==='in_migration')$classified['confirmed_arrival_entries']++;
        else$classified['other_classified_entries']++;
    }
    if($entries&&!$pending&&empty($dataset['duplicates'][$month])&&count($dataset['reports'][$month]??[])<=1)$classified['fully_classified_observed_area_months']++;
}
$areas=Barangay::orderBy('name')->get(['id','name'])->toArray();
array_unshift($areas,['id'=>null,'name'=>'All configured barangays']);
$end=now()->startOfMonth()->subMonth();$scopes=[];
foreach($areas as$area){
    $scope=$area;
    foreach(['in','out']as$type){
        $source=app(MigrationForecastHistory::class)->build($area['id'],$end,36,$type);
        $scope[$type]=['date_basis'=>$type==='in'?'classified_rbi_first_save_month':'actual_departure_date',
            'rows'=>array_map(fn($r)=>['month'=>$r['month'],'count'=>$r['verified']?$r['count']:null,'verified'=>$r['verified'],
                'recorded_count'=>$r['count'],'status'=>$r['status']],$source['rows']),
            'verified_months'=>count(array_filter($source['rows'],fn($r)=>$r['verified'])),
            'consecutive_through_cutoff'=>count($source['consecutive']),'excluded_demo_events'=>$source['excluded_demo_events']];
    }
    $scopes[]=$scope;
}
$real=Inhabitant::where(fn($q)=>$q->whereNull('remarks')->orWhere('remarks','!=',MigrationForecastHistory::DEMO_MARKER));
$undated=(clone$real)->where(fn($q)=>$q->where('status','migrated_out')->orWhere(fn($q)=>$q->where('status','active')->where('residence_status','living_elsewhere')))
    ->whereDoesntHave('migrationRecords',fn($q)=>$q->where('type','out')->where(fn($q)=>$q->whereNull('reason')->orWhere('reason','!=',MigrationForecastHistory::DEMO_MARKER)))->count();
$ledger=DB::table('migration_records as m')->leftJoin('inhabitants as i','i.id','=','m.inhabitant_id')->leftJoin('households as h','h.id','=','i.household_id')
    ->where(fn($q)=>$q->whereNull('m.reason')->orWhere('m.reason','!=',MigrationForecastHistory::DEMO_MARKER))
    ->where(fn($q)=>$q->whereNull('i.remarks')->orWhere('i.remarks','!=',MigrationForecastHistory::DEMO_MARKER))
    ->where(fn($q)=>$q->whereNull('h.household_number')->orWhere('h.household_number','!=','DEMO-MIGRATION-V1'))
    ->selectRaw("m.type, DATE_FORMAT(m.movement_date, '%Y-%m') as month, COUNT(*) as count")->groupBy('m.type','month')->get();
$after=$state();if($before!==$after)throw new RuntimeException('Source changed during export; rerun.');
$result=['format'=>'rbim.actual_verified_training.v1','exported_at'=>now()->toIso8601String(),'source_cutoff'=>$end->format('Y-m'),
    'synthetic_rows'=>0,'age_estimated_training_rows'=>0,'missing_is_zero'=>false,'classified_archive'=>$classified,
    'reports'=>['total'=>BarangayRbiUpdate::count(),'drafts'=>BarangayRbiUpdate::where('status','draft')->count(),
        'submitted'=>BarangayRbiUpdate::where('status','submitted')->count()],
    'undated_away_or_transferred_profiles'=>$undated,'actual_movement_months'=>$ledger,
    'scopes'=>$scopes,'before'=>$before,'after'=>$after,'database_changed'=>false,
    'availability_note'=>'Retrospective verified snapshot; immutable historical as-of versions are unavailable. No claim that a forecast was issued at an old origin.',
    'target_note'=>'Live arrivals predict newly recorded classified arrivals by first-save month, not physical arrival month. Departures require actual dates.'];
$result['source_sha256']=hash('sha256',json_encode($result,JSON_THROW_ON_ERROR));
file_put_contents($out.'/actual-source.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode(['directory'=>$out,'classified_archive'=>$classified,'undated_movement_profiles'=>$undated,
    'municipal_in_verified_months'=>$scopes[0]['in']['verified_months'],'municipal_out_verified_months'=>$scopes[0]['out']['verified_months']],JSON_PRETTY_PRINT).PHP_EOL;
