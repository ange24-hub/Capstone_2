<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrepareStatisticalForecastStorage extends Command
{
    protected $signature = 'migration:prepare-statistical-storage';
    protected $description = 'Create only the two additive WMA forecast tables';

    public function handle(): int
    {
        // Dili modagan og unrelated migrations ug dili mag-seed og demo data.
        foreach (['inhabitants', 'barangays', 'users', 'migration_records'] as $table) {
            if (! Schema::hasTable($table)) { $this->error('Install the base application schema first.'); return self::FAILURE; }
        }
        foreach (['migration_logs' => '2026_11_09_000001_create_migration_logs_table.php',
            'forecast_logs' => '2026_11_09_000002_create_forecast_logs_table.php'] as $table => $file) {
            if (! Schema::hasTable($table) && $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/'.$file]) !== 0) return self::FAILURE;
        }
        $this->info('Statistical forecast storage ready; existing resident records preserved.');
        return self::SUCCESS;
    }
}
