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
        $rows = [
            $this->row(1, [$title], 1, 28),
            $this->row(2, ['Barangay '.$barangay->name.' · As of '.now()->format('d F Y')], 2, 21),
            $this->row(4, ['A. REGION: VIII', '', '', '', '', '', '', '', '', 'D. BARANGAY: '.strtoupper($barangay->name)], 3, 20),
            $this->row(5, ['B. PROVINCE: SOUTHERN LEYTE', '', '', '', '', '', '', '', '', 'HOUSEHOLDS: '.$residents->pluck('household_id')->unique()->count()], 3, 20),
            $this->row(6, ['C. CITY/MUNICIPALITY: TOMAS OPPUS', '', '', '', '', '', '', '', '', 'RESIDENTS: '.$residents->count()], 3, 20),
            $this->row(8, ['HH NO.', 'FAMILY NO.', 'NAME', '', '', '', 'RELATIONSHIP TO HH HEAD', 'PUROK / SITIO', 'PLACE OF BIRTH', 'DATE OF BIRTH', 'AGE', 'SEX (M/F)', 'CIVIL STATUS', 'SCHOOL LEVEL', 'RELIGION', 'OCCUPATION', 'REMARKS', 'ETHNICITY'], 4, 34),
            '<row r="9" ht="25" customHeight="1">'.$this->cell('C9', 'LAST NAME', 4).$this->cell('D9', 'FIRST NAME', 4).$this->cell('E9', 'MIDDLE NAME', 4).$this->cell('F9', 'QUALIFIER', 4).'</row>',
        ];

        $previousHousehold = null;
        foreach ($residents as $index => $resident) {
            $start = $previousHousehold !== $resident->household_id;
            $style = $start ? ($index % 2 ? 8 : 7) : ($index % 2 ? 6 : 5);
            $rows[] = $this->row($index + 10, [
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
            $rows[] = $this->row(10, ['No matching active residents.'], 5, 25);
        }

        $merges = ['A1:R1', 'A2:R2', 'C8:F8'];
        foreach ([4, 5, 6] as $row) {
            $merges[] = 'A'.$row.':I'.$row;
            $merges[] = 'J'.$row.':R'.$row;
        }
        foreach (['A', 'B', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'] as $column) {
            $merges[] = $column.'8:'.$column.'9';
        }
        if ($residents->isEmpty()) {
            $merges[] = 'A10:R10';
        }

        $columns = '';
        foreach (self::COLUMNS as $index => $width) {
            $column = $index + 1;
            $columns .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }
        $mergeXml = implode('', array_map(fn (string $range) => '<mergeCell ref="'.$range.'"/>', $merges));

        return $this->xml('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="9" topLeftCell="A10" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/><cols>'.$columns.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<mergeCells count="'.count($merges).'">'.$mergeXml.'</mergeCells>'
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.3" bottom="0.3" header="0.15" footer="0.15"/>'
            .'<pageSetup paperSize="256" paperWidth="13in" paperHeight="8.5in" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
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
            .'<fonts count="3"><font><sz val="9"/><name val="Arial"/></font><font><b/><sz val="14"/><color rgb="FF203957"/><name val="Arial"/></font><font><b/><sz val="8"/><color rgb="FF203957"/><name val="Arial"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEAF0F4"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF7F9FA"/></patternFill></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="medium"/><bottom style="thin"/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="9"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="2" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="2" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'</cellXfs></styleSheet>');
    }

    private function xml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$body;
    }
}
