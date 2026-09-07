<?php
namespace App\Support;

use App\Models\Inhabitant;
use RuntimeException;

class MaslogWorkbook
{
    public static function residence(array $colors, string $remarks): array
    {
        foreach ($colors as $color) if (SourceResidenceSync::isGreen($color)) return [Inhabitant::RESIDENCE_ELSEWHERE, 'Green source marking'];
        if (preg_match('/\b(DAVAO|MANILA|BATANGAS|SINGAPORE|CAVITE|DUBAI|CEBU|SURIGAO|LAGUNA|SAMAR|SAUDI|BOHOL|AUSTRALIA|HAWAII|POLAND|SOGOD|OFW|ABROAD)\b|\bSTA\.\s*CRUZ\b/i', $remarks)) {
            return [Inhabitant::RESIDENCE_ELSEWHERE, 'Place or overseas residence in source remarks'];
        }
        if (preg_match('/US CITIZEN|GRACE JOY|^469$/i', trim($remarks))) return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Source remark needs residence confirmation'];
        // Maslog yellow marks household heads. School names alone do not establish another residence.
        $other = array_filter($colors, fn ($color) => ! in_array($color, ['', 'FFFFFFFF', 'FFFFFF', 'FFFFFF00'], true));
        if ($other) return [Inhabitant::RESIDENCE_UNCONFIRMED, 'Other marking needs residence confirmation'];
        return [Inhabitant::RESIDENCE_HERE, 'Registered in source without another residence indicated'];
    }

    public static function plan(array $sheets, ?string $emeliaHousehold = null): array
    {
        $rows = $sheets['CONSOLIDATED RBI'] ?? [];
        if (($rows[3]['O'] ?? '') !== 'MASLOG' || ($rows[10]['B'] ?? '') !== 'HH') throw new RuntimeException('Expected Maslog consolidated workbook');
        $households = $active = $moved = $deceased = $pending = $new = [];
        foreach ($rows as $position => $row) {
            if ($position < 11 || (empty($row['C']) && empty($row['D']))) continue;
            if (preg_match('/^(NOTE:|YELLOW - HOUSEHOLD HEAD|BLUE - DECEASED)$/', $row['C'] ?? '')) continue;
            $number = trim($row['B'] ?? '');
            if ($number !== '') $households[$number] ??= $row['H'] ?? '';
            $row['_source'] = 'CONSOLIDATED RBI row '.$position;
            if (empty($row['C']) || empty($row['D'])) { $pending[$position] = ['row'=>$row, 'reason'=>'Incomplete name']; continue; }
            $extra = [];
            foreach (['R','S'] as $column) if (! empty($row[$column]) && ! is_numeric($row[$column])) $extra[] = '[Source '.$column.': '.$row[$column].']';
            $row['Q'] = trim(($row['Q'] ?? '').' '.implode(' ', $extra));
            // Death markings also occur in occupation P and extra-note R.
            if (preg_match('/\bDECEASED?\b/i', ($row['P'] ?? '').' '.$row['Q'])) {
                $deceased[10000+$position] = [
                    'A'=>$number ?: 'Not recorded','B'=>$row['C'],'C'=>$row['D'],'D'=>$row['E'] ?? '',
                    'E'=>$row['F'] ?? '', 'F'=>$row['G'] ?? '', 'G'=>$row['H'] ?? '', 'H'=>$row['I'] ?? '',
                    'I'=>$row['J'] ?? '', 'J'=>$row['K'] ?? '', 'K'=>$row['L'] ?? '', 'L'=>$row['M'] ?? '',
                    'M'=>$row['N'] ?? '', 'N'=>$row['O'] ?? '', 'O'=>$row['P'] ?? '', 'P'=>$row['Q'], 'Q'=>'', '_source'=>$row['_source'],
                ];
                continue;
            }
            $active[$position] = $row;
        }
        // Exact duplicate name, birth date and demographics across two source households.
        if (isset($active[568], $active[993]) && ($active[568]['C'] ?? '') === 'ESPIRITU' && ($active[568]['D'] ?? '') === 'EMELIA') {
            foreach (['C','D','E','J','L'] as $column) if ($active[568][$column] !== $active[993][$column]) throw new RuntimeException('Emelia source identity changed; review required');
            if ($emeliaHousehold !== null && ! in_array($emeliaHousehold, ['123','229'], true)) throw new RuntimeException('Invalid confirmed household');
            $active[568]['B'] = $emeliaHousehold ?? '';
            $active[568]['Q'] .= ' [Duplicate source: row 993, HH 229; row 568, HH 123. '.($emeliaHousehold ? 'Confirmed household '.$emeliaHousehold : 'Household needs confirmation').'] '.($active[993]['Q'] ?? '');
            if ($emeliaHousehold === null) $pending['duplicate-568-993'] = ['row'=>$active[568], 'reason'=>'Imported once; household 123 or 229 needs confirmation'];
            unset($active[993]);
        }
        return compact('households', 'active', 'moved', 'deceased', 'pending', 'new');
    }
}
