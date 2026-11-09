<?php

namespace App\Services;

use App\Models\PopulationReportSubmission;
use Illuminate\Support\Carbon;

class PopulationReportSubmissionSnapshot
{
    public function capture(array $report): array
    {
        // Aggregate summary ra, walay resident-name lists o live model objects.
        return json_decode(json_encode($report, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) ksort($value);
        foreach ($value as &$item) if (is_array($item)) $item = $this->canonical($item);
        unset($item);
        return $value;
    }

    public function hash(array $snapshot, bool $contentOnly = false): string
    {
        if ($contentOnly) unset($snapshot['generatedAt']);
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_THROW_ON_ERROR));
    }

    public function restore(PopulationReportSubmission $submission): array
    {
        $report = $submission->snapshot;
        abort_unless(hash_equals($submission->snapshot_hash, $this->hash($report)), 409, 'Saved report integrity check failed.');
        $report['generatedAt'] = Carbon::parse($report['generatedAt'])->timezone(config('app.timezone'));
        $report['detailGroups'] = collect();
        $report['coverage'] = collect($report['coverage'])->map(function ($area) {
            if ($area['lastUpdated'] ?? null) $area['lastUpdated'] = Carbon::parse($area['lastUpdated']);
            return $area;
        });
        return $report;
    }
}
