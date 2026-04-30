<?php

namespace App\Http\Controllers;

use App\Models\BarangayRbiUpdate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BarangayRbiUpdateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:255'],
            'reporting_month' => ['required', 'date'],
            'as_of_date' => ['nullable', 'date'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'attested_by' => ['nullable', 'string', 'max:255'],
            'source_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $file = $request->file('source_file');
        $path = $file->store('barangay-rbi-updates');

        BarangayRbiUpdate::create([
            'barangay_user_id' => $request->user()->id,
            'barangay_name' => $validated['barangay_name'],
            'reporting_month' => $validated['reporting_month'],
            'as_of_date' => $validated['as_of_date'] ?? null,
            'prepared_by' => $validated['prepared_by'] ?: $request->user()->name,
            'attested_by' => $validated['attested_by'] ?? null,
            'status' => BarangayRbiUpdate::STATUS_DRAFT,
            'rows' => $this->initialRows($file),
            'source_file_path' => $path,
            'source_file_name' => $file->getClientOriginalName(),
        ]);

        return back()->with('status', 'RBI update uploaded as a draft. Review the entries before submitting to Municipal LGU.');
    }

    public function update(Request $request, BarangayRbiUpdate $rbiUpdate): RedirectResponse
    {
        $this->authorizeBarangayDraft($request, $rbiUpdate);

        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:255'],
            'reporting_month' => ['required', 'date'],
            'as_of_date' => ['nullable', 'date'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'attested_by' => ['nullable', 'string', 'max:255'],
            'rows' => ['nullable', 'array'],
            'rows.*.hih_no' => ['nullable', 'string', 'max:50'],
            'rows.*.household_head' => ['nullable', 'string', 'max:255'],
            'rows.*.relationship' => ['nullable', 'string', 'max:255'],
            'rows.*.inhabitant_name' => ['nullable', 'string', 'max:255'],
            'rows.*.sex' => ['nullable', Rule::in(['', 'Male', 'Female'])],
            'rows.*.birth_date' => ['nullable', 'date'],
            'rows.*.birth_place' => ['nullable', 'string', 'max:255'],
            'rows.*.civil_status' => ['nullable', 'string', 'max:100'],
            'rows.*.religion' => ['nullable', 'string', 'max:100'],
            'rows.*.occupation' => ['nullable', 'string', 'max:255'],
            'rows.*.year_completed' => ['nullable', 'string', 'max:100'],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = $this->cleanRows($validated['rows'] ?? []);

        if ($request->boolean('submit_to_municipal') && empty($rows)) {
            return back()
                ->withErrors(['rows' => 'Enter at least one RBI row in the table, then submit to Municipal LGU.'])
                ->withInput();
        }

        $rbiUpdate->update([
            'barangay_name' => $validated['barangay_name'],
            'reporting_month' => $validated['reporting_month'],
            'as_of_date' => $validated['as_of_date'] ?? null,
            'prepared_by' => $validated['prepared_by'] ?: $request->user()->name,
            'attested_by' => $validated['attested_by'] ?? null,
            'rows' => $rows,
            'status' => $request->boolean('submit_to_municipal')
                ? BarangayRbiUpdate::STATUS_SUBMITTED
                : BarangayRbiUpdate::STATUS_DRAFT,
            'submitted_at' => $request->boolean('submit_to_municipal') ? now() : null,
        ]);

        return back()->with('status', $request->boolean('submit_to_municipal')
            ? 'RBI update saved and submitted to Municipal LGU.'
            : 'RBI update draft saved.');
    }

    public function submit(Request $request, BarangayRbiUpdate $rbiUpdate): RedirectResponse
    {
        $this->authorizeBarangayDraft($request, $rbiUpdate);

        if (empty($this->cleanRows($rbiUpdate->rows ?? []))) {
            return back()->withErrors(['rows' => 'Add at least one RBI entry before submitting to Municipal LGU.']);
        }

        $rbiUpdate->update([
            'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'RBI update submitted to Municipal LGU.');
    }

    public function download(Request $request, BarangayRbiUpdate $rbiUpdate): StreamedResponse
    {
        $this->authorizeView($request, $rbiUpdate);
        abort_unless($rbiUpdate->source_file_path && Storage::exists($rbiUpdate->source_file_path), 404);

        return Storage::download($rbiUpdate->source_file_path, $rbiUpdate->source_file_name);
    }

    public function show(Request $request, BarangayRbiUpdate $rbiUpdate): View
    {
        $this->authorizeView($request, $rbiUpdate);

        return view('rbi-updates.show', [
            'rbiUpdate' => $rbiUpdate->load('barangayUser'),
            'rbiRowFields' => BarangayRbiUpdate::rowFields(),
        ]);
    }

    public function exportEdited(Request $request, BarangayRbiUpdate $rbiUpdate): StreamedResponse
    {
        $this->authorizeView($request, $rbiUpdate);

        $barangay = str($rbiUpdate->barangay_name ?: 'barangay')->slug('-');
        $filename = 'edited-rbi-update-'.$barangay.'-'.$rbiUpdate->id.'.xls';

        return response()->streamDownload(function () use ($rbiUpdate) {
            echo $this->buildEditedWorkbook($rbiUpdate);
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function authorizeBarangayDraft(Request $request, BarangayRbiUpdate $rbiUpdate): void
    {
        abort_unless($rbiUpdate->barangay_user_id === $request->user()->id, 403);
        abort_unless($rbiUpdate->status === BarangayRbiUpdate::STATUS_DRAFT, 403);
    }

    private function authorizeView(Request $request, BarangayRbiUpdate $rbiUpdate): void
    {
        $canViewAsMunicipal = $request->user()->hasRole(User::ROLE_MUNICIPAL_LGU)
            && $rbiUpdate->status === BarangayRbiUpdate::STATUS_SUBMITTED;

        abort_unless($canViewAsMunicipal || $rbiUpdate->barangay_user_id === $request->user()->id, 403);
    }

    private function blankRows(): array
    {
        return array_fill(0, 5, array_fill_keys(array_keys(BarangayRbiUpdate::rowFields()), ''));
    }

    private function initialRows($file): array
    {
        $rows = match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->readCsvRows($file->getRealPath()),
            'xlsx' => $this->readXlsxRows($file->getRealPath()),
            default => [],
        };

        $rows = $this->cleanRows($rows);

        return count($rows) > 0 ? $rows : $this->blankRows();
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $rows = [];
        $fields = array_keys(BarangayRbiUpdate::rowFields());

        while (($data = fgetcsv($handle)) !== false) {
            if (count(array_filter($data)) === 0 || str_contains((string) ($data[0] ?? ''), 'HIH')) {
                continue;
            }

            $rows[] = array_combine($fields, array_pad(array_slice($data, 0, count($fields)), count($fields), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (! $sheetXml) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $fields = array_keys(BarangayRbiUpdate::rowFields());
        $columns = range('A', 'L');
        $rows = [];

        foreach ($sheet->children($namespace)->sheetData->row as $sheetRow) {
            $rowNumber = (int) $sheetRow['r'];

            if ($rowNumber <= 5) {
                continue;
            }

            $values = array_fill_keys($columns, '');

            foreach ($sheetRow->children($namespace)->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);

                if (! in_array($column, $columns, true)) {
                    continue;
                }

                $rawValue = (string) ($cell->children($namespace)->v ?? '');
                $values[$column] = ((string) $cell['t'] === 's' && is_numeric($rawValue))
                    ? ($sharedStrings[(int) $rawValue] ?? '')
                    : $this->formatSpreadsheetValue($rawValue, $column);
            }

            $rows[] = array_combine($fields, array_values($values));
        }

        return $rows;
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $shared = simplexml_load_string($xml);
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($shared->children($namespace)->si as $item) {
            $text = '';

            if (isset($item->children($namespace)->t)) {
                $text .= (string) $item->children($namespace)->t;
            }

            foreach ($item->children($namespace)->r as $run) {
                $text .= (string) ($run->children($namespace)->t ?? '');
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function formatSpreadsheetValue(string $value, string $column): string
    {
        if ($column === 'F' && is_numeric($value)) {
            return gmdate('Y-m-d', (((int) $value) - 25569) * 86400);
        }

        return $value;
    }

    private function buildEditedWorkbook(BarangayRbiUpdate $rbiUpdate): string
    {
        $headers = collect(BarangayRbiUpdate::rowFields())
            ->map(fn (string $label): string => '<th>'.$this->excelEscape($label).'</th>')
            ->implode('');

        $bodyRows = collect($rbiUpdate->rows ?? [])
            ->map(function (array $row): string {
                $cells = collect(array_keys(BarangayRbiUpdate::rowFields()))
                    ->map(fn (string $field): string => '<td>'.$this->excelEscape((string) ($row[$field] ?? '')).'</td>')
                    ->implode('');

                return '<tr>'.$cells.'</tr>';
            })
            ->implode('');

        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="12" class="empty">No edited entries saved.</td></tr>';
        }

        return '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Calibri, Arial, sans-serif; color: #102f2a; }
        table { border-collapse: collapse; width: 100%; }
        .title { background: #e8f1ee; color: #102f2a; font-size: 18px; font-weight: 700; text-align: center; }
        .subtitle { background: #fff7e6; color: #102f2a; font-size: 13px; font-weight: 700; text-align: center; }
        th { background: #145c4d; color: #ffffff; font-size: 12px; font-weight: 700; text-align: center; vertical-align: middle; }
        td, th { border: 1px solid #98a2b3; padding: 8px; vertical-align: top; white-space: normal; }
        td { font-size: 12px; }
        .empty { color: #667085; text-align: center; }
        .signature-label { border: 0; color: #344054; font-size: 12px; font-weight: 700; padding-top: 28px; }
        .signature-line { border: 0; border-bottom: 1px solid #102f2a; font-size: 12px; font-weight: 700; text-align: center; }
        .signature-title { border: 0; color: #667085; font-size: 11px; text-align: center; }
        .no-border { border: 0; }
        .w-id { width: 70px; }
        .w-medium { width: 150px; }
        .w-large { width: 230px; }
    </style>
</head>
<body>
    <table>
        <colgroup>
            <col class="w-id">
            <col class="w-large">
            <col class="w-large">
            <col class="w-large">
            <col class="w-id">
            <col class="w-medium">
            <col class="w-large">
            <col class="w-medium">
            <col class="w-medium">
            <col class="w-large">
            <col class="w-medium">
            <col class="w-large">
        </colgroup>
        <tr><td colspan="12" class="title">UPDATED REGISTRY OF BARANGAY INHABITANTS</td></tr>
        <tr><td colspan="12" class="subtitle">of Barangay '.$this->excelEscape($rbiUpdate->barangay_name ?: '____________').'</td></tr>
        <tr><td colspan="12" class="subtitle">Reporting Month: '.$this->excelEscape(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'Not set').' | As of: '.$this->excelEscape(optional($rbiUpdate->as_of_date)->format('F d, Y') ?: 'Not set').'</td></tr>
        <tr>'.$headers.'</tr>
        '.$bodyRows.'
        <tr><td colspan="12" class="no-border">&nbsp;</td></tr>
        <tr>
            <td colspan="5" class="signature-label">Prepared by:</td>
            <td colspan="2" class="no-border"></td>
            <td colspan="5" class="signature-label">Attested by:</td>
        </tr>
        <tr>
            <td colspan="5" class="signature-line">'.$this->excelEscape($rbiUpdate->prepared_by ?: ($rbiUpdate->barangayUser->name ?? '')).'</td>
            <td colspan="2" class="no-border"></td>
            <td colspan="5" class="signature-line">'.$this->excelEscape($rbiUpdate->attested_by ?: '').'</td>
        </tr>
        <tr>
            <td colspan="5" class="signature-title">Barangay Personnel / RBI Encoder</td>
            <td colspan="2" class="no-border"></td>
            <td colspan="5" class="signature-title">Punong Barangay / Authorized Official</td>
        </tr>
    </table>
</body>
</html>';
    }

    private function excelEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function xlsxContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>';
    }

    private function xlsxRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';
    }

    private function xlsxWorkbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="RBI Monthly Update" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>';
    }

    private function xlsxWorkbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="5">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><name val="Calibri"/><color rgb="FF102F2A"/></font>
        <font><b/><sz val="12"/><name val="Calibri"/><color rgb="FF102F2A"/></font>
        <font><b/><sz val="10"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
        <font><sz val="10"/><name val="Calibri"/></font>
    </fonts>
    <fills count="5">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFE8F1EE"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF145C4D"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFFF7E6"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border>
            <left style="thin"><color rgb="FF98A2B3"/></left>
            <right style="thin"><color rgb="FF98A2B3"/></right>
            <top style="thin"><color rgb="FF98A2B3"/></top>
            <bottom style="thin"><color rgb="FF98A2B3"/></bottom>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="5">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>
    </cellXfs>
</styleSheet>';
    }

    private function xlsxSheet(BarangayRbiUpdate $rbiUpdate): string
    {
        $rows = [];
        $rows[] = '<row r="1" ht="28" customHeight="1">'.$this->xlsxCell('A', 1, 'UPDATED REGISTRY OF BARANGAY INHABITANTS', 1).'</row>';
        $rows[] = '<row r="2" ht="22" customHeight="1">'.$this->xlsxCell('A', 2, 'of Barangay '.($rbiUpdate->barangay_name ?: '____________'), 2).'</row>';
        $rows[] = '<row r="3" ht="22" customHeight="1">'.$this->xlsxCell('A', 3, 'Reporting Month: '.(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'Not set').'    As of: '.(optional($rbiUpdate->as_of_date)->format('F d, Y') ?: 'Not set'), 2).'</row>';
        $rows[] = '<row r="4" ht="8" customHeight="1"/>';
        $rows[] = '<row r="5" ht="42" customHeight="1">'.collect(array_values(BarangayRbiUpdate::rowFields()))
            ->map(fn (string $label, int $index): string => $this->xlsxCell($this->xlsxColumn($index + 1), 5, $label, 3))
            ->implode('').'</row>';

        $rowNumber = 6;

        foreach ($rbiUpdate->rows ?? [] as $row) {
            $cells = collect(array_keys(BarangayRbiUpdate::rowFields()))
                ->map(fn (string $field, int $index): string => $this->xlsxCell($this->xlsxColumn($index + 1), $rowNumber, (string) ($row[$field] ?? ''), 4))
                ->implode('');
            $rows[] = '<row r="'.$rowNumber.'" ht="34" customHeight="1">'.$cells.'</row>';
            $rowNumber++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheetViews>
        <sheetView workbookViewId="0">
            <pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/>
        </sheetView>
    </sheetViews>
    <cols>
        <col min="1" max="1" width="10" customWidth="1"/>
        <col min="2" max="2" width="24" customWidth="1"/>
        <col min="3" max="3" width="24" customWidth="1"/>
        <col min="4" max="4" width="34" customWidth="1"/>
        <col min="5" max="5" width="10" customWidth="1"/>
        <col min="6" max="6" width="16" customWidth="1"/>
        <col min="7" max="7" width="24" customWidth="1"/>
        <col min="8" max="8" width="16" customWidth="1"/>
        <col min="9" max="9" width="16" customWidth="1"/>
        <col min="10" max="10" width="20" customWidth="1"/>
        <col min="11" max="11" width="18" customWidth="1"/>
        <col min="12" max="12" width="24" customWidth="1"/>
    </cols>
    <sheetData>'.implode('', $rows).'</sheetData>
    <mergeCells count="3">
        <mergeCell ref="A1:L1"/>
        <mergeCell ref="A2:L2"/>
        <mergeCell ref="A3:L3"/>
    </mergeCells>
    <pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>
</worksheet>';
    }

    private function xlsxCell(string $column, int $row, string $value, int $style): string
    {
        return '<c r="'.$column.$row.'" s="'.$style.'" t="inlineStr"><is><t>'.$this->xlsxEscape($value).'</t></is></c>';
    }

    private function xlsxColumn(int $number): string
    {
        $column = '';

        while ($number > 0) {
            $number--;
            $column = chr(65 + ($number % 26)).$column;
            $number = intdiv($number, 26);
        }

        return $column;
    }

    private function xlsxEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function cleanRows(array $rows): array
    {
        $fields = array_keys(BarangayRbiUpdate::rowFields());

        return collect($rows)
            ->map(fn (array $row): array => collect($fields)
                ->mapWithKeys(fn (string $field): array => [$field => trim((string) ($row[$field] ?? ''))])
                ->all())
            ->filter(fn (array $row): bool => collect($row)->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }
}
