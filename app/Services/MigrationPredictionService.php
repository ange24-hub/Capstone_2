<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MigrationPredictionService
{
    public function predictOutMigration(array $data): ?float
    {
        try {
            $response = Http::connectTimeout(1)->timeout(3)
                ->post(rtrim(config('services.migration_prediction.url'), '/').'/predict', [
                    'out_migration_count' => $data['out_migration_count'],
                    'previous_month_out' => $data['previous_month_out'],
                    'out_3month_avg' => $data['out_3month_avg'],
                    'year' => $data['year'],
                    'month' => $data['month'],
                ]);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $prediction = $response->json('predicted_next_month_out_migration');

        return is_numeric($prediction) && is_finite((float) $prediction)
            ? max(0, round((float) $prediction, 2))
            : null;
    }
}
