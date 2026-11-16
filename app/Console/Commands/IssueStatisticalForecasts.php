<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IssueStatisticalForecasts extends Command
{
    protected $signature = 'migration:issue-statistical-forecasts {--barangay= : Optional barangay ID}';
    protected $description = 'Retired: migration forecasts are no longer issued';

    public function handle(): int
    {
        $this->error('Migration forecasting has been retired. Use the population forecast page for population estimates and historical MAE/RMSE.');

        return self::FAILURE;
    }
}
