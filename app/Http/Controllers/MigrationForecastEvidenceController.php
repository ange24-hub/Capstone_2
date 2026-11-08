<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MigrationForecastEvidence;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MigrationForecastEvidenceController extends Controller
{
    public function __invoke(Request $request, MigrationForecastEvidence $evidence): StreamedResponse
    {
        abort_unless($request->user()->isApproved() && $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU), 403);
        $json = json_encode($evidence->build(true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return response()->streamDownload(fn () => print($json.PHP_EOL),
            'migration-forecast-evidence-'.now()->format('Ymd-His').'.json',
            ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
    }
}
