<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpatialVisualizationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $households = Household::with(['barangay', 'inhabitants'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->when($request->user()->hasRole(User::ROLE_BARANGAY) && $request->user()->barangay_id, fn ($query) => $query
                ->where('barangay_id', $request->user()->barangay_id))
            ->when($request->filled('barangay_id'), fn ($query) => $query
                ->where('barangay_id', $request->integer('barangay_id')))
            ->orderBy('household_number')
            ->get();

        $markers = $households->map(fn (Household $household): array => [
            'id' => $household->id,
            'barangay' => $household->barangay->name,
            'household_number' => $household->household_number,
            'address' => $household->address ?: 'No address recorded',
            'latitude' => (float) $household->latitude,
            'longitude' => (float) $household->longitude,
            'population' => $household->inhabitants->count(),
            'residents' => $household->inhabitants
                ->take(6)
                ->map(fn ($inhabitant): string => $inhabitant->fullName())
                ->values()
                ->all(),
        ])->values();

        return view('spatial.index', [
            'barangays' => Barangay::orderBy('name')->get(),
            'markers' => $markers,
            'householdCount' => $households->count(),
            'populationCount' => $households->sum(fn (Household $household): int => $household->inhabitants->count()),
            'barangayCount' => $households->pluck('barangay_id')->unique()->count(),
        ]);
    }
}
