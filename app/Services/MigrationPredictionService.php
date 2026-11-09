<?php

namespace App\Services;

use App\Models\{Barangay, ForecastLog, Inhabitant, MigrationLog, MigrationRecord};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Pure PHP statistics: walay model training, Python, HTTP request o API key. */
class MigrationPredictionService
{
    public function calendar(): Carbon
    {
        return now(config('migration_forecast.timezone', 'Asia/Manila'));
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, ['in', 'out'], true)) throw new \InvalidArgumentException('Migration type must be in or out.');
    }

    private function monthDate(string $month): Carbon
    {
        if (! preg_match('/^(19|20|21)\d{2}-(0[1-9]|1[0-2])$/D', $month)) {
            throw ValidationException::withMessages(['log_month' => 'Gamita ang YYYY-MM, pananglitan 2026-10.']);
        }
        return Carbon::createFromFormat('!Y-m', $month, config('migration_forecast.timezone'));
    }

    public function monthSnapshot(int $barangayId, string $type, string $month): array
    {
        $this->validateType($type);
        $start = $this->monthDate($month);
        $rows = MigrationLog::actualRecords()->where('barangay_id', $barangayId)->where('migration_type', $type)
            ->whereBetween('transaction_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderBy('id')->get(['id', 'resident_id', 'source_record_id', 'transaction_date', 'updated_at']);
        $sourceCount = MigrationRecord::withoutDemo()->where('barangay_id', $barangayId)->where('type', $type)
            ->whereHas('inhabitant', fn ($q) => $q->where('barangay_id', $barangayId))
            ->whereBetween('movement_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])->count();
        $unsynced = $sourceCount !== $rows->whereNotNull('source_record_id')->count();
        $duplicates = $rows->groupBy(fn ($r) => $r->resident_id.'|'.$r->transaction_date->format('Y-m-d'))
            ->sum(fn ($group) => max(0, $group->count() - 1));
        $hash = hash('sha256', json_encode($rows->map(fn ($r) => [$r->id, $r->resident_id,
            $r->transaction_date->format('Y-m-d'), (string) $r->getRawOriginal('updated_at')])->all(), JSON_THROW_ON_ERROR));
        $log = ForecastLog::where(['barangay_id' => $barangayId, 'migration_type' => $type, 'log_month' => $month])->first();
        $verified = ! $unsynced && $start->lt($this->calendar()->startOfMonth()) && $duplicates === 0 && $log?->actual_verified_at
            && $log->actual_value === $rows->count() && hash_equals((string) $log->actual_source_hash, $hash);

        return ['month' => $month, 'count' => $rows->count(), 'source_hash' => $hash,
            'duplicates' => $duplicates, 'unsynced' => $unsynced, 'verified' => (bool) $verified];
    }

    public function history(int $barangayId, string $type): array
    {
        $window = max(3, min(6, (int) config('migration_forecast.window_months', 6)));
        $end = $this->calendar()->startOfMonth()->subMonth();
        $rows = [];
        for ($offset = $window - 1; $offset >= 0; $offset--) {
            $rows[] = $this->monthSnapshot($barangayId, $type, $end->copy()->subMonths($offset)->format('Y-m'));
        }
        return $rows;
    }

    public function forecastNextMonth(int $barangayId, string $type): array
    {
        $this->validateType($type);
        Barangay::findOrFail($barangayId);
        $history = $this->history($barangayId, $type);
        // Hunong sa unang gap: unknown month dili mahimong zero o laktawan.
        $usable = [];
        foreach (array_reverse($history) as $row) {
            if (! $row['verified']) break;
            array_unshift($usable, $row);
        }
        $population = Inhabitant::withoutMigrationDemo()->where('barangay_id', $barangayId)
            ->where('status', Inhabitant::STATUS_ACTIVE)->count();
        $rate = (float) config('migration_forecast.baseline_rate', 0.001);
        if (! is_finite($rate) || $rate < 0 || $rate > 1) throw new \LogicException('Baseline rate must be between 0 and 1.');
        $method = count($usable) >= 3 ? 'weighted_moving_average' : 'population_baseline';
        $weights = range(1, max(1, count($usable)));
        $prediction = $method === 'weighted_moving_average'
            ? array_sum(array_map(fn ($row, $weight) => $row['count'] * $weight, $usable, $weights)) / array_sum($weights)
            : $population * ((1 + $rate) ** 1 - 1);
        // 0.1% sa matag direction usa ka assumption; dili ni net population growth measurement.
        return ['barangay_id' => $barangayId, 'migration_type' => $type,
            'log_month' => $this->calendar()->startOfMonth()->addMonth()->format('Y-m'),
            'predicted_value' => round($prediction, 4), 'method' => $method,
            'population' => $population, 'baseline_rate' => $rate, 'history' => $usable,
            'weights' => $method === 'weighted_moving_average' ? $weights : [],
            'data_through' => $this->calendar()->startOfMonth()->subMonth()->format('Y-m'),
            'protocol' => 'wma_closed_months_v1'];
    }

    public function issueForecast(int $barangayId, string $type): ForecastLog
    {
        // First issue ra ang i-save; dili ilisan human makita ang resulta sa target month.
        return DB::transaction(function () use ($barangayId, $type) {
            Barangay::whereKey($barangayId)->lockForUpdate()->firstOrFail();
            $forecast = $this->forecastNextMonth($barangayId, $type);
            $log = ForecastLog::firstOrNew(['barangay_id' => $barangayId, 'migration_type' => $type, 'log_month' => $forecast['log_month']]);
            if ($log->predicted_value !== null) return $log;
            $log->fill(['predicted_value' => $forecast['predicted_value'], 'method' => $forecast['method'],
                'issued_at' => now(), 'history_snapshot' => $forecast])->save();
            return $log;
        });
    }

    public function confirmActualMonth(int $barangayId, string $type, string $month, int $userId, string $expectedHash): ForecastLog
    {
        $this->validateType($type);
        if ($this->monthDate($month)->gte($this->calendar()->startOfMonth())) {
            throw ValidationException::withMessages(['log_month' => 'Completed months ra ang mahimong i-confirm.']);
        }
        return DB::transaction(function () use ($barangayId, $type, $month, $userId, $expectedHash) {
            Barangay::whereKey($barangayId)->lockForUpdate()->firstOrFail();
            $source = $this->monthSnapshot($barangayId, $type, $month);
            if (! hash_equals($source['source_hash'], $expectedHash) || $source['duplicates'] || $source['unsynced']) {
                throw ValidationException::withMessages(['log_month' => 'Naay source change, wala pa ma-sync, o possible duplicate. I-sync ug i-review una ang movement logs.']);
            }
            $log = ForecastLog::firstOrNew(['barangay_id' => $barangayId, 'migration_type' => $type, 'log_month' => $month]);
            $actual = $source['count'];
            $eligible = $log->issued_at && $log->issued_at->lt($this->monthDate($month)) && $log->predicted_value !== null;
            $log->fill(['actual_value' => $actual, 'actual_source_hash' => $source['source_hash'],
                'actual_verified_at' => now(), 'verified_by' => $userId,
                'accuracy_rate' => $eligible && $actual > 0 ? round((1 - abs($actual - $log->predicted_value) / $actual) * 100, 4) : null])->save();
            return $log;
        });
    }

    public function calculateForecastAccuracy(int $barangayId, string $type): array
    {
        $this->validateType($type);
        $errors = []; $percentageErrors = []; $zeroActual = 0; $unverified = 0;
        $logs = ForecastLog::where('barangay_id', $barangayId)->where('migration_type', $type)
            ->whereNotNull('predicted_value')->whereNotNull('issued_at')
            ->where('log_month', '<', $this->calendar()->format('Y-m'))->orderBy('log_month')->get();
        foreach ($logs as $log) {
            if (! $log->issued_at->lt($this->monthDate($log->log_month))) continue;
            $source = $this->monthSnapshot($barangayId, $type, $log->log_month);
            if (! $source['verified']) { $unverified++; continue; }
            $error = abs($source['count'] - $log->predicted_value);
            $errors[] = $error;
            if ($source['count'] === 0) { $zeroActual++; continue; }
            $percentageErrors[] = $error / $source['count'] * 100;
        }
        $mape = $percentageErrors ? array_sum($percentageErrors) / count($percentageErrors) : null;
        return ['mape' => $mape === null ? null : round($mape, 4),
            'accuracy_rate' => $mape === null ? null : round(100 - $mape, 4),
            'evaluated_months' => count($errors), 'mape_months' => count($percentageErrors),
            'zero_actual_months' => $zeroActual, 'unverified_months' => $unverified,
            'mae' => $errors ? array_sum($errors) / count($errors) : null,
            'rmse' => $errors ? sqrt(array_sum(array_map(fn ($e) => $e ** 2, $errors)) / count($errors)) : null];
    }

    public function generateHumanReadableSummary(float $predictedIn, float $predictedOut, ?float $accuracyIn, ?float $accuracyOut): string
    {
        // Rule-based Cebuano text, dili AI/ML. Walay gi-infer nga hinungdan sa migration.
        $net = $predictedIn - $predictedOut;
        $text = 'Sa sunod nga bulan, ang estimate kay '.number_format($predictedIn, 2).' ka in-migration ug '
            .number_format($predictedOut, 2).' ka out-migration events. ';
        $text .= abs($net) < 0.005 ? 'Halos parehas ang duha ka estimates. '
            : ($net > 0 ? 'Mas taas ang estimated pagsulod kaysa paggawas. ' : 'Mas taas ang estimated paggawas kaysa pagsulod. ');
        foreach (['In' => $accuracyIn, 'Out' => $accuracyOut] as $label => $score) {
            $text .= $score === null ? "{$label}: wala pay eligible actual results para sa MAPE score. "
                : "{$label}: ".number_format($score, 2).'% ang 100−MAPE score. ';
        }
        return $text.'Estimate ra kini, dili garantiya. Ang score dili probability; ang cold-start baseline usa ka assumption.';
    }

    /** Daang ML adapter gi-retire: walay external prediction request. */
    public function predictOutMigration(array $data): ?float { return null; }
}
