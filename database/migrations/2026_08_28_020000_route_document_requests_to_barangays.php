<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('barangay_id')
                ->nullable()
                ->after('resident_id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('reviewed_by');
            $table->index(['barangay_id', 'status']);
        });

        DB::table('document_requests')
            ->orderBy('id')
            ->get(['id', 'resident_id'])
            ->each(function ($request): void {
                $barangayId = DB::table('users')
                    ->where('id', $request->resident_id)
                    ->value('barangay_id');

                if ($barangayId) {
                    DB::table('document_requests')
                        ->where('id', $request->id)
                        ->update(['barangay_id' => $barangayId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropIndex(['barangay_id', 'status']);
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn('processed_at');
        });
    }
};
