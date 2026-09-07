<?php

namespace App\Support;

use App\Models\Inhabitant;
use RuntimeException;

class SanMiguelWorkbook
{
    public static function localHousehold(array $row): ?string
    {
        return preg_match('/(?:\bHH|\bATHH)\s*#?\s*(\d+)\b/i', $row['Q'] ?? '', $match) ? $match[1] : null;
    }

    public static function transferred(array $row): bool
    {
        $remark = $row['Q'] ?? '';
        if (preg_match('/\b(?:NOT|NO|NEVER)\s+\S*TRANSFER/i', $remark)) return false;
        return (bool) preg_match('/\b(?:TRANSFERRED|TRANSFERED|TRANSFRRED|TRANSFEERED|TRANSFFERED|TRANSFFERES|TRANSFFERD|RANSFEERED|TRANSFERRE|TRANSFERRD|TREANSFERRED)\b/i', $remark);
    }

    public static function residence(array $row): array
    {
        $remark = $row['Q'] ?? '';
        if (self::transferred($row)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Explicit transfer remark in source, including source spelling variants'];
        if (preg_match('/\b(OFW|ABROAD|OVERSEAS|JEDDAH|HONGKONG|QUATAR|QATAR|AUSTRIA|TAIWAN|JAPAN|SAUDI|UK)\b/i', ($row['P'] ?? '').' '.$remark)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Overseas location or occupation in source'];
        }
        if (self::localHousehold($row) !== null) return [Inhabitant::RESIDENCE_ELSEWHERE, 'HH location note; remains registered, living elsewhere as instructed by user'];
        if (preg_match('/NATURE.S SPRING CEBU|4ps Maslog/i', $remark)) return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Workplace or program location requires residence confirmation'];
        if (preg_match('/\b(CEBU|MANILA|MAYNILA|QUEZON CITY|DAVAO|SOGOD|BATO KAHUPIAN)\b/i', $remark)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Other location recorded in source'];
        }
        return SourceResidenceSync::classify([$row['_fills']['C'] ?? '', $row['_fills']['D'] ?? ''], $remark);
    }

    private static function key(array $row, array $columns): string
    {
        return implode('|', array_map(fn ($column) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($row[$column] ?? ''))), $columns));
    }

    public static function plan(array $sheets): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'SAN MIGUEL') throw new RuntimeException('Expected the San Miguel workbook.');
        foreach (['DECEASED', 'NEW'] as $name) if (! isset($sheets[$name])) throw new RuntimeException('Missing '.$name.' sheet.');
        $households = $active = $moved = $deceased = $pending = $new = $deadKeys = $people = $keys = [];
        foreach ($sheets['DECEASED'] as $position => $source) {
            if ($position === 1) continue;
            $row = [];
            foreach (range('A', 'Q') as $column) $row[$column] = $source[chr(ord($column) + 1)] ?? '';
            if (empty($row['B']) || empty($row['C'])) throw new RuntimeException('Incomplete deceased record at row '.$position);
            $row['_source'] = 'DECEASED row '.$position;
            $key = self::key($row, ['B', 'C', 'D', 'E', 'I']);
            if (isset($deadKeys[$key])) throw new RuntimeException('Duplicate deceased identity requires review.');
            $deadKeys[$key] = $position;
            $deceased[$position] = $row;
        }
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 11 || (empty($row['C']) && empty($row['D']))) continue;
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            $row['B'] = trim($row['B'] ?? '');
            $row['Q'] ??= '';
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['row' => $row, 'reason' => 'Incomplete name; not imported'];
                continue;
            }
            if ($row['B'] !== '') $households[$row['B']] ??= $row['H'] ?? '';
            $key = self::key($row, ['C', 'D', 'E', 'F', 'J']);
            if (isset($deadKeys[$key]) || preg_match('/\b(?:DECEASED|DECAESED|DESEACED)\b/i', $row['Q'])) {
                if (isset($deadKeys[$key])) {
                    $keep = $deadKeys[$key];
                    $deceased[$keep]['P'] = trim(($deceased[$keep]['P'] ?? '').' '.$row['Q'].' [Also '.$row['_source'].']');
                } else {
                    $dead = [];
                    foreach (range('A', 'P') as $column) $dead[$column] = $row[chr(ord($column) + 1)] ?? '';
                    $dead['_source'] = $row['_source'];
                    $deadKeys[$key] = 10000 + $position;
                    $deceased[10000 + $position] = $dead;
                }
                continue;
            }
            if (isset($keys[$key])) {
                $keep = $keys[$key];
                $first = $people[$keep];
                $target = self::localHousehold($first) ?? self::localHousehold($row);
                $selected = $target !== null && $row['B'] === $target ? $row : $first;
                $selected['_source'] = $first['_source'];
                $selected['Q'] = trim($first['Q'].' '.$row['Q'].' [Also '.$row['_source'].']');
                $reason = 'Duplicate imported once; source households '.$first['B'].' and '.$row['B'];
                if ($first['B'] !== $row['B'] && $target === null) {
                    $selected['B'] = $selected['A'] = '';
                    $reason .= '; household assignment needs confirmation';
                    $selected['Q'] .= ' [Source households: '.$first['B'].' / '.$row['B'].'; household needs confirmation]';
                }
                $people[$keep] = $selected;
                $pending[$position] = ['row' => $row, 'reason' => $reason];
            } else {
                $keys[$key] = $position;
                $people[$position] = $row;
            }
        }
        foreach ($people as $position => $row) {
            if (! self::transferred($row) && ($target = self::localHousehold($row)) !== null) {
                if (! isset($households[$target])) throw new RuntimeException('Unknown destination household '.$target);
                if ($row['B'] !== $target) {
                    $pending['household-'.$position] = ['row' => $row, 'reason' => 'Assigned to source destination household '.$target.' within San Miguel'];
                    $row['Q'] .= ' [Original household: '.$row['B'].']';
                    $row['B'] = $target;
                    $row['A'] = '';
                }
            }
            if (self::transferred($row)) $moved[$position] = $row;
            else $active[$position] = $row;
        }
        foreach ($sheets['NEW'] as $position => $row) {
            if ($position === 1) continue;
            if (empty($row['B']) || empty($row['C'])) throw new RuntimeException('Incomplete NEW entry at row '.$position);
            $row['_source'] = 'NEW row '.$position;
            $row['O'] = $row['N'] ?? '';
            $new[$position] = $row;
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
