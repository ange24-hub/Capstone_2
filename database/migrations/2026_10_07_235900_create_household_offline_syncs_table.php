<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_offline_syncs', function (Blueprint $table) {
            $table->id();
            $table->string('offline_uuid', 100)->unique();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_offline_syncs');
    }
};
