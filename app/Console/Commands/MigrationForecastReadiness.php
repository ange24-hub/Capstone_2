<?php

namespace App\Console\Commands;

use App\Services\MigrationForecastEvidence;
use Illuminate\Console\Command;

class MigrationForecastReadiness extends Command
{
    protected $signature = 'migration:forecast-readiness {--all-barangays : Include every barangay separately} {--save : Save aggregate JSON under output/prediction-analysis}';
    protected $description = 'Read forecast readiness, validation choices and MAE/RMSE without modifying source records';

    public function handle(MigrationForecastEvidence $evidence): int
    {
        $result = $evidence->build((bool) $this->option('all-barangays'));
        $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($this->option('save')) {
            $directory = base_path('output/prediction-analysis/future-forecast-readiness-'.now()->format('Ymd-His'));
            if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
                throw new \RuntimeException('Unable to create aggregate report directory.');
            }
            file_put_contents($directory.'/readiness.json', $json.PHP_EOL);
            $this->info('Aggregate report: '.$directory.'/readiness.json');
            foreach ($result['scopes'] as $scope) {
                $this->line($scope['name'].': in '.$scope['in']['consecutive_verified_months'].' verified months; out '
                    .$scope['out']['consecutive_verified_months'].' verified months; forecasts '
                    .($scope['in']['evaluation']['ready'] ? 'in ready' : 'in not ready').' / '
                    .($scope['out']['evaluation']['ready'] ? 'out ready' : 'out not ready'));
            }
        } else {
            $this->line($json);
        }
        return self::SUCCESS;
    }
}
