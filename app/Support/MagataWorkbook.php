<?php

namespace App\Support;

use RuntimeException;

class MagataWorkbook
{
    private static function identity(array $parts): string
    {
        // Full source names identify overlaps; discrepant birth dates are retained in remarks.
        $parts = array_slice($parts, 0, 3);
        return implode('|', array_map(fn ($value) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($value))), $parts));
    }

    public static function plan(array $sheets, string $vecinaResolution = 'review'): array
    {
        if (($sheets['CONSOLIDATED RBI'][8]['J'] ?? '') !== '9'
            || ! str_contains($sheets['CONSOLIDATED RBI'][9]['H'] ?? '', 'MAG-ATA')) {
            throw new RuntimeException('Expected the Magata consolidated workbook.');
        }
        $households = $active = $moved = $deceased = $pending = $new = $deceasedByIdentity = [];
        foreach ($sheets['DECEASE INFO'] ?? [] as $position => $original) {
            if ($position < 5 || empty($original['C']) || empty($original['D'])) continue;
            if ($position === 12 && ($original['C'] ?? '') === 'VECINA' && ($original['D'] ?? '') === 'VINCENT') {
                if ($vecinaResolution === 'review') {
                    $pending['DECEASE INFO row 12'] = ['reason'=>'Possible duplicate of VICENTE PALERO VECINA in household 92', 'row'=>$original];
                    continue;
                }
                if ($vecinaResolution === 'merge') {
                    $original['Q'] = trim(($original['Q'] ?? '').' [Source first name: VINCENT; confirmed same person as VICENTE]');
                    $original['D'] = 'VICENTE';
                }
            }
            $row = [];
            foreach (range('A', 'P') as $column) $row[$column] = $original[chr(ord($column) + 1)] ?? '';
            $row['A'] = $row['A'] ?: 'Not recorded';
            $row['Q'] = $original['R'] ?? '';
            $key = self::identity([$row['B'], $row['C'], $row['D'] ?? '', $row['I'] ?? '']);
            if (isset($deceasedByIdentity[$key])) throw new RuntimeException('Duplicate deceased identity requires review.');
            $deceasedByIdentity[$key] = $position;
            $row['_source'] = 'DECEASE INFO row '.$position;
            $deceased[$position] = $row;
        }
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 9 || (empty($row['C']) && empty($row['D']))) continue;
            $number = trim($row['B'] ?? '');
            if ($number !== '') $households[$number] ??= $row['H'] ?? '';
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['reason' => 'Incomplete name', 'row' => $row];
                continue;
            }
            $key = self::identity([$row['C'], $row['D'], $row['E'] ?? '', $row['J'] ?? '']);
            $match = $deceasedByIdentity[$key] ?? null;
            if ($match !== null || str_contains(strtoupper($row['Q'] ?? ''), 'DECEASE') || ($row['_fill'] ?? '') === 'FFFF0000') {
                if ($match !== null) {
                    $deceased[$match]['P'] = trim(($deceased[$match]['P'] ?? '').' '.($row['Q'] ?? '').' [Also '.$row['_source'].']');
                    $deceased[$match]['G'] = ($deceased[$match]['G'] ?? '') ?: ($row['H'] ?? '');
                    if (($deceased[$match]['A'] ?? '') === 'Not recorded' && $number !== '') $deceased[$match]['A'] = $number;
                    if (($deceased[$match]['I'] ?? '') !== ($row['J'] ?? '')) {
                        $deceased[$match]['P'] .= ' [Consolidated birth date: '.($row['J'] ?? '').']';
                        if (empty($deceased[$match]['I'])) $deceased[$match]['I'] = $row['J'] ?? '';
                    }
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
            if (preg_match('/\bTRANSFER(?:RED)?\s+(?:TO\s+)?\S/i', $row['Q'] ?? '')) {
                $moved[$position] = $row;
            } else {
                $active[$position] = $row;
            }
        }
        foreach ($sheets['NEW INHABITANT INFORMATION'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            throw new RuntimeException('New Inhabitant sheet now contains records; review its column mapping before importing.');
        }

        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
