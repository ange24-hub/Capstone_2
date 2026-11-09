<?php

namespace Tests\Feature;

use App\Models\{Barangay, Household, Inhabitant};
use App\Services\BrbiAgeStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;

class BrbiAgeStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_age_unit_migration_preserves_existing_residents_and_allows_month_labels(): void
    {
        Storage::fake('local');
        $area=Barangay::firstOrFail();
        $home=Household::create(['barangay_id'=>$area->id,'household_number'=>'AGE-FIXTURE']);
        $person=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Age',
            'last_name'=>'Fixture','sex'=>'Female','recorded_age'=>'25','remarks'=>'Keep existing']);
        $migration=require database_path('migrations/2026_11_05_020000_allow_age_units_in_registry_records.php');
        $migration->down();
        DB::table('migrations')->where('migration','2026_11_05_020000_allow_age_units_in_registry_records')->delete();
        $before=$person->fresh()->getAttributes();
        app(BrbiAgeStorage::class)->prepare(Storage::disk('local')->path('age-schema-backup'));
        $after=$person->fresh()->getAttributes();
        $before['recorded_age']=(string)$before['recorded_age'];$after['recorded_age']=(string)$after['recorded_age'];
        $this->assertSame($before,$after);
        $this->assertNotContains(Schema::getColumnType('inhabitants','recorded_age'),['smallint','integer','int']);
        Storage::disk('local')->assertExists('age-schema-backup/inhabitants.jsonl');
        Storage::disk('local')->assertExists('age-schema-backup/verified.json');
        $new=Inhabitant::create(['barangay_id'=>$area->id,'household_id'=>$home->id,'first_name'=>'Infant',
            'last_name'=>'Fixture','sex'=>'Female','recorded_age'=>'9 months']);
        $this->assertSame('9 months',$new->fresh()->recorded_age);
        $this->assertDatabaseCount('inhabitants',2);
    }
}
