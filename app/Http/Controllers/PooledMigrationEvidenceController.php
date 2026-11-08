<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ExperimentalPooledSvrForecast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PooledMigrationEvidenceController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole(User::ROLE_MUNICIPAL_LGU) && $request->user()->isApproved(), 403);
    }

    private function evidence(Request $request): array
    {
        $this->authorizeAdmin($request);
        $evidence=app(ExperimentalPooledSvrForecast::class)->publishedEvidence();
        abort_unless($evidence!==null,404,'No reviewed pooled SVR evidence is available.');
        return $evidence;
    }

    private function target(Request $request, bool $required=false): ?string
    {
        $current=now()->timezone('Asia/Manila')->startOfMonth();
        $allowed=array_map(fn($i)=>$current->copy()->addMonths($i)->format('Y-m'),[1,2,3]);
        $data=$request->validate(['target_month'=>[$required?'required':'nullable','date_format:Y-m',Rule::in($allowed)]]);
        return $data['target_month']??null;
    }

    private function barangay(Request $request): ?int
    {
        $request->validate(['barangay_id'=>['nullable','integer','exists:barangays,id']]);
        return $request->filled('barangay_id')?$request->integer('barangay_id'):null;
    }

    public function show(Request $request)
    {
        $this->authorizeAdmin($request);
        $barangayId=$this->barangay($request);
        return redirect()->route('municipal.prediction-forecast',array_filter(['barangay_id'=>$barangayId],fn($value)=>$value!==null));
    }

    public function saveFuture(Request $request, ExperimentalPooledSvrForecast $model)
    {
        $evidence=$this->evidence($request);
        $request->validate(['inference_artifact_sha256'=>['required','string','regex:/\A[0-9a-f]{64}\z/']]);
        $future=$model->build($evidence,$this->target($request,true),$this->barangay($request));
        abort_unless($future['ready'],422,$future['message']??'Experimental estimate unavailable.');
        abort_unless(hash_equals($future['inference_artifact_sha256'],$request->string('inference_artifact_sha256')->toString()),409,'The model changed after this preview. Refresh the preview before saving.');
        $id=(string)Str::uuid();
        $snapshot=['id'=>$id,'saved_at'=>now()->toIso8601String(),
            'saved_by'=>['id'=>$request->user()->id,'name'=>$request->user()->name],
            'forecast'=>$future,'evidence'=>$evidence];
        $disk=Storage::disk('local');$path='migration-reconstruction/future-forecasts/'.$id;
        abort_unless($disk->put($path.'.tmp',json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),503,'Unable to save forecast evidence.');
        if(!$disk->move($path.'.tmp',$path.'.json')){$disk->delete($path.'.tmp');abort(503,'Unable to save forecast evidence.');}
        return redirect()->route('municipal.prediction-pooled.future.show',$id);
    }

    private function savedFuture(Request $request,string $snapshot): array
    {
        $this->authorizeAdmin($request);
        abort_unless(preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',$snapshot),404);
        $path='migration-reconstruction/future-forecasts/'.$snapshot.'.json';
        abort_unless(Storage::disk('local')->exists($path),404);
        return json_decode(Storage::disk('local')->get($path),true,flags:JSON_THROW_ON_ERROR);
    }

    public function showFuture(Request $request,string $snapshot)
    {
        $saved=$this->savedFuture($request,$snapshot);
        return view('dashboards.migration-forecast-snapshot',['future'=>$saved['forecast'],'futureSnapshot'=>$saved]);
    }

    public function downloadFuture(Request $request,string $snapshot)
    {
        $saved=$this->savedFuture($request,$snapshot);
        return response()->streamDownload(fn()=>print(json_encode($saved,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
            'experimental-svr-future-'.$snapshot.'.json',['Content-Type'=>'application/json']);
    }

    public function download(Request $request)
    {
        $data=$this->evidence($request);
        return response()->streamDownload(fn()=>print(json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)),
            'pooled-svr-evidence.json',['Content-Type'=>'application/json']);
    }

    public function defense(Request $request)
    {
        $this->evidence($request);
        return response()->download(resource_path('pooled-svr-defense.txt'),'pooled-svr-defense.txt',['Content-Type'=>'text/plain; charset=UTF-8']);
    }
}
