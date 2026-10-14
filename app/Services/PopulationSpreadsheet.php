<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class PopulationSpreadsheet
{
    public function build(array $report, string $section, string $title): string
    {
        $sheets = $this->sheets($report, $section, $title);
        $path = tempnam(sys_get_temp_dir(), 'population-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Could not create the population spreadsheet.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the population spreadsheet.');
            }
            try {
                $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
                $zip->addFromString('_rels/.rels', $this->xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'));
                $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
                $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
                $zip->addFromString('xl/styles.xml', $this->styles());
                foreach (array_values($sheets) as $index => $sheet) {
                    $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheet($sheet, $report));
                }
            } finally {
                $zip->close();
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Could not read the population spreadsheet.');
            }

            return $contents;
        } finally {
            unlink($path);
        }
    }

    private function sheets(array $report, string $section, string $title): array
    {
        $summary = [
            'Summary' => ['title' => 'Population, families and households', 'headings' => ['Indicator', 'Count'], 'rows' => [
                ['Total registered residents', $report['totalRecords']],
                ['Families', $report['totalFamilies'] ?: 'Not yet identified'],
                ['Households', $report['totalHouseholds'] ?: 'Not yet encoded'],
                ['Senior citizens', $report['totalSeniors']],
                ['Persons with disability', $report['totalPwd']],
            ]],
            'By Sex' => ['title' => 'Population by sex', 'headings' => ['Category', 'Residents'],
                'rows' => [...collect($report['sex'])->map(fn ($count, $label) => [$label, $count])->values()->all(), ['Total', $report['totalRecords']]]],
            'By Age' => ['title' => 'Population by age', 'headings' => ['Age group (years)', 'Residents'],
                'rows' => [...collect($report['ages'])->map(fn ($count, $label) => [$label, $count])->values()->all(), ['Total', $report['totalRecords']]]],
            'By Barangay' => ['title' => 'Summary by barangay',
                'headings' => ['Barangay', 'Residents', 'Male', 'Female', 'Families', 'Households', 'SC', 'PWD'],
                'rows' => $report['coverage']->map(fn ($area) => $area['records'] === 0
                    ? [$area['name'], 'Not yet encoded', '', '', '', '', '', '']
                    : [$area['name'], $area['records'], $area['sex']['Male'], $area['sex']['Female'], $area['families'], $area['households'], $area['seniors'], $area['pwd']])->values()->push([
                        'Total recorded', $report['totalRecords'], $report['sex']['Male'], $report['sex']['Female'],
                        $report['totalFamilies'], $report['totalHouseholds'], $report['totalSeniors'], $report['totalPwd'],
                    ])->all()],
            'Age by Barangay' => ['title' => 'Age groups by barangay',
                'headings' => array_merge(['Barangay'], array_map(fn ($label) => $label === 'Unknown / invalid birth date' ? 'Age unknown' : $label, array_keys($report['ages'])), ['Sex unspecified']),
                'rows' => $report['coverage']->map(fn ($area) => $area['records'] === 0
                    ? array_pad([$area['name'], 'Not yet encoded'], count($report['ages']) + 2, '')
                    : array_merge([$area['name']], array_values($area['ages']), [$area['sex']['Not specified / other']]))
                    ->values()->push(array_merge(['Total recorded'], array_values($report['ages']), [$report['sex']['Not specified / other']]))->all()],
        ];

        if ($section === 'summary') {
            return $report['totalBarangays'] > 1 ? $summary : array_slice($summary, 0, 3, true);
        }

        $key = ['sex' => 'By Sex', 'ages' => 'By Age', 'coverage' => 'By Barangay', 'area-ages' => 'Age by Barangay'][$section] ?? null;
        if ($key !== null) {
            if ($report['totalBarangays'] > 1 && $section === 'sex') return ['By Sex' => $summary['By Sex'], 'By Barangay' => $summary['By Barangay']];
            if ($report['totalBarangays'] > 1 && $section === 'ages') return ['By Age' => $summary['By Age'], 'Age by Barangay' => $summary['Age by Barangay']];
            return [$key => $summary[$key]];
        }

        $grouped = in_array($section, ['families', 'households'], true);
        $rows = [];
        $groupRows = [];
        $lastBarangay = null;
        foreach ($report['detailGroups'] as $group) {
            if ($report['totalBarangays'] > 1 && $group['barangay'] !== $lastBarangay) {
                $groupRows[] = count($rows);
                $rows[] = array_pad(['Barangay '.$group['barangay']], 6, '');
                $lastBarangay = $group['barangay'];
            }
            foreach ($group['members'] as $member) {
                $rows[] = [$member['barangay'], $grouped ? $group['title'] : '', $member['name'] ?: 'Name not encoded',
                    $member['age'] ?? 'Unknown', $member['household'] ?: 'Not encoded', $member['birth_date'] ?? 'Not encoded'];
            }
            if ($grouped && $group['members']->isEmpty()) {
                $rows[] = [$group['barangay'], $group['title'], 'Name not encoded', 'Unknown',
                    $group['household_number'] ?: 'Not encoded', 'Not encoded'];
            }
        }

        return [$title => ['title' => $title, 'headings' => ['Barangay', $grouped ? 'Group' : 'Category', 'Name', 'Age', 'Household number', 'Birthdate'], 'rows' => $rows, 'groupRows' => $groupRows]];
    }

    private function sheet(array $sheet, array $report): string
    {
        $logicalColumns = count($sheet['headings']);
        // Subdivide narrow tables so the document header can retain its boxed layout.
        $span = max(1, (int) ceil(12 / $logicalColumns));
        $columnCount = $logicalColumns * $span;
        $barangay = str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays';
        $header = (new SpreadsheetReportHeader)->build($columnCount, $sheet['title'], $barangay, '',
            $report['generatedAt']->format('m/d/Y'), ['title' => 1, 'label' => 8, 'box' => 9, 'legend' => 10]);
        $rows = [...$header['rows'], $this->row(9, $sheet['headings'], 3, $span)];
        foreach ($sheet['rows'] as $index => $values) {
            $rows[] = $this->row($index + 10, $values, in_array($index, $sheet['groupRows'] ?? [], true) ? 6 : 4, $span);
        }
        if ($sheet['rows'] === []) {
            $rows[] = $this->row(10, ['No entries available in the selected scope.'], 4, $span);
        }
        $lastColumn = $this->column($columnCount);
        $lastRow = max(10, count($sheet['rows']) + 9);
        $merges = $header['merges'];
        if ($span > 1) {
            for ($row = 9; $row <= $lastRow; $row++) {
                for ($column = 0; $column < $logicalColumns; $column++) {
                    $merges[] = $this->column($column * $span + 1).$row.':'.$this->column(($column + 1) * $span).$row;
                }
            }
        }
        $mergeXml = implode('', array_map(fn ($range) => '<mergeCell ref="'.$range.'"/>', $merges));
        $logicalWidths = array_map(fn ($heading, $index) => $index === 0 ? 32 : ($heading === 'Name' ? 34 : 20), $sheet['headings'], array_keys($sheet['headings']));
        $scale = max(1, 120 / array_sum($logicalWidths));
        $widths = '';
        foreach ($logicalWidths as $index => $width) {
            $widths .= '<col min="'.($index * $span + 1).'" max="'.(($index + 1) * $span).'" width="'.round($width * $scale / $span, 2).'" customWidth="1"/>';
        }

        return $this->xml('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="17"/><cols>'.$widths.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<autoFilter ref="A9:'.$lastColumn.$lastRow.'"/>'
            .'<mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells>'
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>');
    }

    private function row(int $number, array $values, int $style, int $span = 1): string
    {
        $cells = '';
        foreach (array_values($values) as $index => $value) {
            $reference = $this->column($index * $span + 1).$number;
            $cells .= is_int($value)
                ? '<c r="'.$reference.'" s="5"><v>'.$value.'</v></c>'
                : '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->escape((string) $value).'</t></is></c>';
            for ($offset = 1; $offset < $span; $offset++) {
                $cells .= '<c r="'.$this->column($index * $span + $offset + 1).$number.'" s="'.(is_int($value) ? 5 : $style).'"/>';
            }
        }

        return '<row r="'.$number.'"'.($style === 7 ? ' ht="30" customHeight="1"' : '').'>'.$cells.'</row>';
    }

    private function contentTypes(int $count): string
    {
        $sheets = '';
        for ($index = 1; $index <= $count; $index++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$index.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $this->xml('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets.'</Types>');
    }

    private function workbook(array $sheets): string
    {
        $entries = '';
        foreach (array_keys($sheets) as $index => $name) {
            $entries .= '<sheet name="'.$this->escape($name).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return $this->xml('<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$entries.'</sheets></workbook>');
    }

    private function workbookRelationships(int $count): string
    {
        $entries = '';
        for ($index = 1; $index <= $count; $index++) {
            $entries .= '<Relationship Id="rId'.$index.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$index.'.xml"/>';
        }
        $entries .= '<Relationship Id="rId'.($count + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return $this->xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$entries.'</Relationships>');
    }

    private function styles(): string
    {
        return $this->xml('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FF172536"/><sz val="11"/><name val="Calibri"/></font><font><sz val="6"/><name val="Calibri"/></font><font><sz val="8"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs></styleSheet>');
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

    private function escape(string $value): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($clean ?? '', ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$body;
    }
}
