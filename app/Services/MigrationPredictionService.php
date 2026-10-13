<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MigrationPredictionService
{
    public function predictOutMigration(array $data): ?float
    {
        if (! config('services.migration_prediction.enabled', true)) {
            return null;
        }

        $url = rtrim((string) config('services.migration_prediction.url'), '/');
        if (! preg_match('~^http://127\.0\.0\.1:[0-9]{1,5}$~D', $url)) {
            return null;
        }

        try {
            $response = Http::connectTimeout(1)->timeout(3)
                ->withOptions(['allow_redirects' => false, 'proxy' => ''])
                ->post($url.'/predict', [
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
