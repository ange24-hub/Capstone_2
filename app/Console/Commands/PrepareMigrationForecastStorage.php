<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrepareMigrationForecastStorage extends Command
{
    protected $signature = 'migration:prepare-forecast-storage';
    protected $description = 'Install only additive forecast reporting storage and arrival verification on an existing database';

    public function handle(): int
    {
        if (! Schema::hasTable('barangays') || ! Schema::hasTable('users')) {
            $this->info('Complete the normal initial database installation before enabling forecasts.');
            return self::SUCCESS;
        }
        if (! Schema::hasTable('migration_reporting_periods')) {
            $result = $this->call('migrate', ['--force' => true,
                '--path' => 'database/migrations/2026_11_05_000000_create_migration_reporting_periods_table.php']);
            if ($result !== self::SUCCESS) return $result;
        }
        if (Schema::hasColumns('migration_reporting_periods', ['in_count', 'in_source_fingerprint', 'in_verified_by', 'in_verified_at', 'in_notes'])) return self::SUCCESS;
        return $this->call('migrate', ['--force' => true,
            '--path' => 'database/migrations/2026_11_05_010000_add_arrival_verification_to_migration_reporting_periods.php']);
    }
}
