<?php

namespace App\Services;

class SpreadsheetReportHeader
{
    /** Build the same boxed location header used by PDF and printed reports. */
    public function build(int $columns, string $title, string $barangay, string $household, string $date, array $styles): array
    {
        $labelEnd = max(2, (int) ceil($columns * .16));
        $valueEnd = max($labelEnd + 2, (int) ceil($columns * .32));
        $codeEnd = max($valueEnd + 1, (int) floor($columns * .42));
        $legend = $codeEnd + 1;
        $dateStart = max($legend + 2, (int) floor($columns * .60) + 1);
        $dateValue = max($dateStart + 1, (int) floor($columns * .80) + 1);
        $cells = [];
        $merges = [];
        $add = function (int $row, int $start, int $end, string $value, int $style) use (&$cells, &$merges): void {
            for ($column = $start; $column <= $end; $column++) {
                $cells[$row][$column] = $this->cell($this->column($column).$row, $column === $start ? $value : '', $style);
            }
            if ($end > $start) $merges[] = $this->column($start).$row.':'.$this->column($end).$row;
        };
        $add(1, 1, $columns, $title, $styles['title']);
        $fields = [['A. REGION:', 'VIII'], ['B. PROVINCE:', 'SOUTHERN LEYTE'],
            ['C. CITY/MUNICIPALITY:', 'TOMAS OPPUS'], ['D. BARANGAY:', $barangay], ['E. HOUSEHOLD NO.:', $household]];
        foreach ($fields as $index => [$label, $value]) {
            $row = $index + 3;
            $add($row, 1, $labelEnd, $label, $styles['label']);
            $add($row, $labelEnd + 1, $valueEnd, $value, $styles['box']);
            if ($index < 4) {
                $add($row, $valueEnd + 1, $codeEnd, '', $styles['box']);
                $add($row, $legend, $legend, $index === 0 ? "PSGC\nC\nO\nD\nE\nS" : '', $styles['legend']);
            }
        }
        $merges[] = $this->column($legend).'3:'.$this->column($legend).'6';
        $add(7, $dateStart, $dateValue - 1, 'DATE ACCOMPLISHED:', $styles['label']);
        $add(7, $dateValue, $columns, $date, $styles['box']);
        $rows = [];
        for ($row = 1; $row <= 8; $row++) {
            $rowCells = $cells[$row] ?? [];
            ksort($rowCells);
            $rows[] = '<row r="'.$row.'" ht="'.($row === 1 ? 24 : 18).'" customHeight="1">'.implode('', $rowCells).'</row>';
        }

        return ['rows' => $rows, 'merges' => $merges];
    }

    private function cell(string $reference, string $value, int $style): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .htmlspecialchars($clean ?? '', ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</t></is></c>';
    }

    private function column(int $number): string
    {
        $label = '';
        while ($number > 0) {
            $number--;
            $label = chr(65 + $number % 26).$label;
            $number = intdiv($number, 26);
        }
        return $label;
    }
}
