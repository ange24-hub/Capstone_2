<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrepareMigrationForecastStorage extends Command
{
    protected $signature = 'migration:prepare-forecast-storage';
    protected $description = 'Install only the additive forecast reporting-period table on an existing database';

    public function handle(): int
    {
        if (! Schema::hasTable('barangays') || ! Schema::hasTable('users')) {
            $this->info('Complete the normal initial database installation before enabling forecasts.');
            return self::SUCCESS;
        }
        if (Schema::hasTable('migration_reporting_periods')) return self::SUCCESS;
        return $this->call('migrate', ['--force' => true,
            '--path' => 'database/migrations/2026_11_05_000000_create_migration_reporting_periods_table.php']);
    }
}
