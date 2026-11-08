<?php

namespace App\Support;

use App\Models\Inhabitant;
use App\Services\RbiReportMonth;
use RuntimeException;

class TinagoWorkbook
{
    public const PRIMARY_SHEET = 'Updated 2025';

    public static function redName(array $row): bool
    {
        foreach (['C', 'D'] as $column) {
            if (substr(strtoupper($row['_fills'][$column] ?? ''), -6) === 'FF0000') return true;
        }
        return false;
    }

    public static function outsideResidence(string $remark): bool
    {
        return (bool) preg_match('/\b(?:MOVED?\s+OUT|NO\s+LONGER\s+(?:LIVING|RESIDING|STAYING)\s+(?:IN|AT)\s+(?:BRGY\.?\s+)?TINAGO|NOT\s+(?:LIVING|RESIDING|STAYING)\s+(?:IN|AT)\s+(?:BRGY\.?\s+)?TINAGO)\b/i', $remark)
            || (bool) preg_match('/\b(?:WALA|DILI)\s+(?:NA\s+)?(?:NAGPUYO|NAG\s+PUYO|PUYO)\s+(?:SA\s+)?TINAGO\b/i', $remark)
            || (bool) preg_match('/\b(?:LIVES?|LIVING|RESIDES?|RESIDING|STAYS?|STAYING)\s+(?:IN|AT)\s+(?!(?:BRGY\.?\s+)?TINAGO\b)\S+/i', $remark);
    }

    public static function transferred(array $row): bool
    {
        $remark = $row['Q'] ?? '';
        return ! preg_match('/\b(?:NOT|NO|NEVER)\s+\S*TRANSFER/i', $remark)
            && (bool) preg_match('/\b(?:TRANSFERRED|TRANSFERED|TRANSFRRED|TRANSFEERED|TRANSFFERED)\b/i', $remark);
    }

    public static function residence(array $row): array
    {
        if (self::transferred($row)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Explicit transfer in source'];
        if (preg_match('/\b(?:OFW|ABROAD|OVERSEAS|SAUDI|QATAR|HONG\s*KONG|TAIWAN|JAPAN)\b/i', ($row['P'] ?? '').' '.($row['Q'] ?? ''))
            || self::outsideResidence($row['Q'] ?? '')) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Source indicates living elsewhere; remains registered without an explicit transfer'];
        }
        if (! empty($row['_household_conflict'])) return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Conflicting source household assignments'];
        $remark = $row['Q'] ?? '';
        // Origin notes do not establish a current residence outside Tinago.
        if (preg_match('/^FROM\s+[^\[]+(?:\[|$)/i', trim($remark))
            || preg_match('/\b(?:LIVING|RESIDING|STAYING)\s+(?:IN|AT)\s+(?:BRGY\.?\s+)?TINAGO\b|^AT\s+TINAGO\b/i', $remark)) $remark = '';
        return SourceResidenceSync::classify([$row['_fills']['C'] ?? '', $row['_fills']['D'] ?? ''], $remark);
    }

