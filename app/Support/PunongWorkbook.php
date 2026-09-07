<?php

namespace App\Support;

use App\Models\Inhabitant;

class PunongWorkbook
{
    public static function plan(array $sheets): array
    {
        if (($sheets['CONSOLIDATED RBI'][3]['O'] ?? '') !== 'PUNONG') throw new \RuntimeException('Expected Punong workbook');
        $households = $active = $moved = $deceased = $pending = $new = $deadNames = [];
        $key = fn (array $parts) => mb_strtoupper(implode('|', array_map('trim', $parts)));
        foreach ($sheets['DECEASED'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $deadNames[$key([$row['B'], $row['C'], $row['D'] ?? ''])] = $position;
            // Q is date submitted, not date of death.
            $row['P'] = trim(($row['P'] ?? '').' [Date submitted: '.($row['Q'] ?? '').']');
            $row['Q'] = '';
            $row['_source'] = 'DECEASED row '.$position;
            $deceased[$position] = $row;
        }
        foreach ($sheets['CONSOLIDATED RBI'] as $position => $row) {
            if ($position < 12 || (empty($row['C']) && empty($row['D']))) continue;
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            $number = trim($row['B'] ?? '');
            if ($number !== '') $households[$number] ??= $row['H'] ?? '';
            if (empty($row['C']) || empty($row['D'])) {
                $pending[$position] = ['row'=>$row, 'reason'=>'Incomplete name'];
                continue;
            }
            $match = $deadNames[$key([$row['C'], $row['D'], $row['E'] ?? ''])] ?? null;
            if ($match !== null) {
                $deceased[$match]['P'] .= ' [Also '.$row['_source'].']';
                continue;
            }
            $active[$position] = $row;
        }
        foreach ($sheets['NEW'] ?? [] as $position => $row) {
            if ($position < 2 || empty($row['B']) || empty($row['C'])) continue;
            $row['O'] = $row['N'] ?? '';
            $row['_source'] = 'NEW row '.$position;
            $new[$position] = $row;
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }

    public static function residence(array $row): array
    {
        // The user excluded the isolated address highlights on source rows 28 and 29.
        // Require yellow on both name cells so isolated cell formatting is ignored.
        $fills = $row['_fills'] ?? [];
        $yellow = fn (string $column) => substr(strtoupper($fills[$column] ?? ''), -6) === 'FFFF00';
        if ($yellow('C') && $yellow('D')) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Yellow row marking; interpretation confirmed by user'];
        }
        $remarks = trim($row['R'] ?? '');
        if (preg_match('/\b(MANILA|HINUNANGAN|CEBU|HILONGOS|UAE|CANADA|SOGOD|LILO-AN|BONTOC|OFW|ABROAD|OVERSEAS)\b|OUT OF THE BARANGAY/i', $remarks)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Another location in source remarks; interpretation confirmed by user'];
        }
        return [Inhabitant::RESIDENCE_HERE, 'No yellow row marking or other location in source remarks'];
    }
}
