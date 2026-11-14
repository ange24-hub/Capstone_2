<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PrepareRbiCertificationStorage extends Command
{
    protected $signature = 'rbi:prepare-certification-storage';

    protected $description = 'Add storage for the Monthly RBI preparer designation';

    public function handle(): int
    {
        if (! Schema::hasTable('barangay_rbi_updates')) {
            return self::SUCCESS;
        }

        return $this->call('migrate', [
            '--force' => true,
            '--path' => 'database/migrations/2026_11_13_000001_add_prepared_role_to_barangay_rbi_updates.php',
        ]);
    }
}