    private static function key(array $row, array $columns): string
    {
        return implode('|', array_map(fn ($c) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($row[$c] ?? '', " \t\r\n-"))), $columns));
    }

    private static function merge(array $first, array $second, string $remarks): array
    {
        foreach ($second as $column => $value) {
            if ($column === '_source' || $column === $remarks || $column === '_fills') continue;
            if (($first[$column] ?? '') === '') $first[$column] = $value;
        }
        $first[$remarks] = trim(($first[$remarks] ?? '').' '.($second[$remarks] ?? '').' [Also '.$second['_source'].']');
        return $first;
    }

    public static function plan(array $sheets): array
    {
        foreach ([self::PRIMARY_SHEET, 'DECEASED', 'NEW'] as $sheet) {
            if (! isset($sheets[$sheet])) throw new RuntimeException('Missing '.$sheet.' worksheet.');
        }
        if (($sheets[self::PRIMARY_SHEET][3]['O'] ?? '') !== 'TINAGO') throw new RuntimeException('Expected the Tinago Updated 2025 workbook.');
        $households = $active = $moved = $deceased = $pending = $new = $deadKeys = $deadLoose = $people = $keys = [];
        foreach ($sheets['DECEASED'] as $position => $row) {
            if ($position === 1) continue;
            $row['_source'] = 'DECEASED row '.$position;
            if (empty($row['B']) || empty($row['C'])) {
                $pending['dead-'.$position] = ['row' => $row, 'reason' => 'Incomplete deceased name; retained for review'];
                continue;
            }
            $key = self::key($row, ['B', 'C', 'D', 'E', 'I']);
            if (isset($deadKeys[$key])) {
                $keep = $deadKeys[$key];
                $deceased[$keep] = self::merge($deceased[$keep], $row, 'P');
                $pending['dead-'.$position] = ['row' => $row, 'reason' => 'Duplicate deceased entry merged with row '.$keep];
                continue;
            }
            $deadKeys[$key] = $position;
            $deadLoose[self::key($row, ['B', 'C', 'E', 'I'])][] = $position;
            $deceased[$position] = $row;
        }
        $household = '';
        foreach ($sheets[self::PRIMARY_SHEET] as $position => $source) {
            if ($position < 10) continue;
            // Updated 2025 has one HH column, merged/blank for subsequent members.
            if (filled($source['A'] ?? null)) $household = trim($source['A']);
            elseif (preg_match('/^HEAD$/i', trim($source['F'] ?? ''))) $household = '';
            $row = ['A' => '', 'B' => $household, '_source' => self::PRIMARY_SHEET.' row '.$position];
            $map = ['C'=>'B','D'=>'C','E'=>'D','F'=>'E','G'=>'F','H'=>'G','I'=>'H','J'=>'I',
                'K'=>'J','L'=>'K','M'=>'L','N'=>'M','O'=>'O','P'=>'P','Q'=>'Q'];
            foreach ($map as $to => $from) {
                $row[$to] = $source[$from] ?? '';
                $row['_fills'][$to] = $source['_fills'][$from] ?? '';
            }
            if ($household !== '' && preg_match('/^\d+[A-Za-z-]*$/', $household)) $households[$household] ??= $row['H'];
            if ($row['C'] === '' && $row['D'] === '') continue;
            if ($row['C'] === '' || $row['D'] === '' || preg_match('/\bVACANT\b/i', $row['C'].' '.$row['D'])) {
                $pending[$position] = ['row' => $row, 'reason' => 'Vacant property or incomplete name; not imported as a person'];
                continue;
            }
            if (! preg_match('/^\d+[A-Za-z-]*$/', $row['B'])) $row['B'] = '';
            if (filled($source['N'] ?? null)) $row['Q'] = trim($row['Q'].' [Household member classification: '.$source['N'].']');
            $key = self::key($row, ['C', 'D', 'E', 'F', 'J']);
            $loose = $deadLoose[self::key($row, ['C', 'D', 'F', 'J'])] ?? [];
            $deadPosition = $deadKeys[$key] ?? (count($loose) === 1 && $row['J'] !== '' ? $loose[0] : null);
            if ($deadPosition !== null || self::redName($row) || preg_match('/\b(?:DECEASED|DECAESED|DESEACED)\b/i', $row['Q'])) {
                if (self::redName($row)) $row['Q'] .= ' [Deceased: red source name highlight, as confirmed by user]';
                if ($deadPosition !== null) {
                    $deceased[$deadPosition]['P'] = trim(($deceased[$deadPosition]['P'] ?? '').' '.$row['Q'].' [Also '.$row['_source'].']');
                } else {
                    $dead = ['_source' => $row['_source']];
                    foreach (range('A', 'P') as $column) $dead[$column] = $row[chr(ord($column) + 1)] ?? '';
                    $deadPosition = 10000 + $position;
                    $deceased[$deadPosition] = $dead;
                    $deadKeys[self::key($dead, ['B', 'C', 'D', 'E', 'I'])] = $deadPosition;
                    $deadLoose[self::key($dead, ['B', 'C', 'E', 'I'])][] = $deadPosition;
                }
                continue;
            }
            if (isset($keys[$key])) {
                $keep = $keys[$key];
                $first = $people[$keep];
                $people[$keep] = self::merge($first, $row, 'Q');
                if ($first['B'] !== $row['B']) {
                    $people[$keep]['B'] = '';
                    $people[$keep]['_household_conflict'] = true;
                    $people[$keep]['Q'] .= ' [Conflicting source households: '.$first['B'].' / '.$row['B'].']';
                }
                $pending[$position] = ['row' => $row, 'reason' => 'Duplicate person imported once; source household assignments retained for review'];
            } else {
                $keys[$key] = $position;
                $people[$position] = $row;
            }
        }
        foreach ($people as $position => $row) {
            if (self::transferred($row)) $moved[$position] = $row;
            else $active[$position] = $row;
        }
        $sectionYear = null;
        foreach ($sheets['NEW'] as $position => $row) {
            if (str_contains(strtoupper($row['B'] ?? ''), 'INHABITANT')) {
                $sectionYear = preg_match('/\b((?:19|20)\d{2})\b/', $row['B'], $match) ? $match[1] : null;
                continue;
            }
            $row['_source'] = 'NEW row '.$position;
            if (empty($row['B']) || empty($row['C'])) {
                $pending['new-'.$position] = ['row' => $row, 'reason' => 'Incomplete NEW entry; retained for review'];
                continue;
            }
            $row['O'] = $row['N'] ?? '';
            $label = trim($row['O']);
            $hasYear = preg_match('/\b(?:19|20)\d{2}\b/', $label);
            $month = (new RbiReportMonth)->resolve(null, $hasYear ? $label : ($sectionYear ? $label.' '.$sectionYear : $label));
            $row['_reporting_month'] = $month ? $month.'-01' : null;
            if (! $hasYear && $sectionYear && $month) $row['M'] = trim(($row['M'] ?? '').' [Reporting year '.$sectionYear.' from NEW section heading]');
            $new[$position] = $row;
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
