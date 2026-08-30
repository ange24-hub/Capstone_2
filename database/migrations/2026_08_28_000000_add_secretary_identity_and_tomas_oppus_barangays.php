<?php

use App\Models\Barangay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 100)->nullable()->unique()->after('email');
        });

        $now = now();

        foreach (Barangay::TOMAS_OPPUS_BARANGAYS as $name) {
            DB::table('barangays')->insertOrIgnore([
                'name' => $name,
                'municipality' => Barangay::MUNICIPALITY,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('barangays')
                ->where('name', $name)
                ->update([
                    'municipality' => Barangay::MUNICIPALITY,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staff_id']);
            $table->dropColumn('staff_id');
        });
    }
};
