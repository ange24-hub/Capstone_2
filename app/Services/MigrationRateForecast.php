<?php

namespace App\Services;

/** Pure count-rate fit; no report access, target actuals or parameter search. */
class MigrationRateForecast
{
    public function fit(array $counts, string $method): array
    {
        if (! in_array($method, ['mean_rate', 'poisson_jeffreys'], true) || ! $counts
            || array_filter($counts, fn ($count) => ! is_int($count) || $count < 0)) {
            throw new \InvalidArgumentException('Use a supported rate method and nonnegative integer training counts.');
        }
        $total = array_sum($counts);
        $bayesian = $method === 'poisson_jeffreys';

        return ['method_key' => $method, 'method' => $bayesian ? 'Bayesian Poisson (Jeffreys prior)' : 'Mean-rate baseline',
            'prediction' => ($total + ($bayesian ? 0.5 : 0)) / count($counts),
            'training_total' => $total, 'training_months' => count($counts),
            'prior' => $bayesian ? ['name' => 'Jeffreys', 'shape' => 0.5, 'rate' => 0, 'tuned' => false] : null,
            'posterior' => $bayesian ? ['shape' => $total + 0.5, 'rate' => count($counts)] : null];
    }
}
