<?php

namespace App\Http\Controllers;

use App\Models\BarangayRbiFamily;
use App\Models\BarangayRbiUpdate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class BarangayRbiUpdateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $barangayName = $this->assignedBarangayName($request);

        $validated = $request->validate($this->formRules());
        $rows = $this->cleanRows($validated['rows'] ?? []);
        $this->ensureHouseholdHeads($rows);
        $deceasedRows = $this->resolveDeceasedFamilies($this->cleanDeceasedRows($validated['deceased_rows'] ?? []), $rows);
        $preparedBy = trim((string) ($validated['prepared_by'] ?? '')) ?: ($request->user()->barangay?->secretary_name ?: $request->user()->name);
        $attestedBy = trim((string) ($validated['attested_by'] ?? '')) ?: (string) ($request->user()->barangay?->punong_barangay_name ?? '');

        $existingReport = BarangayRbiUpdate::where('barangay_user_id', $request->user()->id)
            ->whereDate('reporting_month', date('Y-m-01', strtotime($validated['reporting_month'])))
            ->first();

        if ($existingReport) {
            return redirect()
                ->route('barangay.rbi-updates.index', ['edit' => $existingReport->id])
                ->withErrors(['reporting_month' => 'A monthly form already exists for this month. It has been reopened so you can add the new family rows without replacing the existing entries.']);
        }

        if ($request->boolean('submit_to_municipal') && empty($rows) && empty($deceasedRows)) {
            return back()
                ->withErrors(['rows' => 'Enter at least one newly registered or deceased inhabitant before submitting this monthly report.'])
                ->withInput();
        }

        $submitted = $request->boolean('submit_to_municipal');
        [$preparedSignaturePath, $attestedSignaturePath] = $this->storeReportSignatures(
            $request,
            null,
            null,
            $submitted,
            $attestedBy
        );

        $rbiUpdate = BarangayRbiUpdate::create([
            'barangay_user_id' => $request->user()->id,
            'barangay_name' => $barangayName,
            'household_head' => $rows[0]['household_head'] ?? null,
            'reporting_month' => date('Y-m-01', strtotime($validated['reporting_month'])),
            'as_of_date' => $validated['as_of_date'] ?? null,
            'prepared_by' => $preparedBy,
            'prepared_signature_path' => $preparedSignaturePath,
            'attested_by' => $attestedBy,
            'attested_signature_path' => $attestedSignaturePath,
            'status' => $submitted ? BarangayRbiUpdate::STATUS_SUBMITTED : BarangayRbiUpdate::STATUS_DRAFT,
            'families' => null,
            'rows' => $rows,
            'deceased_rows' => $deceasedRows,
            'submitted_at' => $submitted ? now() : null,
        ]);
        $this->syncRelationalRecords($rbiUpdate, $rows, $deceasedRows);

        if ($submitted) {
            return redirect()
                ->route('barangay.rbi-updates.index', ['new' => 1])
                ->with('status', 'Monthly RBI form submitted successfully. The form below is now ready for a new report.')
                ->with('submitted_rbi_update_id', $rbiUpdate->id);
        }

        return back()->with('status', 'Monthly RBI report saved. You can continue adding family rows before submitting.');
    }

    public function update(Request $request, BarangayRbiUpdate $rbiUpdate): RedirectResponse
    {
        $this->authorizeBarangayReportOwner($request, $rbiUpdate);
        $barangayName = $this->assignedBarangayName($request);

        $validated = $request->validate($this->formRules());

        $conflictingReport = BarangayRbiUpdate::where('barangay_user_id', $request->user()->id)
            ->whereKeyNot($rbiUpdate->id)
            ->whereDate('reporting_month', date('Y-m-01', strtotime($validated['reporting_month'])))
            ->exists();

        if ($conflictingReport) {
            return back()
                ->withErrors(['reporting_month' => 'Another RBI report already exists for this month. Continue that monthly report instead.'])
                ->withInput();
        }

        $rows = $this->cleanRows($validated['rows'] ?? []);
        $this->ensureHouseholdHeads($rows);
        $deceasedRows = $this->resolveDeceasedFamilies($this->cleanDeceasedRows($validated['deceased_rows'] ?? []), $rows);
        $preparedBy = trim((string) ($validated['prepared_by'] ?? '')) ?: ($request->user()->barangay?->secretary_name ?: $request->user()->name);
        $attestedBy = trim((string) ($validated['attested_by'] ?? '')) ?: (string) ($request->user()->barangay?->punong_barangay_name ?? '');

        $submitted = $request->boolean('submit_to_municipal')
            || $rbiUpdate->status === BarangayRbiUpdate::STATUS_SUBMITTED;

        if ($submitted && empty($rows) && empty($deceasedRows)) {
            return back()
                ->withErrors(['rows' => 'Enter at least one newly registered or deceased inhabitant before submitting this monthly report.'])
                ->withInput();
        }

        [$preparedSignaturePath, $attestedSignaturePath] = $this->storeReportSignatures(
            $request,
            $rbiUpdate->prepared_signature_path,
            $rbiUpdate->attested_signature_path,
            $submitted,
            $attestedBy
        );

        $rbiUpdate->update([
            'barangay_name' => $barangayName,
            'household_head' => $rows[0]['household_head'] ?? null,
            'reporting_month' => date('Y-m-01', strtotime($validated['reporting_month'])),
            'as_of_date' => $validated['as_of_date'] ?? null,
            'prepared_by' => $preparedBy,
            'prepared_signature_path' => $preparedSignaturePath,
            'attested_by' => $attestedBy,
            'attested_signature_path' => $attestedSignaturePath,
            'families' => null,
            'rows' => $rows,
            'deceased_rows' => $deceasedRows,
            'status' => $submitted ? BarangayRbiUpdate::STATUS_SUBMITTED : BarangayRbiUpdate::STATUS_DRAFT,
            'submitted_at' => $submitted ? now() : null,
        ]);
        $this->syncRelationalRecords($rbiUpdate, $rows, $deceasedRows);

        if ($request->boolean('submit_to_municipal')) {
            return redirect()
                ->route('barangay.rbi-updates.index', ['new' => 1])
                ->with('status', 'Monthly RBI form submitted successfully. The form below is now ready for a new report.')
                ->with('submitted_rbi_update_id', $rbiUpdate->id);
        }

        return back()->with('status', $submitted
            ? 'The submitted monthly RBI form was updated and remains visible to Municipal LGU.'
            : 'Monthly RBI draft saved. You can continue adding family rows.');
    }

    public function submit(Request $request, BarangayRbiUpdate $rbiUpdate): RedirectResponse
    {
        $this->authorizeBarangayReportOwner($request, $rbiUpdate);

        if (empty($this->cleanRows($rbiUpdate->rows ?? [])) && empty($this->cleanDeceasedRows($rbiUpdate->deceased_rows ?? []))) {
            return back()->withErrors(['rows' => 'Add at least one family member or deceased inhabitant before submitting to Municipal LGU.']);
        }

        if (! $rbiUpdate->prepared_signature_path || ! $rbiUpdate->attested_signature_path || ! $rbiUpdate->attested_by) {
            return back()->withErrors(['signatures' => 'The monthly form requires the Barangay Secretary and Punong Barangay names and signatures before submitting.']);
        }

        $rbiUpdate->update([
            'status' => BarangayRbiUpdate::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('barangay.rbi-updates.index', ['new' => 1])
            ->with('status', 'Monthly RBI form submitted successfully. The form below is now ready for a new report.')
            ->with('submitted_rbi_update_id', $rbiUpdate->id);
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
            'rbiDeceasedRowFields' => BarangayRbiUpdate::deceasedRowFields(),
        ]);
    }

    public function signature(Request $request, BarangayRbiUpdate $rbiUpdate, string $type, ?int $family = null): StreamedResponse
    {
        $this->authorizeView($request, $rbiUpdate);

        $path = match ($type) {
            'secretary' => $rbiUpdate->prepared_signature_path,
            'captain' => $rbiUpdate->attested_signature_path,
            default => null,
        };

        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }

    public function exportWord(Request $request, BarangayRbiUpdate $rbiUpdate): StreamedResponse
    {
        $this->authorizeView($request, $rbiUpdate);

        $barangay = str($rbiUpdate->barangay_name ?: 'barangay')->slug('-');
        $month = optional($rbiUpdate->reporting_month)->format('Y-m') ?: 'undated';
        $filename = 'rbi-monthly-'.$barangay.'-'.$month.'.docx';

        return response()->streamDownload(function () use ($rbiUpdate) {
            echo $this->buildWordDocument($rbiUpdate);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function exportPdf(Request $request, BarangayRbiUpdate $rbiUpdate): Response
    {
        $this->authorizeView($request, $rbiUpdate);
        $rbiUpdate->load([
            'barangayUser.barangay',
            'rbiFamilies.members',
            'rbiFamilies.deceasedRecords',
        ]);

        $barangay = $rbiUpdate->barangayUser?->barangay;
        $barangayName = $barangay?->name ?: $rbiUpdate->barangay_name ?: 'Barangay';
        $secretaryName = $barangay?->secretary_name ?: $rbiUpdate->prepared_by ?: $rbiUpdate->barangayUser?->name;
        $punongBarangayName = $barangay?->punong_barangay_name ?: $rbiUpdate->attested_by;
        $pages = $this->pdfPages($rbiUpdate);
        $month = optional($rbiUpdate->reporting_month)->format('F_Y') ?: 'Undated';
        $filenameBarangay = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', Str::ascii($barangayName)), '_') ?: 'Barangay';
        $filename = 'RBI_'.$filenameBarangay.'_'.$month.'.pdf';

        return Pdf::loadView('rbi-updates.pdf', [
            'rbiUpdate' => $rbiUpdate,
            'pages' => $pages,
            'barangayName' => $barangayName,
            'secretaryName' => $secretaryName,
            'punongBarangayName' => $punongBarangayName,
            'logoDataUri' => $this->barangayLogoDataUri($barangay?->logo_path),
            'preparedSignatureDataUri' => $this->storageImageDataUri($rbiUpdate->prepared_signature_path),
            'attestedSignatureDataUri' => $this->storageImageDataUri($rbiUpdate->attested_signature_path),
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    private function formRules(): array
    {
        return [
            'reporting_month' => ['required', 'date_format:Y-m'],
            'as_of_date' => ['nullable', 'date'],
            'prepared_by' => ['nullable', 'string', 'max:255'],
            'attested_by' => ['nullable', 'string', 'max:255'],
            'prepared_signature_data' => ['nullable', 'string', 'max:3000000'],
            'attested_signature_data' => ['nullable', 'string', 'max:3000000'],
            'rows' => ['nullable', 'array'],
            'rows.*.household_head' => ['nullable', 'string', 'max:255'],
            'rows.*.household_id' => ['nullable', 'integer', Rule::exists('households', 'id')->where('barangay_id', request()->user()?->barangay_id)],
            'rows.*.inhabitant_id' => ['nullable', 'integer', Rule::exists('inhabitants', 'id')->where('barangay_id', request()->user()?->barangay_id)],
            'rows.*.inhabitant_name' => ['nullable', 'string', 'max:255'],
            'rows.*.sex' => ['nullable', Rule::in(['', 'Male', 'Female'])],
            'rows.*.birth_date' => ['nullable', 'date'],
            'rows.*.birth_place' => ['nullable', 'string', 'max:255'],
            'rows.*.civil_status' => ['nullable', 'string', 'max:100'],
            'rows.*.occupation' => ['nullable', 'string', 'max:255'],
            'rows.*.relationship' => ['nullable', 'string', 'max:255'],
            'deceased_rows' => ['nullable', 'array'],
            'deceased_rows.*.deceased_name' => ['nullable', 'string', 'max:255'],
            'deceased_rows.*.death_date' => ['nullable', 'date'],
            'deceased_rows.*.household_head' => ['nullable', 'string', 'max:255'],
            'deceased_rows.*.household_id' => ['nullable', 'integer', Rule::exists('households', 'id')->where('barangay_id', request()->user()?->barangay_id)],
            'deceased_rows.*.inhabitant_id' => ['nullable', 'integer', Rule::exists('inhabitants', 'id')->where('barangay_id', request()->user()?->barangay_id)],
        ];
    }

    private function hasDrawnSignature(Request $request, string $field): bool
    {
        return str_starts_with((string) $request->input($field), 'data:image/png;base64,');
    }

    private function storeDrawnSignature(Request $request, string $field, ?string $currentPath = null): ?string
    {
        $dataUrl = trim((string) $request->input($field));

        if ($dataUrl === '') {
            return $currentPath;
        }

        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $matches)) {
            throw ValidationException::withMessages([$field => 'The drawn signature could not be read. Please clear it and sign again.']);
        }

        $signature = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);

        if ($signature === false || $signature === '' || strlen($signature) > 2 * 1024 * 1024 || ! str_starts_with($signature, "\x89PNG\r\n\x1a\n")) {
            throw ValidationException::withMessages([$field => 'The drawn signature must be a valid PNG image no larger than 2 MB.']);
        }

        $path = 'rbi-signatures/'.Str::uuid().'.png';
        Storage::put($path, $signature);

        if ($currentPath && Storage::exists($currentPath)) {
            Storage::delete($currentPath);
        }

        return $path;
    }

    private function authorizeBarangayReportOwner(Request $request, BarangayRbiUpdate $rbiUpdate): void
    {
        abort_unless($rbiUpdate->barangay_user_id === $request->user()->id, 403);
    }

    private function assignedBarangayName(Request $request): string
    {
        $barangay = $request->user()->barangay;
        abort_unless($barangay, 403, 'Your secretary account is not assigned to a barangay.');

        return $barangay->name;
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

    private function blankDeceasedRows(): array
    {
        return array_fill(0, 3, array_fill_keys(array_keys(BarangayRbiUpdate::deceasedRowFields()), ''));
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
        $zip = new ZipArchive;

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
        $columns = range('A', 'H');
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
        if ($column === 'D' && is_numeric($value)) {
            return gmdate('Y-m-d', (((int) $value) - 25569) * 86400);
        }

        return $value;
    }

    private function buildEditedWorkbook(BarangayRbiUpdate $rbiUpdate): string
    {
        $columnCount = count(BarangayRbiUpdate::rowFields());
        $deceasedNameColumns = max($columnCount - 2, 1);
        $headers = collect(BarangayRbiUpdate::rowFields())
            ->map(fn (string $label): string => '<th>'.$this->excelEscape($label).'</th>')
            ->implode('');

        $bodyRows = collect($rbiUpdate->rows ?? [])
            ->map(function (array $row): string {
                $cells = collect(array_keys(BarangayRbiUpdate::rowFields()))
                    ->map(fn (string $field): string => '<td>'.$this->excelEscape(
                        $field === 'birth_date'
                            ? $this->formatFormDate((string) ($row[$field] ?? ''))
                            : (string) ($row[$field] ?? '')
                    ).'</td>')
                    ->implode('');

                return '<tr>'.$cells.'</tr>';
            })
            ->implode('');

        if ($bodyRows === '') {
            $bodyRows = '<tr><td colspan="'.$columnCount.'" class="empty">No family members entered.</td></tr>';
        }

        $deceasedRows = collect($rbiUpdate->deceased_rows ?? [])
            ->map(function (array $row) use ($deceasedNameColumns): string {
                return '<tr><td colspan="'.$deceasedNameColumns.'">'.$this->excelEscape((string) ($row['deceased_name'] ?? '')).'</td>'
                    .'<td colspan="2">'.$this->excelEscape($this->formatFormDate((string) ($row['death_date'] ?? ''))).'</td></tr>';
            })
            ->implode('');

        if ($deceasedRows === '') {
            $deceasedRows = '<tr><td colspan="2" class="empty">No deceased inhabitants reported.</td></tr>';
        }

        return '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #000000; }
        table { border-collapse: collapse; width: 100%; }
        .title { font-size: 16px; font-weight: 700; text-align: center; }
        th { font-size: 11px; font-weight: 700; text-align: center; vertical-align: middle; }
        td, th { border: 1px solid #000000; padding: 8px; vertical-align: top; white-space: normal; }
        td { font-size: 12px; }
        .section { font-size: 12px; font-weight: 700; }
        .empty { text-align: center; }
        .signature-label { border: 0; font-size: 12px; padding-top: 28px; }
        .signature-line { border: 0; font-size: 12px; font-weight: 700; text-align: center; }
        .signature-title { border: 0; font-size: 11px; text-align: center; }
        .no-border { border: 0; }
    </style>
</head>
<body>
    <table>
        <tr><td colspan="'.$columnCount.'" class="title">Updates of Barangay Registry of Barangay Inhabitants</td></tr>
        <tr><td colspan="'.$columnCount.'" class="title">For the month of '.$this->excelEscape(strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET')).'</td></tr>
        <tr><td colspan="'.$columnCount.'" class="title">Barangay '.$this->excelEscape($rbiUpdate->barangay_name ?: '____________').'</td></tr>
        <tr>'.$headers.'</tr>
        '.$bodyRows.'
        <tr><td colspan="'.$columnCount.'" class="no-border">&nbsp;</td></tr>
        <tr><th colspan="'.$deceasedNameColumns.'">B. Name of Deceased Registered Barangay Inhabitant</th><th colspan="2">Date of Death</th></tr>
        '.$deceasedRows.'
        <tr><td colspan="'.$columnCount.'" class="no-border">&nbsp;</td></tr>
        <tr>
            <td colspan="3" class="signature-label">Prepared by:</td>
            <td colspan="1" class="no-border"></td>
            <td colspan="4" class="signature-label">Noted by:</td>
        </tr>
        <tr>
            <td colspan="3" class="signature-line">'.$this->excelEscape($rbiUpdate->prepared_by ?: ($rbiUpdate->barangayUser->name ?? '')).'</td>
            <td colspan="1" class="no-border"></td>
            <td colspan="4" class="signature-line">'.$this->excelEscape($rbiUpdate->attested_by ?: '').'</td>
        </tr>
        <tr>
            <td colspan="3" class="signature-title">Barangay Secretary</td>
            <td colspan="1" class="no-border"></td>
            <td colspan="4" class="signature-title">Punong Barangay</td>
        </tr>
    </table>
</body>
</html>';
    }

    private function excelEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatFormDate(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('m/d/y', $timestamp);
    }

    private function buildWordDocument(BarangayRbiUpdate $rbiUpdate): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'rbi-word-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('Unable to create the temporary Word document.');
        }

        $zip = new ZipArchive;

        if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($temporaryPath);
            throw new \RuntimeException('Unable to create the Word document package.');
        }

        $signatureMedia = $this->wordSignatureMedia($rbiUpdate);

        $zip->addFromString('[Content_Types].xml', $this->wordContentTypes());
        $zip->addFromString('_rels/.rels', $this->wordRootRelationships());
        $zip->addFromString('word/document.xml', $this->wordDocumentXml($rbiUpdate, $signatureMedia));
        $zip->addFromString('word/_rels/document.xml.rels', $this->wordDocumentRelationships($signatureMedia));

        foreach ($signatureMedia as $media) {
            $content = isset($media['absolute_path'])
                ? file_get_contents($media['absolute_path'])
                : Storage::get($media['path']);

            if ($content !== false) {
                $zip->addFromString('word/media/'.$media['filename'], $content);
            }
        }

        $zip->close();

        $document = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        if ($document === false) {
            throw new \RuntimeException('Unable to read the generated Word document.');
        }

        return $document;
    }

    private function wordContentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Default Extension="png" ContentType="image/png"/>
    <Default Extension="jpg" ContentType="image/jpeg"/>
    <Default Extension="jpeg" ContentType="image/jpeg"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
    }

    private function wordRootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    }

    private function wordSignatureMedia(BarangayRbiUpdate $rbiUpdate): array
    {
        $signatures = [
            'secretary' => [$rbiUpdate->prepared_signature_path, 'rId2', 'secretary-signature'],
            'captain' => [$rbiUpdate->attested_signature_path, 'rId3', 'captain-signature'],
        ];

        $media = collect($signatures)
            ->filter(fn (array $signature): bool => (bool) $signature[0] && Storage::exists($signature[0]))
            ->map(function (array $signature): array {
                $extension = strtolower(pathinfo($signature[0], PATHINFO_EXTENSION));
                $extension = in_array($extension, ['png', 'jpg', 'jpeg'], true) ? $extension : 'png';

                return [
                    'path' => $signature[0],
                    'relationship' => $signature[1],
                    'filename' => $signature[2].'.'.$extension,
                ];
            })
            ->all();

        $sealPath = public_path('images/tomas-oppus-seal.png');

        if (is_file($sealPath)) {
            $media['seal'] = [
                'absolute_path' => $sealPath,
                'relationship' => 'rId4',
                'filename' => 'tomas-oppus-seal.png',
            ];
        }

        return $media;
    }

    private function wordDocumentRelationships(array $signatureMedia): string
    {
        $relationships = collect($signatureMedia)
            ->map(fn (array $media): string => '<Relationship Id="'.$media['relationship'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$media['filename'].'"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function wordDocumentXml(BarangayRbiUpdate $rbiUpdate, array $signatureMedia): string
    {
        $mainWidths = [2057, 3462, 598, 1457, 1869, 1028, 1401, 1683];
        $month = strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET');
        $barangay = $rbiUpdate->barangay_name ?: '____________';
        $rows = $rbiUpdate->rows ?? [];
        $families = collect($rows)
            ->groupBy(fn (array $row, int $index): string => trim((string) ($row['household_head'] ?? '')) ?: '__unnamed_'.$index)
            ->values();

        if ($families->isEmpty()) {
            $families = collect([collect([[]])]);
        }

        $familyPages = $families
            ->map(function ($familyRows, int $familyIndex) use ($barangay, $families, $mainWidths, $month, $rbiUpdate, $signatureMedia): string {
                $mainTableRows = [[
                    'cells' => array_values(BarangayRbiUpdate::rowFields()),
                    'center' => true,
                    'size' => 21,
                    'height' => 1060,
                ]];

                foreach ($familyRows->values() as $memberIndex => $row) {
                    $mainTableRows[] = [
                        'cells' => collect(array_keys(BarangayRbiUpdate::rowFields()))
                            ->map(fn (string $field): string => match ($field) {
                                'household_head' => $memberIndex === 0 ? (string) ($row[$field] ?? '') : '',
                                'birth_date' => $this->formatFormDate((string) ($row[$field] ?? '')),
                                default => (string) ($row[$field] ?? ''),
                            })
                            ->all(),
                        'size' => 18,
                        'height' => max(620, (int) floor(4000 / max($familyRows->count(), 1))),
                    ];
                }

                $deceasedRows = $familyIndex === $families->count() - 1
                    ? ($rbiUpdate->deceased_rows ?? [])
                    : [];

                if ($deceasedRows === []) {
                    $deceasedRows[] = [];
                }

                $deceasedTableRows = [[
                    'cells' => array_values(BarangayRbiUpdate::deceasedRowFields()),
                    'center' => true,
                    'size' => 21,
                    'height' => 615,
                ]];

                foreach ($deceasedRows as $row) {
                    $deceasedTableRows[] = [
                        'cells' => [
                            (string) ($row['deceased_name'] ?? ''),
                            $this->formatFormDate((string) ($row['death_date'] ?? '')),
                        ],
                        'size' => 18,
                        'height' => max(700, (int) floor(2200 / max(count($deceasedRows), 1))),
                    ];
                }

                $documentIdOffset = $familyIndex * 3;
                $pageBreak = $familyIndex < $families->count() - 1
                    ? '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'
                    : '';

                return $this->wordHeaderTable($month, $barangay, $signatureMedia['seal'] ?? null, $documentIdOffset + 3)
                    .$this->wordParagraph('', false, false, 4)
                    .$this->wordTable($mainTableRows, $mainWidths)
                    .$this->wordParagraph('', false, false, 4)
                    .$this->wordTable($deceasedTableRows, [5679, 4900])
                    .$this->wordParagraph('', false, false, 4)
                    .$this->wordSignatureTable(
                        $rbiUpdate->prepared_by ?: ($rbiUpdate->barangayUser->name ?? ''),
                        $rbiUpdate->attested_by ?: '',
                        $signatureMedia,
                        $documentIdOffset
                    )
                    .$pageBreak;
            })
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
    <w:body>'
        .$familyPages
        .'<w:sectPr>
            <w:pgSz w:w="15840" w:h="12240" w:orient="landscape"/>
            <w:pgMar w:top="749" w:right="893" w:bottom="792" w:left="893" w:header="360" w:footer="360" w:gutter="0"/>
        </w:sectPr>
    </w:body>
</w:document>';
    }

    private function wordSignatureTable(string $preparedBy, string $notedBy, array $signatureMedia, int $documentIdOffset = 0): string
    {
        $secretaryImage = isset($signatureMedia['secretary'])
            ? $this->wordImageDrawing($signatureMedia['secretary']['relationship'], 'Barangay Secretary Signature', $documentIdOffset + 1)
            : $this->wordParagraph('', false, true, 16);
        $captainImage = isset($signatureMedia['captain'])
            ? $this->wordImageDrawing($signatureMedia['captain']['relationship'], 'Barangay Captain Signature', $documentIdOffset + 2)
            : $this->wordParagraph('', false, true, 16);

        return '<w:tbl>
            <w:tblPr><w:tblW w:w="8640" w:type="dxa"/><w:jc w:val="center"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>
            <w:tblGrid><w:gridCol w:w="4320"/><w:gridCol w:w="4320"/></w:tblGrid>
            <w:tr>
                <w:tc><w:tcPr><w:tcW w:w="4320" w:type="dxa"/></w:tcPr>'.$this->wordParagraph('Prepared by:', false, false, 21).$secretaryImage.$this->wordParagraph($preparedBy, true, true, 21).$this->wordParagraph('Brgy. Secretary', false, true, 21).'</w:tc>
                <w:tc><w:tcPr><w:tcW w:w="4320" w:type="dxa"/></w:tcPr>'.$this->wordParagraph('Noted by:', false, false, 21).$captainImage.$this->wordParagraph($notedBy, true, true, 21).$this->wordParagraph('Punong Barangay', false, true, 21).'</w:tc>
            </w:tr>
        </w:tbl>';
    }

    private function wordHeaderTable(string $month, string $barangay, ?array $sealMedia, int $documentPropertyId = 3): string
    {
        $seal = $sealMedia
            ? $this->wordImageDrawing($sealMedia['relationship'], 'Tomas Oppus Seal', $documentPropertyId, 566420, 544195)
            : $this->wordParagraph('', false, true, 8);

        return '<w:tbl>
            <w:tblPr><w:tblW w:w="7560" w:type="dxa"/><w:jc w:val="center"/><w:tblLayout w:type="fixed"/><w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders></w:tblPr>
            <w:tblGrid><w:gridCol w:w="1123"/><w:gridCol w:w="6437"/></w:tblGrid>
            <w:tr>
                <w:tc><w:tcPr><w:tcW w:w="1123" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'.$seal.'</w:tc>
                <w:tc><w:tcPr><w:tcW w:w="6437" w:type="dxa"/><w:vAlign w:val="center"/></w:tcPr>'
                    .$this->wordParagraph('Updates of Barangay Registry of Barangay Inhabitants', false, true, 17)
                    .$this->wordParagraph('For the month of '.$month, true, true, 17)
                    .$this->wordParagraph('Barangay '.$barangay, false, true, 17)
                .'</w:tc>
            </w:tr>
        </w:tbl>';
    }

    private function wordImageDrawing(
        string $relationshipId,
        string $name,
        int $documentPropertyId,
        int $width = 1800000,
        int $height = 600000
    ): string
    {
        $safeName = $this->xlsxEscape($name);

        return '<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">
            <wp:extent cx="'.$width.'" cy="'.$height.'"/><wp:docPr id="'.$documentPropertyId.'" name="'.$safeName.'"/>
            <a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>
                <pic:nvPicPr><pic:cNvPr id="0" name="'.$safeName.'"/><pic:cNvPicPr/></pic:nvPicPr>
                <pic:blipFill><a:blip r:embed="'.$relationshipId.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>
                <pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>
            </pic:pic></a:graphicData></a:graphic>
        </wp:inline></w:drawing></w:r></w:p>';
    }

    private function wordTable(array $rows, array $widths, bool $borders = true): string
    {
        $borderStyle = $borders
            ? '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="000000"/><w:left w:val="single" w:sz="4" w:color="000000"/><w:bottom w:val="single" w:sz="4" w:color="000000"/><w:right w:val="single" w:sz="4" w:color="000000"/><w:insideH w:val="single" w:sz="4" w:color="000000"/><w:insideV w:val="single" w:sz="4" w:color="000000"/></w:tblBorders>'
            : '<w:tblBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/><w:insideH w:val="nil"/><w:insideV w:val="nil"/></w:tblBorders>';

        $grid = collect($widths)
            ->map(fn (int $width): string => '<w:gridCol w:w="'.$width.'"/>')
            ->implode('');

        $tableRows = collect($rows)
            ->map(function (array $row) use ($widths): string {
                $cells = collect($row['cells'])
                    ->map(fn (string $value, int $index): string => $this->wordCell(
                        $value,
                        $widths[$index] ?? 1800,
                        (bool) ($row['bold'] ?? false),
                        (bool) ($row['center'] ?? false),
                        (int) ($row['size'] ?? 18)
                    ))
                    ->implode('');

                $height = isset($row['height'])
                    ? '<w:trPr><w:trHeight w:val="'.(int) $row['height'].'" w:hRule="atLeast"/></w:trPr>'
                    : '';

                return '<w:tr>'.$height.$cells.'</w:tr>';
            })
            ->implode('');

        $tableWidth = array_sum($widths);

        return '<w:tbl><w:tblPr><w:tblW w:w="'.$tableWidth.'" w:type="dxa"/><w:jc w:val="center"/><w:tblLayout w:type="fixed"/>'.$borderStyle.'</w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>'.$tableRows.'</w:tbl>';
    }

    private function wordCell(string $value, int $width, bool $bold = false, bool $center = false, int $size = 18): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/><w:vAlign w:val="center"/><w:tcMar><w:top w:w="55" w:type="dxa"/><w:left w:w="45" w:type="dxa"/><w:bottom w:w="45" w:type="dxa"/><w:right w:w="45" w:type="dxa"/></w:tcMar></w:tcPr>'
            .$this->wordParagraph($value, $bold, $center, $size)
            .'</w:tc>';
    }

    private function wordParagraph(string $value, bool $bold = false, bool $center = false, int $size = 18): string
    {
        $paragraphProperties = '<w:pPr><w:spacing w:before="0" w:after="0"/>'.($center ? '<w:jc w:val="center"/>' : '').'</w:pPr>';
        $runProperties = '<w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:eastAsia="Arial"/>'.($bold ? '<w:b/>' : '').'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr>';

        return '<w:p>'.$paragraphProperties.'<w:r>'.$runProperties.'<w:t xml:space="preserve">'.$this->xlsxEscape($value).'</w:t></w:r></w:p>';
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
        $rows[] = '<row r="1" ht="28" customHeight="1">'.$this->xlsxCell('A', 1, 'UPDATES OF BARANGAY REGISTRY OF BARANGAY INHABITANTS', 1).'</row>';
        $rows[] = '<row r="2" ht="22" customHeight="1">'.$this->xlsxCell('A', 2, 'of Barangay '.($rbiUpdate->barangay_name ?: '____________'), 2).'</row>';
        $rows[] = '<row r="3" ht="22" customHeight="1">'.$this->xlsxCell('A', 3, 'Reporting Month: '.(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'Not set').'    As of: '.(optional($rbiUpdate->as_of_date)->format('F d, Y') ?: 'Not set'), 2).'</row>';
        $rows[] = '<row r="4" ht="22" customHeight="1">'.$this->xlsxCell('A', 4, 'One continuous monthly form', 2).'</row>';
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
    <mergeCells count="4">
        <mergeCell ref="A1:H1"/>
        <mergeCell ref="A2:H2"/>
        <mergeCell ref="A3:H3"/>
        <mergeCell ref="A4:H4"/>
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
        $fields = array_merge(['household_id', 'inhabitant_id'], array_keys(BarangayRbiUpdate::rowFields()));

        return collect($rows)
            ->map(fn (array $row): array => collect($fields)
                ->mapWithKeys(fn (string $field): array => [$field => trim((string) ($row[$field] ?? ''))])
                ->all())
            ->filter(fn (array $row): bool => collect($row)
                ->except(['household_id', 'inhabitant_id', 'household_head'])
                ->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }

    private function ensureHouseholdHeads(array $rows): void
    {
        if (collect($rows)->contains(fn (array $row): bool => ($row['household_head'] ?? '') === '')) {
            throw ValidationException::withMessages([
                'rows' => 'Enter the household head for every family section that has a member.',
            ]);
        }
    }

    private function storeReportSignatures(
        Request $request,
        ?string $currentPreparedPath,
        ?string $currentAttestedPath,
        bool $required,
        string $attestedBy
    ): array
    {
        $errors = [];

        if ($required && ! $this->hasDrawnSignature($request, 'prepared_signature_data') && ! $currentPreparedPath) {
            $errors['prepared_signature_data'] = 'Draw the Barangay Secretary signature for the monthly form.';
        }

        if ($required && ! $this->hasDrawnSignature($request, 'attested_signature_data') && ! $currentAttestedPath) {
            $errors['attested_signature_data'] = 'Draw the Punong Barangay signature for the monthly form.';
        }

        if ($required && $attestedBy === '') {
            $errors['attested_by'] = 'Enter the Punong Barangay name for the monthly form.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            $this->storeDrawnSignature($request, 'prepared_signature_data', $currentPreparedPath),
            $this->storeDrawnSignature($request, 'attested_signature_data', $currentAttestedPath),
        ];
    }

    private function cleanDeceasedRows(array $rows): array
    {
        $fields = array_merge(['household_id', 'household_head', 'inhabitant_id'], array_keys(BarangayRbiUpdate::deceasedRowFields()));

        return collect($rows)
            ->map(fn (array $row): array => collect($fields)
                ->mapWithKeys(fn (string $field): array => [$field => trim((string) ($row[$field] ?? ''))])
                ->all())
            ->filter(fn (array $row): bool => collect($row)
                ->only(array_keys(BarangayRbiUpdate::deceasedRowFields()))
                ->contains(fn (string $value): bool => $value !== ''))
            ->values()
            ->all();
    }

    private function resolveDeceasedFamilies(array $deceasedRows, array $rows): array
    {
        $families = collect($rows)
            ->map(fn (array $row): array => [
                'household_id' => (string) ($row['household_id'] ?? ''),
                'household_head' => trim((string) ($row['household_head'] ?? '')),
            ])
            ->unique(fn (array $family): string => $family['household_id'] ?: mb_strtolower($family['household_head']))
            ->values();

        if ($families->count() === 1) {
            return collect($deceasedRows)->map(function (array $row) use ($families): array {
                $family = $families->first();
                $row['household_id'] = $row['household_id'] ?: $family['household_id'];
                $row['household_head'] = $row['household_head'] ?: $family['household_head'];

                return $row;
            })->all();
        }

        if ($families->count() > 1 && collect($deceasedRows)->contains(
            fn (array $row): bool => ($row['household_id'] ?? '') === '' && ($row['household_head'] ?? '') === ''
        )) {
            throw ValidationException::withMessages([
                'deceased_rows' => 'Select the household for every deceased inhabitant when the report contains more than one family.',
            ]);
        }

        return $deceasedRows;
    }

    private function syncRelationalRecords(BarangayRbiUpdate $report, array $rows, array $deceasedRows): void
    {
        DB::transaction(function () use ($report, $rows, $deceasedRows): void {
            $report->deceasedRecords()->delete();
            $report->rbiFamilies()->delete();

            $groupedRows = collect($rows)
                ->groupBy(fn (array $row, int $index): string => ($row['household_id'] ?? '') !== ''
                    ? 'household:'.$row['household_id']
                    : (mb_strtolower(trim((string) ($row['household_head'] ?? ''))) ?: '__unnamed_'.$index));
            $familyMap = [];

            foreach ($groupedRows->values() as $familyPosition => $members) {
                $first = $members->first();
                $family = $report->rbiFamilies()->create([
                    'household_id' => $first['household_id'] ?: null,
                    'household_head' => $first['household_head'],
                    'position' => $familyPosition,
                ]);
                $familyMap[$this->familyKey($first)] = $family;

                foreach ($members->values() as $memberPosition => $member) {
                    $family->members()->create([
                        'inhabitant_id' => $member['inhabitant_id'] ?: null,
                        'inhabitant_name' => $member['inhabitant_name'],
                        'sex' => $member['sex'] ?: null,
                        'birth_date' => $member['birth_date'] ?: null,
                        'birth_place' => $member['birth_place'] ?: null,
                        'civil_status' => $member['civil_status'] ?: null,
                        'occupation' => $member['occupation'] ?: null,
                        'relationship' => $member['relationship'] ?: null,
                        'position' => $memberPosition,
                    ]);
                }
            }

            foreach ($deceasedRows as $position => $deceased) {
                $key = $this->familyKey($deceased);
                $family = $familyMap[$key] ?? null;

                if (! $family && trim((string) ($deceased['household_head'] ?? '')) !== '') {
                    $family = $report->rbiFamilies()->create([
                        'household_id' => $deceased['household_id'] ?: null,
                        'household_head' => $deceased['household_head'],
                        'position' => count($familyMap),
                    ]);
                    $familyMap[$key] = $family;
                }

                $report->deceasedRecords()->create([
                    'barangay_rbi_family_id' => $family?->id,
                    'inhabitant_id' => $deceased['inhabitant_id'] ?: null,
                    'deceased_name' => $deceased['deceased_name'],
                    'death_date' => $deceased['death_date'] ?: null,
                    'position' => $position,
                ]);
            }
        });
    }

    private function familyKey(array $row): string
    {
        return ($row['household_id'] ?? '') !== ''
            ? 'household:'.$row['household_id']
            : mb_strtolower(trim((string) ($row['household_head'] ?? '')));
    }

    private function pdfPages(BarangayRbiUpdate $report): array
    {
        $families = $report->rbiFamilies->map(function (BarangayRbiFamily $family): array {
            return [
                'household_head' => $family->household_head,
                'members' => $family->members->map(fn ($member): array => [
                    'inhabitant_name' => $member->inhabitant_name,
                    'sex' => $member->sex,
                    'birth_date' => optional($member->birth_date)->format('Y-m-d'),
                    'birth_place' => $member->birth_place,
                    'civil_status' => $member->civil_status,
                    'occupation' => $member->occupation,
                    'relationship' => $member->relationship,
                ])->all(),
                'deceased' => $family->deceasedRecords->map(fn ($record): array => [
                    'deceased_name' => $record->deceased_name,
                    'death_date' => optional($record->death_date)->format('Y-m-d'),
                ])->all(),
            ];
        })->all();

        if ($families === []) {
            $families = collect($report->rows ?? [])
                ->groupBy(fn (array $row, int $index): string => trim((string) ($row['household_head'] ?? '')) ?: '__unnamed_'.$index)
                ->map(fn ($members): array => [
                    'household_head' => (string) ($members->first()['household_head'] ?? ''),
                    'members' => $members->values()->all(),
                    'deceased' => [],
                ])->values()->all();
        }

        if ($families === []) {
            $families[] = ['household_head' => '', 'members' => [], 'deceased' => []];
        }

        $unassignedDeaths = $report->deceasedRecords
            ->whereNull('barangay_rbi_family_id')
            ->map(fn ($record): array => [
                'deceased_name' => $record->deceased_name,
                'death_date' => optional($record->death_date)->format('Y-m-d'),
            ])->all();

        if ($unassignedDeaths === [] && $report->deceasedRecords->isEmpty()) {
            $unassignedDeaths = $report->deceased_rows ?? [];
        }

        if ($unassignedDeaths !== []) {
            $families[array_key_last($families)]['deceased'] = array_merge(
                $families[array_key_last($families)]['deceased'],
                $unassignedDeaths
            );
        }

        $pages = [];
        foreach ($families as $familyIndex => $family) {
            $memberChunks = array_chunk($family['members'], 7);
            $memberChunks = $memberChunks ?: [[]];
            $deathChunks = array_chunk($family['deceased'], 3);
            $deathChunks = $deathChunks ?: [[]];
            $deathStart = count($memberChunks) - 1;
            $pageCount = max(count($memberChunks), $deathStart + count($deathChunks));

            for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++) {
                $pages[] = [
                    'household_head' => $family['household_head'],
                    'members' => $memberChunks[$pageIndex] ?? [],
                    'deceased' => $pageIndex >= $deathStart ? ($deathChunks[$pageIndex - $deathStart] ?? []) : [],
                    'continued' => $pageIndex > 0,
                    'show_signatures' => $pageIndex === $pageCount - 1,
                ];
            }
        }

        return $pages;
    }

    private function barangayLogoDataUri(?string $configuredPath): ?string
    {
        if ($configuredPath) {
            if (Storage::exists($configuredPath)) {
                return $this->storageImageDataUri($configuredPath);
            }

            $publicPath = public_path(ltrim($configuredPath, '/\\'));
            if (is_file($publicPath)) {
                return $this->absoluteImageDataUri($publicPath);
            }
        }

        $fallback = public_path('images/tomas-oppus-seal.png');

        return is_file($fallback) ? $this->absoluteImageDataUri($fallback) : null;
    }

    private function storageImageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::exists($path)) {
            return null;
        }

        $mimeType = Storage::mimeType($path) ?: 'image/png';

        return $this->canRenderImageType($mimeType)
            ? 'data:'.$mimeType.';base64,'.base64_encode(Storage::get($path))
            : null;
    }

    private function absoluteImageDataUri(string $path): ?string
    {
        $mimeType = mime_content_type($path) ?: 'image/png';

        return $this->canRenderImageType($mimeType)
            ? 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path))
            : null;
    }

    private function canRenderImageType(string $mimeType): bool
    {
        return ! str_contains($mimeType, 'png') || extension_loaded('gd');
    }
}
