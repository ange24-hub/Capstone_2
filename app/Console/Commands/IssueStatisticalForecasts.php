<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\MigrationPredictionService;
use Illuminate\Console\Command;

class IssueStatisticalForecasts extends Command
{
    protected $signature = 'migration:issue-statistical-forecasts {--barangay= : Optional barangay ID}';
    protected $description = 'Save immutable first-issued next-month WMA/baseline forecasts';

    public function handle(MigrationPredictionService $service): int
    {
        $id = $this->option('barangay');
        if ($id !== null && (! ctype_digit((string) $id) || (int) $id < 1)) { $this->error('Invalid barangay ID.'); return self::FAILURE; }
        $areas = Barangay::when($id !== null, fn ($q) => $q->whereKey($id))->orderBy('id')->get();
        if ($areas->isEmpty()) return self::FAILURE;
        foreach ($areas as $area) foreach (['in', 'out'] as $type) $service->issueForecast($area->id, $type);
        $this->info('Saved forecasts for '.$areas->count().' barangays. Existing first issues were retained.');
        return self::SUCCESS;
    }
}
