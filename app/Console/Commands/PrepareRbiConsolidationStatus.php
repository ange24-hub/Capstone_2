<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrepareRbiConsolidationStatus extends Command
{
    protected $signature = 'rbi:prepare-consolidation-status';

    protected $description = 'Add the submitted and added state to Monthly RBI reports';

    public function handle(): int
    {
        if (! Schema::hasTable('barangay_rbi_updates') || Schema::hasColumn('barangay_rbi_updates', 'consolidated_at')) {
            return self::SUCCESS;
        }

        return $this->call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_11_13_000002_add_consolidated_at_to_barangay_rbi_updates.php',
        ]);
    }
}
