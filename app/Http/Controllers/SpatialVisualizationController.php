<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\User;
use App\Services\GisLayers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpatialVisualizationController extends Controller
{
    public function __invoke(Request $request, GisLayers $layers): View
    {
        $isBarangaySecretary = $request->user()->hasRole(User::ROLE_BARANGAY);
        $scope = $layers->scope($request);
        $request->validate(['edit_household' => ['nullable', 'integer']]);
        $editingHousehold = $request->filled('edit_household') ? Household::findOrFail($request->integer('edit_household')) : null;
        if ($editingHousehold && $isBarangaySecretary) {
            abort_unless($editingHousehold->barangay_id === $request->user()->barangay_id, 403);
        }

        $registryQuery = Household::query()
            ->when($scope, fn ($query) => $query->where('barangay_id', $scope->id));
        $registeredHouseholdCount = (clone $registryQuery)->count();
        $households = (clone $registryQuery)->with(['barangay', 'inhabitants'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [-90, 90])
            ->whereBetween('longitude', [-180, 180])
            ->orderBy('household_number')
            ->get();

        $markers = $households->map(fn (Household $household): array => [
            'id' => $household->id,
            'edit_url' => ! $isBarangaySecretary || $household->barangay_id === $request->user()->barangay_id
                ? route('spatial.index', ['edit_household' => $household->id]) : null,
            'barangay' => $household->barangay->name,
            'household_number' => $household->household_number,
            'household_name' => $household->household_name ?: 'Household '.$household->household_number,
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
        $sourcePoints = $layers->read('building-points', $scope)['features'];
        $unmappedHouseholds = (clone $registryQuery)->with('barangay:id,name')
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude')
                ->orWhereNotBetween('latitude', [-90, 90])->orWhereNotBetween('longitude', [-180, 180]))
            ->when($isBarangaySecretary, fn ($query) => $query->where('barangay_id', $request->user()->barangay_id))
            ->orderBy('barangay_id')->orderBy('household_number')
            ->get(['id', 'barangay_id', 'household_name', 'household_number']);

        return view('spatial.index', [
            'editingHousehold' => $editingHousehold,
            'defaultFormBarangayId' => $editingHousehold?->barangay_id ?? ($isBarangaySecretary ? $request->user()->barangay_id : $scope?->id),
            'writableBarangayName' => $isBarangaySecretary ? $request->user()->barangay->name : null,
            'selectedBarangayId' => $scope?->id,
            'scopeName' => $scope?->name ?? 'Tomas Oppus',
            'layerUrls' => collect(array_keys(GisLayers::FILES))->mapWithKeys(fn ($layer) => [
                $layer => route('spatial.layers', array_filter(['layer' => $layer, 'barangay_id' => $scope?->id])),
            ]),
            'barangays' => $isBarangaySecretary
                ? Barangay::whereKey($request->user()->barangay_id)->get()
                : Barangay::orderBy('name')->get(),
            'markers' => $markers,
            'householdCount' => $households->count(),
            'populationCount' => $households->sum(fn (Household $household): int => $household->inhabitants->count()),
            'barangayCount' => count($layers->read('barangay-boundaries', $scope)['features']),
            'sourceBuildingCount' => count($sourcePoints),
            'sourceBoundaryReviewCount' => count(array_filter($sourcePoints, fn ($feature) => ! $feature['properties']['within_barangay_boundary'])),
            'unmappedHouseholdCount' => $registeredHouseholdCount - $households->count(),
            'unmappedHouseholds' => $unmappedHouseholds,
        ]);
    }
}
