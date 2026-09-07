<?php

// Included by import_iniguihan.php after the source audit and database backup.
use App\Models\Barangay;
use App\Models\DeceasedInhabitant;
use App\Models\Household;
use App\Models\Inhabitant;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($barangay, $activeRows, $deceasedRows, $sourceHouseholds): void {
    Barangay::whereKey($barangay->id)->lockForUpdate()->firstOrFail();
    if (Household::where('barangay_id', $barangay->id)->count() !== $sourceHouseholds->count()
        || Inhabitant::where('barangay_id', $barangay->id)->count() !== $activeRows->count() + $deceasedRows->count()
        || DeceasedInhabitant::where('barangay_id', $barangay->id)->exists()) {
        throw new RuntimeException('The database no longer matches the original Sheet1 import; review before reconciliation.');
    }
    foreach ($deceasedRows as $position => $row) {
        $matches = Inhabitant::where('barangay_id', $barangay->id)
            ->where('last_name', $row['B'])->where('first_name', $row['C'])
            ->where('middle_name', $row['D'])
            ->whereHas('household', fn ($query) => $query->where('household_number', $row['A']))
            ->where('remarks', 'like', '%[Source: INIGUIHAN.xlsx Sheet1 row '.$position.';%')
            ->lockForUpdate()->get();
        if ($matches->count() !== 1) throw new RuntimeException("Ambiguous resident at source row {$position}.");
        $resident = $matches->sole();
        if ($resident->resident_user_id || $resident->migrationRecords()->exists()
            || DB::table('barangay_rbi_members')->where('inhabitant_id', $resident->id)->exists()) {
            throw new RuntimeException('Resident has linked activity; manual reconciliation is required.');
        }
        $attributes = $resident->only([
            'barangay_id', 'family_number', 'individual_number', 'last_name', 'first_name',
            'middle_name', 'suffix', 'relationship_to_head', 'birth_place', 'birth_date',
            'recorded_age', 'sex', 'civil_status', 'education_level', 'religion', 'occupation',
        ]);
        DeceasedInhabitant::create($attributes + [
            'household_number' => $row['A'], 'purok' => $row['G'],
            'remarks' => $resident->remarks.' [Marked deceased in Sheet1; Sheet2 not imported]',
            'source_position' => $position, 'death_date' => null,
        ]);
        $resident->delete();
    }
    if (Inhabitant::where('barangay_id', $barangay->id)->count() !== $activeRows->count()) {
        throw new RuntimeException('Reconciled resident count does not match the workbook.');
    }
});

echo json_encode([
    'households' => Household::where('barangay_id', $barangay->id)->count(),
    'active_residents' => Inhabitant::where('barangay_id', $barangay->id)->count(),
    'deceased' => DeceasedInhabitant::where('barangay_id', $barangay->id)->count(),
    'sheet2_imported' => false, 'backup' => $backupPath,
], JSON_PRETTY_PRINT).PHP_EOL;
