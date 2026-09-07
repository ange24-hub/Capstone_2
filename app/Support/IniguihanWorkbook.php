<?php

namespace App\Support;

use RuntimeException;

class IniguihanWorkbook
{
    public static function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        try {
            if (is_numeric($value)) {
                $date = \Illuminate\Support\Carbon::create(1899, 12, 30)->addDays((int) floor((float) $value));
            } elseif (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~', $value, $parts)) {
                if (! checkdate((int) $parts[1], (int) $parts[2], (int) $parts[3])) return null;
                $date = \Illuminate\Support\Carbon::create((int) $parts[3], (int) $parts[1], (int) $parts[2]);
            } elseif (str_contains($value, '/')) {
                return null;
            } else {
                $date = \Illuminate\Support\Carbon::parse($value);
            }

            return $date->year < 1800 || $date->startOfDay()->isFuture() ? null : $date->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function normalize(array $sheets): array
    {
        if (! str_contains($sheets['Sheet1'][3]['J'] ?? '', 'INIGUIHAN')) {
            throw new RuntimeException('Expected Iniguihan household forms in Sheet1.');
        }
        $active = [3 => ['O' => 'INIGUIHAN']];
        $households = [];
        $number = null;
        $headerRow = null;
        $first = false;
        $deceased = [];
        foreach ($sheets['Sheet1'] as $position => $row) {
            if (preg_match('/HOUSEHOLD NO\.\s*(\d+)\s*-\s*(.+)/i', $row['J'] ?? '', $match)) {
                $number = $match[1];
                if (isset($households[$number])) {
                    throw new RuntimeException("Repeated household header at row {$position}.");
                }
                $households[$number] = ['purok' => trim($match[2]), 'address' => null, 'members' => 0];
                $headerRow = $position;
                $first = true;
                continue;
            }
            // The member table starts seven rows after each household header and
            // ends before the printed legend; never import labels or signatures.
            if ($headerRow === null || $position < $headerRow + 7 || $position > $headerRow + 30) {
                continue;
            }
            if (empty($row['A']) && empty($row['B'])) {
                continue;
            }
            if (empty($row['A']) || empty($row['B'])) {
                throw new RuntimeException("Incomplete resident name at Sheet1 row {$position}.");
            }
            $households[$number]['members']++;
            $households[$number]['address'] ??= $row['F'] ?? null;
            $active[$position] = [
                'A' => $first ? $number : '', 'B' => $number,
                'C' => $row['A'], 'D' => $row['B'], 'E' => $row['C'] ?? '',
                'F' => $row['D'] ?? '', 'G' => $row['E'] ?? '',
                'H' => $households[$number]['purok'], 'I' => $row['G'] ?? '',
                'J' => $row['H'] ?? '', 'K' => $row['I'] ?? '', 'L' => $row['J'] ?? '',
                'M' => $row['K'] ?? '', 'N' => $row['L'] ?? '', 'O' => $row['M'] ?? '',
                'P' => $row['N'] ?? '', 'Q' => $row['O'] ?? '',
                'source_address' => $row['F'] ?? '',
            ];
            $first = false;
            if (($row['_red'] ?? false) || strtoupper(trim($row['P'] ?? '')) === 'DECEASED') {
                $deceased[$position] = [
                    'A' => $number, 'B' => $row['A'], 'C' => $row['B'],
                    'D' => $row['C'] ?? '', 'E' => $row['D'] ?? '', 'F' => $row['E'] ?? '',
                    'G' => $households[$number]['purok'], 'H' => $row['G'] ?? '', 'I' => $row['H'] ?? '',
                    'J' => $row['I'] ?? '', 'K' => $row['J'] ?? '', 'L' => $row['K'] ?? '',
                    'M' => $row['L'] ?? '', 'N' => $row['M'] ?? '', 'O' => $row['N'] ?? '',
                    'P' => '', 'source_remarks' => ($row['O'] ?? '').' [Marked deceased in Sheet1]',
                ];
                unset($active[$position]);
            }
        }
        return ['CONSOLIDATED RBI' => $active, 'DECEASED' => $deceased, 'NEW' => [], 'households' => $households];
    }
}
