<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Inhabitant;
use App\Services\RegistrySpreadsheet;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegistryPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('registry.print', $this->report($request));
    }

    public function download(Request $request, RegistrySpreadsheet $spreadsheet): Response
    {
        $report = $this->report($request);
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtoupper($report['barangay']->name));
        $filename = 'RBI_'.trim($name ?: 'BARANGAY', '_').'_'.strtoupper($report['scope']).'.xlsx';

        return response($spreadsheet->build($report['residents'], $report['barangay'], $report['title']), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function report(Request $request): array
    {
        $request->validate(['scope' => ['nullable', Rule::in(['consolidated', 'registered', 'living_elsewhere', 'unconfirmed'])]]);
        /** @var Barangay|null $barangay */
        $barangay = $request->user()->barangay;
        abort_unless($barangay, 403, 'Your secretary account is not assigned to a barangay.');

        $scope = $request->input('scope', 'consolidated');
        abort_if($barangay->name === 'Biasong' && $scope !== 'consolidated', 403);
        $residents = Inhabitant::with('household')
            ->where('barangay_id', $barangay->id)
            ->where('status', Inhabitant::STATUS_ACTIVE)
            ->when($scope === 'registered', fn ($query) => $query->where('residence_status', Inhabitant::RESIDENCE_HERE))
            ->when($scope === 'living_elsewhere', fn ($query) => $query->where('residence_status', Inhabitant::RESIDENCE_ELSEWHERE))
            ->when($scope === 'unconfirmed', fn ($query) => $query->where('residence_status', Inhabitant::RESIDENCE_UNCONFIRMED))
            ->orderBy('household_id')->orderBy('id')->get();

        $title = match ($scope) {
            'registered' => 'REGISTERED INHABITANTS LIVING IN BARANGAY',
            'living_elsewhere' => 'REGISTERED INHABITANTS LIVING ELSEWHERE',
            'unconfirmed' => 'REGISTERED INHABITANTS WITH UNCONFIRMED RESIDENCE',
            default => 'CONSOLIDATED HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)',
        };

        return compact('barangay', 'scope', 'residents', 'title');
    }
}
