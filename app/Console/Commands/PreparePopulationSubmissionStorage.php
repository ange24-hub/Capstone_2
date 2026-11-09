<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PreparePopulationSubmissionStorage extends Command
{
    protected $signature = 'population:prepare-submission-storage';
    protected $description = 'Add immutable population summary submission history storage';

    public function handle(): int
    {
        if (Schema::hasTable('population_report_submissions')) return self::SUCCESS;
        return $this->call('migrate', ['--force' => true,
            '--path' => 'database/migrations/2026_11_09_000003_create_population_report_submissions_table.php']);
    }
}
