<?php

namespace App\Services;

use App\Models\BarangayRbiUpdate;
use App\Support\HouseholdRbi;
use App\Support\RegistryRemarks;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class RbiSpreadsheet
{
    private const STYLES = ['meta' => 0, 'title' => 1, 'context' => 2, 'heading' => 3, 'text' => 4, 'center' => 5, 'name' => 6];

    /** The browser preview and download use exactly the same cells. */
    public function layout(BarangayRbiUpdate $report, array $pages): array
    {
        $rows = [];
        $breaks = [];
        $cell = fn (int $column, int $span, string $value, string $style = 'meta', int $rowspan = 1): array => compact('column', 'span', 'value', 'style', 'rowspan');
        $add = function (array $cells = [], int $height = 20) use (&$rows): void {
            $rows[] = ['number' => count($rows) + 1, 'height' => $height, 'cells' => $cells];
        };

        foreach ($pages as $pageIndex => $page) {
            if ($pageIndex > 0) {
                $breaks[] = count($rows);
            }
            $add([$cell(1, 15, 'RECORD OF BARANGAY INHABITANTS BY HOUSEHOLD', 'title')], 30);
            $add([], 10);
            foreach ([['A. REGION:', 'VIII'], ['B. PROVINCE:', 'SOUTHERN LEYTE'],
                ['C. CITY/MUNICIPALITY:', 'TOMAS OPPUS'], ['D. BARANGAY:', $report->barangay_name ?? '']] as [$label, $value]) {
                $add([$cell(1, 15, $label.' '.$value)]);
            }
            $add([$cell(1, 8, 'E. HOUSEHOLD NO.: '.($page['household_number'] ?? '')),
                $cell(9, 7, 'DATE ACCOMPLISHED: '.$report->accomplishedDate()->format('m/d/Y'))]);
            $add([$cell(1, 15, 'Household head: '.$page['household_head'].' | For the month of '
                .strtoupper($report->reporting_month?->format('F Y') ?? 'NOT SET').($page['continued'] ? ' | CONTINUED' : ''), 'context')], 24);
            $add([], 10);

            $headers = [$cell(1, 4, 'NAME', 'heading')];
            foreach (array_slice(array_values(HouseholdRbi::fields()), 4) as $index => $label) {
                $headers[] = $cell($index + 5, 1, $label, 'heading', 2);
            }
            $add($headers, 32);
            $add(array_map(fn ($label, $index) => $cell($index + 1, 1, $label, 'heading'),
                array_slice(array_values(HouseholdRbi::fields()), 0, 4), range(0, 3)), 32);

            foreach (array_pad($page['members'], 7, []) as $member) {
                $cells = [];
                foreach (array_keys(HouseholdRbi::fields()) as $index => $field) {
                    $value = (string) ($member[$field] ?? '');
                    $value = match ($field) {
                        'birth_date' => $this->date($value, 'm-d-y'),
                        'sex' => ['Male' => 'M', 'Female' => 'F'][$value] ?? $value,
                        'remarks' => RegistryRemarks::display($value),
                        default => $value,
                    };
                    $cells[] = $cell($index + 1, 1, $value, 'text');
                }
                $add($cells, $member === [] ? 16 : 38);
            }
            $add([], 10);
            $add([$cell(1, 11, 'B. Name of Deceased Registered Brgy. Inhabitant', 'heading'),
                $cell(12, 4, 'Date of Death', 'heading')], 24);
            foreach (array_pad($page['deceased'], 3, []) as $death) {
                $add([$cell(1, 11, (string) ($death['deceased_name'] ?? ''), 'text'),
                    $cell(12, 4, $this->date((string) ($death['death_date'] ?? '')), 'text')], 22);
            }
            $add([], 10);
            if ($page['show_signatures']) {
                $add([$cell(1, 5, 'Prepared by:'), $cell(6, 5, 'Certified Correct:'), $cell(11, 5, 'Verified by:')]);
                $signatureCells = [];
                foreach (['secretary' => 'prepared_signature_path', 'certified' => 'certified_signature_path', 'captain' => 'attested_signature_path'] as $type => $field) {
                    $signatureCells[] = $cell(count($signatureCells) * 5 + 1, 5, '', 'center')
                        + ['signature' => $report->$field ? $type : null];
                }
                $add($signatureCells, 36);
                $add([$cell(1, 5, $report->prepared_by ?? '', 'name'), $cell(6, 5, $report->certified_by ?? '', 'name'),
                    $cell(11, 5, $report->attested_by ?? '', 'name')]);
                $add([$cell(1, 5, 'BHW / Encoder', 'center'), $cell(6, 5, 'Barangay Secretary', 'center'),
                    $cell(11, 5, 'Barangay Captain / Punong Barangay', 'center')], 24);
            }
            $add([], 12);
        }

        return ['rows' => $rows, 'breaks' => $breaks,
            'columns' => array_map(fn (int $width): float => $width * 2.5, HouseholdRbi::widths())];
    }

    public function build(BarangayRbiUpdate $report, array $pages): string
    {
        $layout = $this->layout($report, $pages);
        $media = [];
        foreach (['secretary' => 'prepared_signature_path', 'certified' => 'certified_signature_path', 'captain' => 'attested_signature_path'] as $type => $field) {
            $path = $report->$field;
            if (! $path || ! Storage::exists($path)) {
                continue;
            }
            $bytes = Storage::get($path);
            $size = @getimagesizefromstring($bytes);
            $extension = match ($size[2] ?? null) {
                IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', default => null
            };
            if ($extension) {
                $media[$type] = ['bytes' => $bytes, 'filename' => $type.'.'.$extension, 'width' => $size[0], 'height' => $size[1]];
            }
        }
        // Keep exports independent of the web server's system temporary directory.
        $directory = storage_path('framework/cache');
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('The application cache directory is not writable.');
        }
        $path = $directory.DIRECTORY_SEPARATOR.'rbi-xlsx-'.bin2hex(random_bytes(16)).'.tmp';

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the RBI spreadsheet.');
            }
            try {
                $zip->addFromString('[Content_Types].xml', $this->xml('<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
                    .'<Default Extension="png" ContentType="image/png"/><Default Extension="jpeg" ContentType="image/jpeg"/>'
                    .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                    .($media ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '').'</Types>'));
                $zip->addFromString('_rels/.rels', $this->relationships([['rId1', 'officeDocument', 'xl/workbook.xml']]));
                $zip->addFromString('xl/workbook.xml', $this->xml('<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    .'<sheets><sheet name="RBI Monthly Report" sheetId="1" r:id="rId1"/></sheets>'
                    .'<definedNames><definedName name="_xlnm.Print_Area" localSheetId="0">\'RBI Monthly Report\'!$A$1:$O$'.count($layout['rows']).'</definedName></definedNames></workbook>'));
                $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relationships([['rId1', 'worksheet', 'worksheets/sheet1.xml'], ['rId2', 'styles', 'styles.xml']]));
                $zip->addFromString('xl/styles.xml', $this->styles());
                $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($layout, (bool) $media));
                if ($media) {
                    $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->relationships([['rId1', 'drawing', '../drawings/drawing1.xml']]));
                    $zip->addFromString('xl/drawings/drawing1.xml', $this->drawing($layout, $media));
                    $relations = [];
                    foreach ($media as $type => $item) {
                        $relations[] = [$type, 'image', '../media/'.$item['filename']];
                        $zip->addFromString('xl/media/'.$item['filename'], $item['bytes']);
                    }
                    $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->relationships($relations));
                }
            } finally {
                $zip->close();
            }
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('Could not read the RBI spreadsheet.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function sheet(array $layout, bool $hasImages): string
    {
        $rows = '';
        $merges = [];
        $pending = [];
        foreach ($layout['rows'] as $row) {
            $number = $row['number'];
            $cells = $pending[$number] ?? [];
            foreach ($row['cells'] as $cell) {
                $start = $cell['column'];
                $end = $start + $cell['span'] - 1;
                $lastRow = $number + $cell['rowspan'] - 1;
                $style = self::STYLES[$cell['style']];
                for ($column = $start; $column <= $end; $column++) {
                    $reference = $this->column($column).$number;
                    $cells[$column] = '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
                        .$this->escape($column === $start ? $cell['value'] : '').'</t></is></c>';
                    for ($next = $number + 1; $next <= $lastRow; $next++) {
                        $pending[$next][$column] = '<c r="'.$this->column($column).$next.'" s="'.$style.'"/>';
                    }
                }
                if ($cell['span'] > 1 || $cell['rowspan'] > 1) {
                    $merges[] = '<mergeCell ref="'.$this->column($start).$number.':'.$this->column($end).$lastRow.'"/>';
                }
            }
            ksort($cells);
            $rows .= '<row r="'.$number.'" ht="'.$row['height'].'" customHeight="1">'.implode('', $cells).'</row>';
        }
        $columns = '';
        foreach ($layout['columns'] as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $breaks = '';
        foreach ($layout['breaks'] as $number) {
            $breaks .= '<brk id="'.$number.'" min="0" max="14" man="1"/>';
        }

        return $this->xml('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><dimension ref="A1:O'.count($layout['rows']).'"/>'
            .'<sheetViews><sheetView workbookViewId="0" zoomScale="80"><pane ySplit="11" topLeftCell="A12" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="20"/><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData>'
            .'<mergeCells count="'.count($merges).'">'.implode('', $merges).'</mergeCells>'
            .'<printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.3" bottom="0.3" header="0.1" footer="0.1"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .($breaks ? '<rowBreaks count="'.count($layout['breaks']).'" manualBreakCount="'.count($layout['breaks']).'">'.$breaks.'</rowBreaks>' : '')
            .($hasImages ? '<drawing r:id="rId1"/>' : '').'</worksheet>');
    }

    private function drawing(array $layout, array $media): string
    {
        $anchors = '';
        $id = 0;
        foreach ($layout['rows'] as $row) {
            foreach ($row['cells'] as $cell) {
                $type = $cell['signature'] ?? null;
                if (! $type || ! isset($media[$type])) {
                    continue;
                }
                $id++;
                $item = $media[$type];
                $scale = min(180 / $item['width'], 44 / $item['height']);
                $width = (int) round($item['width'] * $scale * 9525);
                $height = (int) round($item['height'] * $scale * 9525);
                $anchors .= '<xdr:oneCellAnchor><xdr:from><xdr:col>'.($cell['column'] - 1).'</xdr:col><xdr:colOff>47625</xdr:colOff><xdr:row>'.($row['number'] - 1).'</xdr:row><xdr:rowOff>19050</xdr:rowOff></xdr:from>'
                    .'<xdr:ext cx="'.$width.'" cy="'.$height.'"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="'.$id.'" name="'.$type.' signature '.$id.'"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                    .'<xdr:blipFill><a:blip r:embed="'.$type.'"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                    .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
            }
        }

        return $this->xml('<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.$anchors.'</xdr:wsDr>');
    }

    private function styles(): string
    {
        $formats = '';
        foreach (self::STYLES as $style => $index) {
            $font = in_array($style, ['title', 'heading', 'name'], true) ? ($style === 'title' ? 2 : 1) : 0;
            $border = in_array($style, ['heading', 'text'], true) ? 1 : 0;
            $alignment = in_array($style, ['title', 'heading', 'center', 'name'], true) ? 'center' : 'left';
            $formats .= '<xf numFmtId="0" fontId="'.$font.'" fillId="0" borderId="'.$border.'" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="'.$alignment.'" vertical="center" wrapText="1"/></xf>';
        }

        return $this->xml('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="11"/><name val="Times New Roman"/></font><font><b/><sz val="11"/><name val="Times New Roman"/></font><font><b/><sz val="15"/><name val="Times New Roman"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7">'.$formats.'</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    }

    private function relationships(array $items): string
    {
        $xml = '';
        foreach ($items as [$id, $type, $target]) {
            $xml .= '<Relationship Id="'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'.$type.'" Target="'.$target.'"/>';
        }

        return $this->xml('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$xml.'</Relationships>');
    }

    private function xml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.$body;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function date(string $value, string $format = 'm/d/y'): string
    {
        $timestamp = $value !== '' ? strtotime($value) : false;

        return $timestamp === false ? $value : date($format, $timestamp);
    }

    private function column(int $number): string
    {
        return chr(64 + $number);
    }
}
