<?php

namespace App\Console\Commands;

use App\Services\MigrationForecastReport;
use Illuminate\Console\Command;

class AuditMigrationForecast extends Command
{
    protected $signature = 'migration:forecast-audit {--barangay= : Limit to a barangay ID}';
    protected $description = 'Read aggregate arrival/departure coverage and net forecast evaluation without changing records';

    public function handle(MigrationForecastReport $report): int
    {
        $areaId = $this->option('barangay');
        if ($areaId !== null && ! ctype_digit((string) $areaId)) { $this->error('Use a numeric barangay ID.'); return self::FAILURE; }
        $forecast = $report->build($areaId ? (int) $areaId : null);
        $source = $forecast['forecastSource'];
        $result = $forecast['forecastEvaluation'];
        $this->line(json_encode(['scope' => $areaId ? 'Barangay ID '.$areaId : 'All configured barangays',
            'storage_ready' => $source['storage_ready'], 'excluded_demo_events' => $source['excluded_demo_events'],
            'source_through' => $forecast['sourceMonthLabel'], 'forecast_month' => $forecast['predictionMonthLabel'],
            'quarterly_refresh' => $forecast['forecastRefreshLabel'], 'next_refresh' => $forecast['forecastNextRefreshLabel'],
            'coverage' => $source['coverage'], 'evaluation' => $result,
            'arrivals' => ['storage_ready' => $forecast['forecastArrivalSource']['storage_ready'],
                'excluded_demo_events' => $forecast['forecastArrivalSource']['excluded_demo_events'],
                'coverage' => $forecast['forecastArrivalSource']['coverage'], 'evaluation' => $forecast['forecastArrivalEvaluation']],
            'net_evaluation' => $forecast['forecastNetEvaluation']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return self::SUCCESS;
    }
}
