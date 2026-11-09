<?php

namespace App\Http\Controllers;

use App\Services\DeploymentBrbiSync;

class DeploymentSyncStatusController extends Controller
{
    public function __invoke(DeploymentBrbiSync $sync)
    {
        abort_unless(app()->environment('production') && parse_url(config('app.url'), PHP_URL_HOST) === DeploymentBrbiSync::HOST, 404);
        return response()->json($sync->publicMetadata())->header('Cache-Control', 'no-store');
    }
}
