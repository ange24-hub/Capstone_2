<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MigrationPredictionService
{
    public function predictOutMigration(array $data): ?float
    {
        $response = Http::timeout(10)
            ->post('http://127.0.0.1:5001/predict', [
                'out_migration_count' => $data['out_migration_count'],
                'previous_month_out' => $data['previous_month_out'],
                'out_3month_avg' => $data['out_3month_avg'],
                'year' => $data['year'],
                'month' => $data['month'],
            ]);

        if (!$response->successful()) {
            return null;
        }

        return $response->json('predicted_next_month_out_migration');
    }
}