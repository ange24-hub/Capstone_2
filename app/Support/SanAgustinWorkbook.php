<?php

namespace App\Support;

use RuntimeException;

class SanAgustinWorkbook
{
    public static function residence(array $row): array
    {
        $remarks = $row['Q'] ?? '';
        if (preg_match('/\b(OFW|ABROAD|OVERSEAS|SAUDI|MALAYSIA|HONGKONG|QATAR|KUWAIT|DUBAI|CANADA|JAPAN|AMERICA|TAIWAN|ABU DHABI|RIYAH?D|SINGAPORE|SOUTH KOREA)\b/i', ($row['P'] ?? '').' '.$remarks)) {
            return [\App\Models\Inhabitant::RESIDENCE_ELSEWHERE, 'Overseas location in source'];
        }
        if (preg_match('/MANILA|SOGOD|HILONGOS|BANDAY|SAN ANTONIO|LILOAN|BONTOC|CEBU|DAVAO|LAGUNA|INIGUIHAN|MAASIN|TINAGO|SAN ISIDRO|ANAHAWAN|BATANGAS|ORMOC|UBAGO/i', $remarks)) {
            return [\App\Models\Inhabitant::RESIDENCE_ELSEWHERE, 'Other location in source; interpretation confirmed by user'];
        }
        return [\App\Models\Inhabitant::RESIDENCE_HERE, 'No other residence indicated in source'];
    }

    private static function identity(array $parts): string
    {
        return implode('|', array_map(fn ($value) => mb_strtoupper(preg_replace('/\s+/', ' ', trim($value))), $parts));
    }

    public static function plan(array $sheets, ?string $mulaanHousehold = null): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'SAN AGUSTIN') {
            throw new RuntimeException('Expected the SanAgustin consolidated workbook.');
        }
        foreach ($sheets['DECEASED'] ?? [] as $position => $sourceRow) {
            if ($position < 2) continue;
            $row = [];
            foreach (range('A', 'Q') as $column) $row[$column] = $sourceRow[chr(ord($column) + 1)] ?? '';
            if (stripos($row['E'] ?? '', 'no record') !== false) { $row['P'] .= ' [Source: '.$row['E'].']'; $row['E'] = ''; }
            if (($row['D'] ?? '') === '-') $row['D'] = '';
            $sheets['DECEASED'][$position] = $row;
        }
        $households = $active = $moved = $deceased = $pending = $new = $deceasedByIdentity = [];
        foreach ($sheets['DECEASED'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['A'] = in_array(trim($row['A'] ?? ''), ['', '-'], true) ? 'Not recorded' : trim($row['A']);
            if (strcasecmp($row['D'] ?? '', 'not recorded in RBI') === 0) { $row['D'] = ''; $row['R'] = 'Not recorded in RBI'; }
            $key = self::identity([$row['B'], $row['C'], $row['D'] ?? '', $row['I'] ?? '']);
            if (isset($deceasedByIdentity[$key])) throw new RuntimeException('Duplicate deceased identity requires review.');
            $deceasedByIdentity[$key] = $position;
            $row['_source'] = 'DECEASED row '.$position;
            $deceased[$position] = $row;
        }
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 11 || (empty($row['C']) && empty($row['D']))) continue;
            if (strtoupper($row['C'] ?? '') === 'VACANT') { $pending[$position] = ['reason'=>'Vacant property, not a person', 'row'=>$row]; continue; }
            $number = trim($row['B'] ?? '');
            if ($number !== '') $households[$number] ??= $row['H'] ?? '';
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['reason' => 'Incomplete name', 'row' => $row];
                continue;
            }
            $key = self::identity([$row['C'], $row['D'], $row['E'] ?? '', $row['J'] ?? '']);
            $match = $deceasedByIdentity[$key] ?? null;
            if ($match !== null || preg_match('/DECEASED|DESEACED/i', $row['Q'] ?? '')) {
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
            if (preg_match('/\b(?:TRANSFER(?:RED)?|TANSFER|TRNSFE)\s+(?:TO\s+)?\S/i', $row['Q'] ?? '')) {
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

        if ($mulaanHousehold !== null && ! in_array($mulaanHousehold, ['18', '145'], true)) throw new RuntimeException('Invalid Mulaan household');
        foreach ([[91, 583], [92, 584], [93, 585]] as [$first, $second]) {
            if (! isset($active[$first], $active[$second])) continue;
            foreach (['C', 'D', 'L'] as $column) {
                if ($active[$first][$column] !== $active[$second][$column]) throw new RuntimeException('Mulaan duplicate identity changed; review required');
            }
            if ($first !== 91 && $active[$first]['E'] !== $active[$second]['E']) throw new RuntimeException('Mulaan middle name changed');
            $keep = $mulaanHousehold === '18' ? $first : $second;
            $drop = $keep === $first ? $second : $first;
            $active[$keep]['B'] = $mulaanHousehold ?? '';
            $active[$keep]['Q'] .= ' [Duplicate source: rows '.$first.' and '.$second.'; HH 18 and 145. '.($mulaanHousehold ? 'Confirmed household '.$mulaanHousehold : 'Household needs confirmation').']';
            $pending['duplicate-'.$first.'-'.$second] = ['row'=>$active[$drop], 'reason'=>'Duplicate imported once; '.($mulaanHousehold ? 'confirmed HH '.$mulaanHousehold : 'household requires review')];
            unset($active[$drop]);
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
