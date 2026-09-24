<?php

namespace App\Http\Controllers;

use App\Services\GisLayers;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GisLayerController extends Controller
{
    public function __invoke(Request $request, string $layer, GisLayers $layers): Response
    {
        $scope = $layers->scope($request);
        // Stream large municipal hazard files without decoding their coordinates in PHP.
        if (! in_array($layer, ['barangay-boundaries', 'building-points'], true)) {
            return response()->file($layers->path($layer), [
                'Content-Type' => 'application/geo+json',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->json($layers->read($layer, $scope))
            ->header('Content-Type', 'application/geo+json')
            ->header('Cache-Control', 'private, no-store');
    }
}
