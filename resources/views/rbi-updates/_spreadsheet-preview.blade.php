@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-spreadsheet.css') }}?v={{ filemtime(public_path('css/rbi-spreadsheet.css')) }}">
@endpush

<div class="rbi-spreadsheet">
    <div class="rbi-sheet-toolbar">
        <span class="rbi-sheet-file"><x-app-icon name="form" />RBI Monthly Report.xlsx</span>
        <span>Read only</span>
    </div>
    <div class="rbi-sheet-scroll" tabindex="0" role="region" aria-label="Monthly RBI Excel spreadsheet. Scroll to view all columns and rows.">
        <table class="rbi-sheet-grid" aria-label="Monthly RBI spreadsheet">
            <colgroup>
                <col class="rbi-sheet-row-column">
                @foreach ($spreadsheet['columns'] as $width)
                    <col style="width: {{ $width * 8 }}px">
                @endforeach
            </colgroup>
            <thead>
                <tr><th class="rbi-sheet-corner" aria-label="Row numbers"></th>@foreach (range('A', 'O') as $column)<th scope="col">{{ $column }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @foreach ($spreadsheet['rows'] as $row)
                    <tr style="height: {{ $row['height'] }}pt" @class(['rbi-sheet-page-start' => in_array($row['number'] - 1, $spreadsheet['breaks'], true)])>
                        <th scope="row" class="rbi-sheet-row-number">{{ $row['number'] }}</th>
                        @forelse ($row['cells'] as $cell)
                            <td colspan="{{ $cell['span'] }}" rowspan="{{ $cell['rowspan'] }}" class="rbi-sheet-cell rbi-sheet-cell-{{ $cell['style'] }}">
                                @if (!empty($cell['signature']))
                                    <img src="{{ route('rbi-updates.signature', [$rbiUpdate, $cell['signature']]) }}" alt="{{ ['secretary' => 'BHW / Encoder', 'certified' => 'Barangay Secretary', 'captain' => 'Punong Barangay'][$cell['signature']] }} signature">
                                @else
                                    {{ $cell['value'] }}
                                @endif
                            </td>
                        @empty
                            <td colspan="15" class="rbi-sheet-cell rbi-sheet-cell-blank"></td>
                        @endforelse
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="rbi-sheet-tabs"><span class="rbi-sheet-tab">RBI Monthly Report</span><span class="rbi-sheet-status">{{ count($rbiUpdate->rows ?? []) }} inhabitants &middot; {{ count($rbiUpdate->deceased_rows ?? []) }} deceased</span></div>
</div>
