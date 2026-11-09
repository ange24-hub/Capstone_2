<?php

namespace App\Console\Commands;

use App\Services\DeploymentBrbiSync;
use Illuminate\Console\Command;

class SyncBrbiRelease extends Command
{
    protected $signature = 'brbi:sync-release';
    protected $description = 'Apply the recipient-encrypted, append-only BRBI release once, preserving and backing up existing records';

    public function handle(DeploymentBrbiSync $sync): int
    {
        $sync->run();
        return self::SUCCESS;
    }
}
