<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['inhabitants', 'new_inhabitants', 'deceased_inhabitants'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('recorded_age', 30)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Refuse a rollback that would discard an infant's age unit.
        foreach (self::TABLES as $name) {
            foreach (DB::table($name)->whereNotNull('recorded_age')->select('recorded_age')->cursor() as $record) {
                if (! ctype_digit((string) $record->recorded_age) || (int) $record->recorded_age > 65535) {
                    throw new RuntimeException('Convert recorded ages to whole years before rolling back age unit support.');
                }
            }
        }

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedSmallInteger('recorded_age')->nullable()->change();
            });
        }
    }
};
