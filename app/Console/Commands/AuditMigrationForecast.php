<?php

namespace App\Console\Commands;

use App\Services\MigrationForecastEngine;
use App\Services\MigrationForecastHistory;
use Illuminate\Console\Command;

class AuditMigrationForecast extends Command
{
    protected $signature = 'migration:forecast-audit {--barangay= : Limit to a barangay ID}';
    protected $description = 'Read aggregate departure history, verified coverage and forecast evaluation without changing records';

    public function handle(MigrationForecastHistory $history, MigrationForecastEngine $engine): int
    {
        $areaId = $this->option('barangay');
        if ($areaId !== null && ! ctype_digit((string) $areaId)) { $this->error('Use a numeric barangay ID.'); return self::FAILURE; }
        $source = $history->build($areaId ? (int) $areaId : null, now()->startOfMonth()->subMonth());
        $result = $engine->evaluate($source['consecutive'], 2);
        $this->line(json_encode(['scope' => $areaId ? 'Barangay ID '.$areaId : 'All configured barangays',
            'storage_ready' => $source['storage_ready'], 'excluded_demo_events' => $source['excluded_demo_events'],
            'coverage' => $source['coverage'], 'evaluation' => $result], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
