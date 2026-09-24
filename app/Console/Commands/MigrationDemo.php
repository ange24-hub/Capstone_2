<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\Inhabitant;
use App\Models\MigrationRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrationDemo extends Command
{
    protected $signature = 'migration:demo {--remove : Remove only tagged demo data} {--barangay= : Limit to a barangay ID}';

    protected $description = 'Create removable synthetic migration history for testing, or remove it before deployment';

    public const MARKER = 'RBIM migration demo v1';

    public function handle(): int
    {
        if ($this->option('barangay') !== null && (! ctype_digit((string) $this->option('barangay')) || (int) $this->option('barangay') < 1)) {
            $this->error('Barangay must be a positive integer ID.');

            return self::FAILURE;
        }
        if (! $this->option('remove') && app()->environment('production')) {
            $this->error('Demo creation is disabled in production. Removal is still available.');

            return self::FAILURE;
        }

        $barangays = Barangay::query()
            ->when($this->option('barangay'), fn ($q, $id) => $q->whereKey($id))->get();
        if ($barangays->isEmpty()) {
            $this->error('No matching barangays found.');

            return self::FAILURE;
        }

        $created = 0;
        $removed = 0;
        DB::transaction(function () use ($barangays, &$created, &$removed) {
            foreach ($barangays as $barangay) {
                $household = Household::where('barangay_id', $barangay->id)
                    ->where('household_number', 'DEMO-MIGRATION-V1')->first();

                if ($this->option('remove')) {
                    $removed += MigrationRecord::where('barangay_id', $barangay->id)
                        ->where('reason', self::MARKER)->delete();
                    if ($household && $household->address === self::MARKER) {
                        foreach (Inhabitant::where('household_id', $household->id)->where('remarks', self::MARKER)->get() as $person) {
                            // Preserve demo profiles if staff have subsequently linked real data.
                            if ($person->resident_user_id || $person->migrationRecords()->exists()
                                || DB::table('document_requests')->where('inhabitant_id', $person->id)->exists()
                                || DB::table('barangay_rbi_members')->where('inhabitant_id', $person->id)->exists()
                                || DB::table('barangay_rbi_deceased_records')->where('inhabitant_id', $person->id)->exists()
                                || DB::table('new_inhabitants')->where('active_inhabitant_id', $person->id)->exists()) {
                                $this->warn("Kept linked demo profile {$person->id}; review manually.");

                                continue;
                            }
                            $person->delete();
                        }
                        if (! Inhabitant::where('household_id', $household->id)->exists()
                            && ! DB::table('barangay_rbi_families')->where('household_id', $household->id)->exists()) {
                            $household->delete();
                        }
                    }

                    continue;
                }

                // Re-running the command does not duplicate an existing demo batch.
                if ($household) {
                    $this->line("Skipped {$barangay->name}: demo household already exists.");

                    continue;
                }
                $household = Household::create([
                    'barangay_id' => $barangay->id,
                    'household_number' => 'DEMO-MIGRATION-V1',
                    'address' => self::MARKER,
                ]);
                $sequence = 0;
                for ($offset = 12; $offset >= 0; $offset--) {
                    $month = now()->startOfMonth()->subMonths($offset);
                    foreach ([MigrationRecord::TYPE_IN, MigrationRecord::TYPE_OUT] as $type) {
                        $count = $type === MigrationRecord::TYPE_IN
                            ? 3 + (($month->month + $barangay->id) % 6)
                            : 2 + (($month->month * 2 + $barangay->id) % 5);
                        for ($i = 0; $i < $count; $i++) {
                            $person = Inhabitant::create([
                                'barangay_id' => $barangay->id, 'household_id' => $household->id,
                                'first_name' => 'Demo '.(++$sequence), 'last_name' => 'Migration Sample',
                                'sex' => $i % 2 ? 'Male' : 'Female', 'remarks' => self::MARKER,
                                'status' => $type === MigrationRecord::TYPE_IN ? Inhabitant::STATUS_ACTIVE : Inhabitant::STATUS_MIGRATED_OUT,
                            ]);
                            MigrationRecord::create([
                                'barangay_id' => $barangay->id, 'inhabitant_id' => $person->id,
                                'type' => $type, 'movement_date' => $month->toDateString(),
                                'origin' => 'DEMO origin', 'destination' => 'DEMO destination',
                                'reason' => self::MARKER,
                            ]);
                            $created++;
                        }
                    }
                }
            }
        });

        $this->info($this->option('remove') ? "Removed {$removed} demo migration events." : "Created {$created} demo migration events. Remove before deployment: php artisan migration:demo --remove");

        return self::SUCCESS;
    }
}
