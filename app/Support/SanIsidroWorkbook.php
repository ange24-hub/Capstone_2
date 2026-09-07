<?php

namespace App\Support;

use App\Models\Inhabitant;
use RuntimeException;

class SanIsidroWorkbook
{
    public static function transferred(array $row): bool
    {
        $remark = $row['Q'] ?? '';
        return ! preg_match('/\b(?:NOT|NO|NEVER)\s+TRANSFER/i', $remark)
            && (bool) preg_match('/\b(?:TRANSFERRED|TRANSFERED)\b/i', $remark);
    }

    public static function residence(array $row): array
    {
        if (self::transferred($row)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Explicit transfer in source'];
        $text = ($row['P'] ?? '').' '.($row['Q'] ?? '');
        if (preg_match('/\b(?:OFW|ABROAD|OUT OF COUNTRY)\b/i', $text)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Overseas entry; classification confirmed by user'];
        }
        if (stripos($text, 'MITSUBISHI CEBU') !== false) {
            return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Cebu employment alone does not confirm residence; review requested by user'];
        }
        return [Inhabitant::RESIDENCE_HERE, 'No other residence indicated in source'];
    }

    private static function identity(array $row, array $columns): string
    {
        return implode('|', array_map(fn ($column) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($row[$column] ?? ''))), $columns));
    }

    public static function plan(array $sheets): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'SAN ISIDRO' || ! isset($sheets['DECEASED'])) {
            throw new RuntimeException('Expected San Isidro consolidated and deceased worksheets.');
        }
        $households = $active = $moved = $deceased = $pending = $new = $deadKeys = $seen = [];
        foreach ($sheets['DECEASED'] as $position => $row) {
            if (empty($row['B']) || empty($row['C'])) throw new RuntimeException('Incomplete deceased record at row '.$position);
            $key = self::identity($row, ['B', 'C', 'D', 'E', 'I']);
            if (isset($deadKeys[$key])) throw new RuntimeException('Duplicate deceased identity requires review.');
            $deadKeys[$key] = $position;
            $row['_source'] = 'DECEASED row '.$position;
            $deceased[$position] = $row;
        }
        $number = '';
        $unassigned = [1347 => '143', 1348 => '143', 1349 => '144', 1350 => '145', 1351 => '145'];
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 11) continue;
            if (filled($row['A'] ?? null)) $number = trim($row['A']);
            if (empty($row['C']) && empty($row['D'])) continue;
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            $row['B'] = $number;
            $row['Q'] ??= '';
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['row' => $row, 'reason' => 'Incomplete name; not imported'];
                continue;
            }
            if (isset($unassigned[$position])) {
                if (($row['A'] ?? '') !== $unassigned[$position]) throw new RuntimeException('Reviewed household assignment changed at row '.$position);
                $pending[$position] = ['row' => $row, 'reason' => 'Source household '.$number.' repeats an earlier household head; imported unassigned as instructed by user'];
                $row['Q'] .= ' [Source household: '.$number.'; assignment withheld pending confirmation as instructed by user]';
                $row['B'] = $row['A'] = '';
            }
            if ($row['B'] !== '') $households[$row['B']] ??= $row['H'] ?? '';
            $key = self::identity($row, ['C', 'D', 'E', 'F', 'J']);
            if (isset($seen[$key])) throw new RuntimeException('Duplicate consolidated identity requires review at row '.$position);
            $seen[$key] = true;
            if (isset($deadKeys[$key])) {
                $keep = $deadKeys[$key];
                $deceased[$keep]['P'] = trim(($deceased[$keep]['P'] ?? '').' '.$row['Q'].' [Also '.$row['_source'].']');
                continue;
            }
            if (preg_match('/\b(?:DECEASED|DESCEASED|DESEACED)\b/i', $row['Q'])) {
                $dead = [];
                foreach (range('A', 'P') as $column) $dead[$column] = $row[chr(ord($column) + 1)] ?? '';
                $dead['_source'] = $row['_source'];
                $dead['A'] = $dead['A'] ?: 'Not recorded';
                $deceased[10000 + $position] = $dead;
                continue;
            }
            if (self::transferred($row)) $moved[$position] = $row;
            else $active[$position] = $row;
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
