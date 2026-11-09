<?php

namespace App\Http\Controllers;

use App\Services\DeploymentBrbiSync;
use Illuminate\Http\Request;

/** Temporary service-to-service authentication; no user sessions, arbitrary uploads or import execution. */
class PrivateBrbiTransferController extends Controller
{
    public function __invoke(Request $request, DeploymentBrbiSync $sync)
    {
        abort_unless(app()->environment('production') && parse_url(config('app.url'),PHP_URL_HOST)===DeploymentBrbiSync::HOST
            && $request->isSecure() && $request->getHost()===DeploymentBrbiSync::HOST,404);
        abort_if(now()->greaterThan(\Illuminate\Support\Carbon::parse(DeploymentBrbiSync::TRANSPORT_EXPIRES_AT)),410);
        $body=$request->getContent();
        abort_if(strlen($body)>512*1024,413);
        $timestamp=(string)$request->header('X-Brbi-Timestamp','');
        abort_unless(ctype_digit($timestamp) && abs(now()->timestamp-(int)$timestamp)<=120,401);
        abort_unless($sync->verifyPrivateTransferSignature($body,$timestamp,(string)$request->header('X-Brbi-Signature','')),401);
        abort_unless(hash_equals($sync->expectedEnvelopeSha256(),hash('sha256',$body)),422);
        try {$result=$sync->stagePrivateTransfer($body);}
        catch(\Throwable){return response()->json(['state'=>'transfer_rejected'],422);}
        return response()->json($result,202)->header('Cache-Control','private, no-store');
    }
}
