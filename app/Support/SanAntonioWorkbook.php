<?php

namespace App\Support;

use App\Models\Inhabitant;
use RuntimeException;

class SanAntonioWorkbook
{
    public static function transferred(array $row): bool
    {
        $remark = $row['Q'] ?? '';
        return ! preg_match('/\b(?:NOT|NO|NEVER)\s+TRANSFER/i', $remark)
            && (bool) preg_match('/\b(?:TRANSFERRED|TRANSFERED)\b/i', $remark);
    }

    public static function residence(array $row): array
    {
        if (! empty($row['_out'])) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'OUT sheet; living elsewhere as instructed by user'];
        }
        if (self::transferred($row)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Explicit transfer in source'];
        return SourceResidenceSync::classify([$row['_fills']['C'] ?? '', $row['_fills']['D'] ?? ''],
            ($row['P'] ?? '').' '.($row['Q'] ?? ''));
    }

    private static function identity(array $row, array $columns): string
    {
        return implode('|', array_map(fn ($column) => mb_strtoupper(preg_replace('/\s+/', ' ',
            trim($row[$column] ?? '', " \t\r\n-"))), $columns));
    }

    // OUT has the same fields as the consolidated sheet, shifted one column left.
    private static function fromOut(array $row): array
    {
        $result = ['A' => '', '_out' => true];
        foreach (range('A', 'P') as $column) $result[chr(ord($column) + 1)] = $row[$column] ?? '';
        return $result;
    }

    private static function merge(array $first, array $second, string $remarkColumn): array
    {
        foreach ($second as $column => $value) {
            if ($column === '_source' || $column === $remarkColumn) continue;
            if (($first[$column] ?? '') === '') $first[$column] = $value;
        }
        $first[$remarkColumn] = trim(($first[$remarkColumn] ?? '').' '.($second[$remarkColumn] ?? '').' [Also '.$second['_source'].']');
        if (! empty($second['_out'])) $first['_out'] = true;
        return $first;
    }

    public static function plan(array $sheets): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'SAN ANTONIO') {
            throw new RuntimeException('Expected the San Antonio consolidated workbook.');
        }
        foreach (['DECEASED', 'NEW', 'OUT'] as $sheet) {
            if (! isset($sheets[$sheet])) throw new RuntimeException('Missing '.$sheet.' worksheet.');
        }
        $households = $active = $moved = $deceased = $pending = $new = $deadKeys = $people = $personKeys = [];
        foreach ($sheets['DECEASED'] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['_source'] = 'DECEASED row '.$position;
            if (($row['D'] ?? '') === '-') $row['D'] = '';
            if (stripos($row['E'] ?? '', 'no record') !== false) {
                $row['P'] = trim(($row['P'] ?? '').' [Source: '.$row['E'].']');
                $row['E'] = '';
            }
            // Two recent entries put their death dates in the remarks column.
            if (empty($row['Q']) && preg_match('/^[A-Z]+\s+\d{1,2},\s*\d{4}$/i', $row['P'] ?? '')) {
                $row['Q'] = $row['P'];
                $row['P'] = '';
            }
            $row['A'] = trim($row['A'] ?? '', ' -') ?: 'Not recorded';
            $key = self::identity($row, ['B', 'C', 'D', 'E', 'I']);
            if (isset($deadKeys[$key])) {
                $keep = $deadKeys[$key];
                $deceased[$keep] = self::merge($deceased[$keep], $row, 'P');
                $pending['DECEASED '.$position] = ['row' => self::fromOut($row), 'reason' => 'Duplicate deceased entry merged with row '.$keep];
            } else {
                $deadKeys[$key] = $position;
                $deceased[$position] = $row;
            }
        }
        $rows = [];
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 11 || (empty($row['C']) && empty($row['D']))) continue;
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            $rows[$position] = $row;
        }
        foreach ($sheets['OUT'] as $position => $row) {
            if ($position < 2 || (empty($row['B']) && empty($row['C']))) continue;
            $row = self::fromOut($row);
            $row['_source'] = 'OUT row '.$position;
            $rows[20000 + $position] = $row;
        }
        foreach ($rows as $position => $row) {
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['row' => $row, 'reason' => 'Incomplete name or vacant property; not imported as a resident'];
                continue;
            }
            $row['B'] = trim($row['B'] ?? '', ' -');
            $key = self::identity($row, ['C', 'D', 'E', 'F', 'J']);
            if (isset($deadKeys[$key]) || preg_match('/\b(?:DECEASED|DESCEASED|DESEACED)\b/i', $row['Q'] ?? '')) {
                $dead = [];
                foreach (range('A', 'P') as $column) $dead[$column] = $row[chr(ord($column) + 1)] ?? '';
                $dead['_source'] = $row['_source'];
                $dead['A'] = $dead['A'] ?: 'Not recorded';
                if (isset($deadKeys[$key])) {
                    $keep = $deadKeys[$key];
                    $deceased[$keep] = self::merge($deceased[$keep], $dead, 'P');
                } else {
                    $deadKeys[$key] = 10000 + $position;
                    $deceased[10000 + $position] = $dead;
                }
                continue;
            }
            if (isset($personKeys[$key])) {
                $keep = $personKeys[$key];
                $before = $people[$keep];
                $people[$keep] = self::merge($before, $row, 'Q');
                $reason = 'Duplicate identity merged with '.$before['_source'];
                if (($before['B'] ?? '') !== $row['B']) {
                    $people[$keep]['B'] = '';
                    $reason .= '; conflicting or missing household assignment needs review';
                    $people[$keep]['Q'] .= ' [Source households: '.($before['B'] ?: 'not recorded').' / '.($row['B'] ?: 'not recorded').'; household needs confirmation]';
                }
                $pending[$position] = ['row' => $row, 'reason' => $reason];
            } else {
                $personKeys[$key] = $position;
                $people[$position] = $row;
            }
        }
        foreach ($people as $position => $row) {
            if ($row['B'] !== '') $households[$row['B']] ??= $row['H'] ?? '';
            if (self::transferred($row)) $moved[$position] = $row;
            else $active[$position] = $row;
        }
        // NEW is a historical submission log, separate from the current resident registry.
        foreach ($sheets['NEW'] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['_source'] = 'NEW row '.$position;
            $row['O'] = $row['N'] ?? '';
            $new[$position] = $row;
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
