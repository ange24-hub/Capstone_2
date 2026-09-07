<?php

namespace App\Support;

use RuntimeException;

class RizalWorkbook
{
    public static function residence(array $row): array
    {
        if (preg_match('/\b(OFW|ABROAD|OVERSEAS)\b/i', ($row['P'] ?? '').' '.($row['Q'] ?? ''))) {
            return [\App\Models\Inhabitant::RESIDENCE_ELSEWHERE, 'Overseas work in source'];
        }
        if (str_contains(strtoupper($row['Q'] ?? ''), 'VACANT')) {
            return [\App\Models\Inhabitant::RESIDENCE_ELSEWHERE, 'VACANT in source; living elsewhere confirmed by user'];
        }
        return SourceResidenceSync::classify([$row['_fills']['C'] ?? '', $row['_fills']['D'] ?? ''], $row['Q'] ?? '');
    }

    private static function identity(array $parts): string
    {
        return implode('|', array_map(fn ($value) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($value))), $parts));
    }

    public static function plan(array $sheets): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'RIZAL') {
            throw new RuntimeException('Expected the Rizal consolidated workbook.');
        }
        $households = $active = $moved = $deceased = $pending = $new = $deceasedByIdentity = [];
        foreach ($sheets['DECEASED'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['A'] = in_array(trim($row['A'] ?? ''), ['', '-'], true) ? 'Not recorded' : trim($row['A']);
            if (strcasecmp($row['D'] ?? '', 'not recorded in RBI') === 0) { $row['D'] = ''; $row['R'] = 'Not recorded in RBI'; }
            // Rizal places the death date in P, with no remarks column.
            $row['Q'] = $row['P'] ?? '';
            $row['P'] = $row['R'] ?? '';
            $key = self::identity([$row['B'], $row['C'], $row['D'] ?? '', $row['I'] ?? '']);
            if (isset($deceasedByIdentity[$key])) throw new RuntimeException('Duplicate deceased identity requires review.');
            $deceasedByIdentity[$key] = $position;
            $row['_source'] = 'DECEASED row '.$position;
            $deceased[$position] = $row;
        }
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 11 || (empty($row['C']) && empty($row['D']))) continue;
            $number = trim($row['B'] ?? '');
            if ($number !== '') $households[$number] ??= $row['H'] ?? '';
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['reason' => 'Incomplete name', 'row' => $row];
                continue;
            }
            $key = self::identity([$row['C'], $row['D'], $row['E'] ?? '', $row['J'] ?? '']);
            $match = $deceasedByIdentity[$key] ?? null;
            if ($match !== null || str_contains(strtoupper($row['Q'] ?? ''), 'DECEASED')) {
                if ($match !== null) {
                    $deceased[$match]['P'] = trim(($deceased[$match]['P'] ?? '').' '.($row['Q'] ?? '').' [Also '.$row['_source'].']');
                    $deceased[$match]['G'] = ($deceased[$match]['G'] ?? '') ?: ($row['H'] ?? '');
                } else {
                    $deceased[10000 + $position] = [
                        'A' => $number ?: 'Not recorded', 'B' => $row['C'], 'C' => $row['D'], 'D' => $row['E'] ?? '',
                        'E' => $row['F'] ?? '', 'F' => $row['G'] ?? '', 'G' => $row['H'] ?? '', 'H' => $row['I'] ?? '',
                        'I' => $row['J'] ?? '', 'J' => $row['K'] ?? '', 'K' => $row['L'] ?? '', 'L' => $row['M'] ?? '',
                        'M' => $row['N'] ?? '', 'N' => $row['O'] ?? '', 'O' => $row['P'] ?? '',
                        'P' => $row['Q'] ?? '', 'Q' => '', '_source' => $row['_source'],
                    ];
                }
                continue;
            }
            if (preg_match('/\bTRANSFERRED\s+TO\s+\S/i', $row['Q'] ?? '')) {
                $moved[$position] = $row;
            } else {
                $active[$position] = $row;
            }
        }
        foreach ($sheets['NEW'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['O'] = $row['N'] ?? '';
            $row['_source'] = 'NEW row '.$position;
            $new[$position] = $row;
        }

        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
