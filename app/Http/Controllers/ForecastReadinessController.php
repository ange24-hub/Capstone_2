<?php

namespace App\Http\Controllers;

use App\Models\{Barangay, User};
use App\Services\ForecastReadiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastReadinessController extends Controller
{
    public function __invoke(Request $request, ForecastReadiness $readiness): View
    {
        $validated = $request->validate(['barangay_id' => ['nullable', 'integer', 'exists:barangays,id']]);
        $id = isset($validated['barangay_id']) ? (int) $validated['barangay_id'] : null;
        return view('reports.forecast-readiness', [
            'report' => $readiness->build($request->user(), $id),
            'barangays' => $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU)
                ? Barangay::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
