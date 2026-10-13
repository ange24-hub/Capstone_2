<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HouseholdSyncController extends Controller
{
    public function registry(Request $request): JsonResponse
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay, 403, 'Your secretary account is not assigned to a barangay.');
        $records = Household::where('barangay_id', $barangay->id)
            ->where('household_number', '!=', 'Not recorded')
            ->with(['inhabitants' => fn ($query) => $query->where('status', 'active')->orderBy('last_name')->orderBy('first_name')])
            ->orderBy('household_number')->get()->flatMap(function (Household $household) {
                $records = $household->inhabitants->map(fn ($resident) => [
                    'key' => 'resident-'.$resident->id,
                    'household_id' => $household->id,
                    'household_number' => $household->household_number,
                    'name' => $resident->fullName(),
                    'search_names' => [$resident->fullName(), trim(implode(' ', array_filter([
                        $resident->last_name, $resident->first_name, $resident->middle_name, $resident->suffix,
                    ])))],
                ]);
                if (filled($household->household_name)) {
                    $records->push([
                        'key' => 'household-'.$household->id,
                        'household_id' => $household->id,
                        'household_number' => $household->household_number,
                        'name' => $household->household_name,
                        'search_names' => [$household->household_name],
                    ]);
                }

                return $records;
            })->values();

        return response()->json(['barangay_id' => $barangay->id, 'barangay_name' => $barangay->name, 'records' => $records])
            ->header('Cache-Control', 'no-store');
    }

    public function session(Request $request): JsonResponse
    {
        abort_unless($request->user()->barangay_id, 403, 'Your secretary account is not assigned to a barangay.');

        return response()->json([
            'csrf_token' => csrf_token(),
            'barangay_id' => $request->user()->barangay_id,
            'barangay_name' => $request->user()->barangay->name,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $barangayId = $request->user()->barangay_id;
        abort_unless($barangayId, 403, 'Your secretary account is not assigned to a barangay.');
        abort_if($request->filled('barangay_id') && $request->integer('barangay_id') !== $barangayId, 403);

        $validated = $request->validate([
            'offline_uuid' => ['required', 'string', 'max:100'],
            'household_number' => ['required', 'string', 'max:100'],
            'household_name' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'location_captured_at' => ['required', 'date'],
            'household_id' => ['nullable', 'integer'],
        ]);

        $receipt = DB::table('household_offline_syncs')->where('offline_uuid', $validated['offline_uuid'])->first();
        if ($receipt) {
            $household = Household::findOrFail($receipt->household_id);
            abort_unless($household->barangay_id === $barangayId, 403);

            return response()->json(['success' => true, 'household_id' => $household->id]);
        }

        if (! empty($validated['household_id'])) {
            return DB::transaction(function () use ($validated, $barangayId) {
                $household = Household::lockForUpdate()->findOrFail($validated['household_id']);
                abort_unless($household->barangay_id === $barangayId, 403);
                abort_unless($household->household_number === $validated['household_number'], 422, 'The selected household number has changed. Refresh the consolidated list.');
                $uuidOwner = Household::where('offline_uuid', $validated['offline_uuid'])->first();
                abort_if($uuidOwner && $uuidOwner->id !== $household->id, 409, 'This offline UUID belongs to another household.');
                $inserted = DB::table('household_offline_syncs')->insertOrIgnore([
                    'offline_uuid' => $validated['offline_uuid'], 'household_id' => $household->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $receipt = DB::table('household_offline_syncs')->where('offline_uuid', $validated['offline_uuid'])->first();
                abort_unless($receipt && (int) $receipt->household_id === $household->id, 409, 'This offline UUID belongs to another household.');
                if ($inserted) {
                    $household->update(collect($validated)->only(['latitude', 'longitude', 'gps_accuracy', 'location_captured_at'])->all());
                }

                return response()->json(['success' => true, 'household_id' => $household->id]);
            });
        }

        try {
            $household = Household::firstOrCreate(
                ['offline_uuid' => $validated['offline_uuid']],
                array_merge($validated, ['barangay_id' => $barangayId])
            );
        } catch (QueryException $exception) {
            // A different offline UUID must not overwrite an existing registry household.
            if (Household::where('barangay_id', $barangayId)->where('household_number', $validated['household_number'])->exists()) {
                return response()->json(['message' => 'This household number already exists in your barangay. The offline record remains pending.'], 409);
            }

            throw $exception;
        }

        abort_unless($household->barangay_id === $barangayId, 403);

        return response()->json(['success' => true, 'household_id' => $household->id]);
    }
}
