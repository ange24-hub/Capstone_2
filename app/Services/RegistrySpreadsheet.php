<?php

namespace App\Services;

use App\Models\Barangay;
use App\Support\RegistryRemarks;
use Illuminate\Support\Collection;
use RuntimeException;
use ZipArchive;

class RegistrySpreadsheet
{
    private const COLUMNS = [8, 10, 17, 18, 17, 11, 20, 15, 20, 14, 7, 8, 14, 17, 16, 19, 18, 15];

    public function build(Collection $residents, Barangay $barangay, string $title): string
    {
        $directory = storage_path('framework/cache');
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('The application cache directory is not writable.');
        }
        $path = $directory.DIRECTORY_SEPARATOR.'registry-xlsx-'.bin2hex(random_bytes(16)).'.tmp';

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the registry spreadsheet.');
            }

            try {
                $zip->addFromString('[Content_Types].xml', $this->xml('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>'));
                $zip->addFromString('_rels/.rels', $this->xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'));
                $zip->addFromString('xl/workbook.xml', $this->xml('<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Resident Registry" sheetId="1" r:id="rId1"/></sheets></workbook>'));
                $zip->addFromString('xl/_rels/workbook.xml.rels', $this->xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>'));
                $zip->addFromString('xl/styles.xml', $this->styles());
                $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($residents, $barangay, $title));
            } finally {
                $zip->close();
            }

            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Could not read the registry spreadsheet.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function sheet(Collection $residents, Barangay $barangay, string $title): string
    {
        $header = (new SpreadsheetReportHeader)->build(18, $title, strtoupper($barangay->name), '', now()->format('m/d/Y'),
            ['title' => 1, 'label' => 3]);
        $rows = [...$header['rows'],
            $this->row(9, ['HH NO.', 'FAMILY NO.', 'NAME', '', '', '', 'RELATIONSHIP TO HH HEAD', 'PUROK / SITIO', 'PLACE OF BIRTH', 'DATE OF BIRTH', 'AGE', 'SEX (M/F)', 'CIVIL STATUS', 'SCHOOL LEVEL', 'RELIGION', 'OCCUPATION', 'REMARKS', 'ETHNICITY'], 4, 34),
            '<row r="10" ht="34" customHeight="1">'.$this->cell('C10', 'LAST NAME', 4).$this->cell('D10', 'FIRST NAME', 4).$this->cell('E10', 'MIDDLE NAME', 4).$this->cell('F10', 'QUALIFIER', 4).'</row>',
        ];

        $previousHousehold = null;
        foreach ($residents as $index => $resident) {
            $start = $previousHousehold !== $resident->household_id;
            $style = $start ? ($index % 2 ? 8 : 7) : ($index % 2 ? 6 : 5);
            $rows[] = $this->row($index + 11, [
                $resident->household?->household_number, $resident->family_number,
                $resident->last_name, $resident->first_name, $resident->middle_name, $resident->suffix,
                $resident->relationship_to_head, $resident->household?->purok, $resident->birth_place,
                $resident->birth_date?->format('m-d-y'), $resident->recorded_age,
                ['Male' => 'M', 'Female' => 'F'][$resident->sex] ?? $resident->sex,
                $resident->civil_status, $resident->education_level, $resident->religion,
                $resident->occupation, RegistryRemarks::display($resident->remarks), $resident->ethnicity,
            ], $style, 21);
            $previousHousehold = $resident->household_id;
        }

        if ($residents->isEmpty()) {
            $rows[] = $this->row(11, ['No matching active residents.'], 5, 25);
        }

        $totalRow = max(11, $residents->count() + 10) + 2;
        $rows[] = $this->row($totalRow, ['HOUSEHOLDS: '.$residents->pluck('household_id')->unique()->count().'    RESIDENTS: '.$residents->count()], 3, 20);
        $merges = [...$header['merges'], 'C9:F9', 'A'.$totalRow.':R'.$totalRow];
        foreach (['A', 'B', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'] as $column) {
            $merges[] = $column.'9:'.$column.'10';
        }
        if ($residents->isEmpty()) {
            $merges[] = 'A11:R11';
        }

        $columns = '';
        foreach (self::COLUMNS as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $mergeXml = implode('', array_map(fn (string $range) => '<mergeCell ref="'.$range.'"/>', $merges));

        return $this->xml('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="10" topLeftCell="A11" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$columns.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells>'
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.3" bottom="0.3" header="0.15" footer="0.15"/>'
            // A supported legal-paper preset avoids Excel shrinking custom-size reports incorrectly.
            .'<pageSetup paperSize="5" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>');
    }

    private function row(int $number, array $values, int $style, int $height): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $cells .= $this->cell($this->column($index + 1).$number, (string) ($value ?? ''), $style);
        }

        return '<row r="'.$number.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
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

    private function styles(): string
    {
        return $this->xml('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4"><font><sz val="11"/><name val="Times New Roman"/><color rgb="FF000000"/></font><font><b/><sz val="11"/><name val="Times New Roman"/><color rgb="FF000000"/></font><font><b/><sz val="11"/><name val="Times New Roman"/><color rgb="FF000000"/></font><font><sz val="11"/><name val="Times New Roman"/><color rgb="FF000000"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border><border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="medium"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs></styleSheet>');
    }

    private function xml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$body;
    }
}
