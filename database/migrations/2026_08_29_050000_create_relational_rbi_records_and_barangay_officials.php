<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barangays', function (Blueprint $table) {
            $table->string('secretary_name')->nullable()->after('municipality');
            $table->string('punong_barangay_name')->nullable()->after('secretary_name');
            $table->string('logo_path')->nullable()->after('punong_barangay_name');
        });

        Schema::create('barangay_rbi_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_rbi_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('household_head');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('barangay_rbi_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_rbi_family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inhabitant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('inhabitant_name');
            $table->string('sex', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('civil_status', 100)->nullable();
            $table->string('occupation')->nullable();
            $table->string('relationship')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('barangay_rbi_deceased_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_rbi_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_rbi_family_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inhabitant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('deceased_name');
            $table->date('death_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $this->backfillExistingReports();
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_rbi_deceased_records');
        Schema::dropIfExists('barangay_rbi_members');
        Schema::dropIfExists('barangay_rbi_families');

        Schema::table('barangays', function (Blueprint $table) {
            $table->dropColumn(['secretary_name', 'punong_barangay_name', 'logo_path']);
        });
    }

    private function backfillExistingReports(): void
    {
        DB::table('barangay_rbi_updates')
            ->select(['id', 'rows', 'deceased_rows'])
            ->orderBy('id')
            ->eachById(function (object $report): void {
                $rows = $this->decodeRows($report->rows);
                $families = collect($rows)
                    ->groupBy(fn (array $row, int $index): string => trim((string) ($row['household_head'] ?? '')) ?: '__unnamed_'.$index)
                    ->values();
                $familyIds = [];

                foreach ($families as $familyPosition => $members) {
                    $head = trim((string) ($members->first()['household_head'] ?? '')) ?: 'Unnamed household';
                    $familyId = DB::table('barangay_rbi_families')->insertGetId([
                        'barangay_rbi_update_id' => $report->id,
                        'household_head' => $head,
                        'position' => $familyPosition,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $familyIds[mb_strtolower($head)] = $familyId;

                    foreach ($members->values() as $memberPosition => $member) {
                        DB::table('barangay_rbi_members')->insert([
                            'barangay_rbi_family_id' => $familyId,
                            'inhabitant_name' => trim((string) ($member['inhabitant_name'] ?? '')) ?: 'Unnamed inhabitant',
                            'sex' => $member['sex'] ?? null,
                            'birth_date' => ($member['birth_date'] ?? '') ?: null,
                            'birth_place' => $member['birth_place'] ?? null,
                            'civil_status' => $member['civil_status'] ?? null,
                            'occupation' => $member['occupation'] ?? null,
                            'relationship' => $member['relationship'] ?? null,
                            'position' => $memberPosition,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $deceasedRows = $this->decodeRows($report->deceased_rows);
                foreach ($deceasedRows as $position => $row) {
                    $head = mb_strtolower(trim((string) ($row['household_head'] ?? '')));
                    DB::table('barangay_rbi_deceased_records')->insert([
                        'barangay_rbi_update_id' => $report->id,
                        'barangay_rbi_family_id' => $familyIds[$head] ?? (count($familyIds) === 1 ? reset($familyIds) : null),
                        'deceased_name' => trim((string) ($row['deceased_name'] ?? '')) ?: 'Unnamed inhabitant',
                    'death_date' => ($row['death_date'] ?? '') ?: null,
                        'position' => $position,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function decodeRows(mixed $value): array
    {
        $rows = is_string($value) ? json_decode($value, true) : $value;

        return is_array($rows) ? $rows : [];
    }
};
