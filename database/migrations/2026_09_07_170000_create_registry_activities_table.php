<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('registry_activities', function (Blueprint $table) {
        $table->id(); $table->unsignedBigInteger('barangay_id')->index(); $table->unsignedBigInteger('user_id')->nullable();
        $table->unsignedBigInteger('inhabitant_id')->nullable(); $table->string('description'); $table->json('changes')->nullable(); $table->timestamps();
    }); }
    public function down(): void { Schema::dropIfExists('registry_activities'); }
};
