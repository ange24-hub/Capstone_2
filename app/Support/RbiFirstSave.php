<?php

namespace App\Support;

use App\Models\BarangayRbiUpdate;
use App\Services\RbiDuplicateEntries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RbiFirstSave
{
    public function stamp(array $rows, ?BarangayRbiUpdate $report, Carbon $savedAt): array
    {
        $previous = $report?->rows ?? [];
        $byId = [];
        $byPerson = [];
        foreach ($previous as $index => $row) {
            if (filled($row['source_row_id'] ?? null)) {
                $byId[$row['source_row_id']] = $index;
            }
            foreach ($this->personKeys($row) as $key) {
                $byPerson[$key] ??= $index;
            }
        }

        $used = [];
        foreach ($rows as &$row) {
            $index = $byId[$row['source_row_id'] ?? ''] ?? null;
            if ($index === null || isset($used[$index])) {
                $index = null;
                foreach ($this->personKeys($row) as $key) {
                    $candidate = $byPerson[$key] ?? null;
                    if ($candidate !== null && ! isset($used[$candidate])) {
                        $index = $candidate;
                        break;
                    }
                }
            }
            if ($index !== null) {
                $used[$index] = true;
            }
            $old = $index === null ? null : $previous[$index];
            $row['source_row_id'] = $old['source_row_id'] ?? (string) Str::uuid();
            $row['first_saved_at'] = $old
                ? (($old['first_saved_at'] ?? '') ?: ($report?->created_at?->toIso8601String() ?? $savedAt->toIso8601String()))
                : $savedAt->toIso8601String();
        }
        unset($row);

        return $rows;
    }

    private function personKeys(array $row): array
    {
        $keys = [];
        foreach (['history_record_id', 'inhabitant_id'] as $field) {
            if (filled($row[$field] ?? null)) {
                $keys[] = $field.':'.$row[$field];
            }
        }
        return array_merge($keys, app(RbiDuplicateEntries::class)->keys($row, 'rows'));
    }
}
