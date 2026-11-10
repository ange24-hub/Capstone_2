@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/barangay-directory.css') }}?v={{ filemtime(public_path('css/barangay-directory.css')) }}" media="screen">
@endpush

@section('content')
    @php
        $barangaysWithReports = $barangays->filter(fn ($barangay) => $rbiUpdates->where('barangay_name', $barangay->name)->isNotEmpty())->count();
        $activeSecretaries = $barangays->sum('secretaries_count');
    @endphp
    <section class="municipal-directory-page panel" aria-labelledby="municipal-directory-title">
        <header class="directory-page-heading">
            <div class="directory-page-identity">
                <span class="directory-page-icon" aria-hidden="true"><x-app-icon name="form" /></span>
                <div><span class="directory-eyebrow">Municipal reports</span><h1 id="municipal-directory-title">New Inhabitant Report</h1><p>Municipality of Tomas Oppus</p></div>
            </div>
            <a class="button secondary-button directory-dashboard-link" href="{{ route('dashboard.municipal') }}"><x-app-icon name="home" /> Dashboard</a>
        </header>

        <div class="municipal-directory-summary-grid" aria-label="Reporting overview">
            <article><span class="directory-stat-icon"><x-app-icon name="building" /></span><div><strong>{{ $barangays->count() }}</strong><span>Total barangays</span></div></article>
            <article class="has-reports"><span class="directory-stat-icon"><x-app-icon name="inbox" /></span><div><strong>{{ $barangaysWithReports }}</strong><span>With reports</span></div></article>
            <article class="needs-report"><span class="directory-stat-icon"><x-app-icon name="clock" /></span><div><strong>{{ $barangays->count() - $barangaysWithReports }}</strong><span>Awaiting reports</span></div></article>
            <article><span class="directory-stat-icon"><x-app-icon name="users" /></span><div><strong>{{ $activeSecretaries }}</strong><span>Approved secretaries</span></div></article>
        </div>

        <section class="directory-list-shell" aria-label="Reports by barangay">
            <div class="municipal-directory-toolbar">
                <div class="directory-search-field">
                    <label for="barangay-directory-search">Search barangays</label>
                    <div class="directory-search-input"><x-app-icon name="search" /><input id="barangay-directory-search" type="search" placeholder="Barangay or local name" autocomplete="off" aria-controls="municipal-directory-grid"></div>
                </div>
                <div class="directory-filter-group" role="group" aria-label="Filter barangays by report status">
                    <button type="button" class="is-active" aria-pressed="true" data-directory-filter="all">All Barangays</button>
                    <button type="button" aria-pressed="false" data-directory-filter="reported">With Reports</button>
                    <button type="button" aria-pressed="false" data-directory-filter="missing">Awaiting Reports</button>
                </div>
            </div>
            <div class="directory-list-heading"><h2>Barangay reports</h2><span id="barangay-directory-count" role="status" aria-live="polite">Showing {{ $barangays->count() }} barangays</span></div>
            <div class="directory-column-labels" aria-hidden="true"><span>Barangay</span><span>Registry totals</span><span>Latest monthly RBI</span><span></span></div>
            <div class="municipal-directory-grid" id="municipal-directory-grid">
                @foreach ($barangays as $index => $barangay)
                    @php
                        $barangayReports = $rbiUpdates->where('barangay_name', $barangay->name);
                        $latestReport = $barangayReports->first();
                        $hasReports = $barangayReports->isNotEmpty();
                    @endphp
                    <article class="municipal-directory-card {{ $hasReports ? 'has-reports' : 'needs-report' }}" data-directory-card data-status="{{ $hasReports ? 'reported' : 'missing' }}" data-name="{{ str($barangay->name.' '.$barangay->localName())->lower() }}">
                        <details class="directory-entry-details" name="barangay-report-details">
                            <summary class="directory-entry-summary">
                                <span class="directory-row-identity"><span class="barangay-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><span><strong class="directory-barangay-name">{{ $barangay->name }}</strong><span class="directory-local-name">{{ $barangay->localName() ?: 'Tomas Oppus, Southern Leyte' }}</span></span></span>
                                <span class="directory-row-metrics">
                                    <span><strong>{{ number_format($barangay->inhabitants_count) }}</strong><span>Inhabitants</span></span>
                                    <span><strong>{{ number_format($barangay->households_count) }}</strong><span>Households</span></span>
                                    <span><strong>{{ number_format($barangayReports->count()) }}</strong><span>Reports</span></span>
                                </span>
                                <span class="directory-row-receipt">
                                    <span class="directory-status {{ $hasReports ? 'is-received' : 'is-awaiting' }}">{{ $hasReports ? 'Received' : 'Awaiting report' }}</span>
                                    @if($latestReport)<span class="directory-latest-month">{{ optional($latestReport->reporting_month)->format('F Y') ?: 'Month not set' }}</span>@endif
                                </span>
                                <span class="directory-row-toggle">Details <x-app-icon name="chevron-down" /></span>
                            </summary>
                            <div class="directory-entry-body">
                                <section class="directory-reports-panel" aria-label="{{ $barangay->name }} reports and accounts">
                                    <div class="directory-detail-heading"><h3>Reports &amp; accounts</h3><span>{{ $barangay->secretaries_count }} approved {{ $barangay->secretaries_count === 1 ? 'secretary' : 'secretaries' }}</span></div>
                                    @if ($latestReport)
                                        <div class="directory-latest-report">
                                            <span>Latest municipal submission</span>
                                            <strong>{{ optional($latestReport->reporting_month)->format('F Y') ?: 'Reporting month not set' }}</strong>
                                            <small>Received {{ $latestReport->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y · h:i A') }}</small>
                                            <div class="row-actions"><a href="{{ route('rbi-updates.show', $latestReport) }}">Review latest</a><a href="{{ route('rbi-updates.export-pdf', $latestReport) }}">PDF</a></div>
                                        </div>
                                    @else
                                        <div class="directory-missing-report"><strong>No RBI report received</strong></div>
                                    @endif

                                    @if ($hasReports)
                                        <details class="directory-report-history">
                                            <summary>View all {{ $barangayReports->count() }} received report{{ $barangayReports->count() === 1 ? '' : 's' }}</summary>
                                            <div>
                                                @foreach ($barangayReports as $report)
                                                    <article>
                                                        <div><strong>{{ optional($report->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><span>{{ collect($report->rows ?? [])->pluck('household_head')->filter()->unique()->count() }} families · {{ count($report->rows ?? []) }} members</span></div>
                                                        <div class="row-actions"><a href="{{ route('rbi-updates.show', $report) }}">View</a><a href="{{ route('rbi-updates.export-pdf', $report) }}">PDF</a><a href="{{ route('rbi-updates.export-excel', $report) }}">Excel</a></div>
                                                    </article>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </section>
                                <section class="directory-payments-panel" aria-label="{{ $barangay->name }} payment settings">
                                    <details class="directory-gcash-settings">
                                        <summary>
                                            <span>GCash document payments</span>
                                            <b class="payment-status {{ $barangay->gcashIsReady() ? 'payment-status-paid' : 'payment-status-unpaid' }}">
                                                {{ $barangay->gcashIsReady() ? 'Active' : 'Not configured' }}
                                            </b>
                                        </summary>
                                        <form method="POST" enctype="multipart/form-data" action="{{ route('municipal.barangays.gcash.update', $barangay) }}">
                                            @csrf
                                            @method('PUT')
                                            <label class="gcash-enable-field">
                                                <input name="gcash_enabled" type="checkbox" value="1" @checked($barangay->gcash_enabled)>
                                                Enable official GCash collection for this Barangay
                                            </label>
                                            <label for="gcash_merchant_name_{{ $barangay->id }}">Official merchant name</label>
                                            <input id="gcash_merchant_name_{{ $barangay->id }}" name="gcash_merchant_name" value="{{ $barangay->gcash_merchant_name }}" maxlength="100" placeholder="Name shown in GCash">
                                            <label for="gcash_account_identifier_{{ $barangay->id }}">Merchant account identifier</label>
                                            <input id="gcash_account_identifier_{{ $barangay->id }}" name="gcash_account_identifier" value="{{ $barangay->gcash_account_identifier }}" maxlength="100" placeholder="Official account or branch identifier">
                                            <label for="gcash_qr_{{ $barangay->id }}">Official GCash merchant QR image</label>
                                            <input id="gcash_qr_{{ $barangay->id }}" name="gcash_qr" type="file" accept="image/png,image/jpeg">
                                            @if ($barangay->gcash_qr_path)
                                                <a href="{{ route('barangays.gcash.qr', $barangay) }}" target="_blank" rel="noopener">View current merchant QR</a>
                                            @endif
                                            @if ($barangay->gcash_approved_at)
                                                <small>Last approved {{ $barangay->gcash_approved_at->format('M d, Y h:i A') }}</small>
                                            @endif
                                            <button type="submit">Save GCash Profile</button>
                                        </form>
                                    </details>
                                </section>
                            </div>
                        </details>
                    </article>
                @endforeach
            </div>
            <nav class="directory-pagination" id="barangay-directory-pagination" aria-label="New inhabitant report pages" hidden>
                <span id="barangay-directory-page-status"></span>
                <div class="directory-pagination-controls">
                    <button type="button" data-directory-previous aria-controls="municipal-directory-grid">Previous</button>
                    <div class="directory-page-numbers" id="barangay-directory-page-numbers"></div>
                    <button type="button" data-directory-next aria-controls="municipal-directory-grid">Next</button>
                </div>
            </nav>
            <div class="empty-directory" id="empty-directory" hidden><x-app-icon name="search" /><strong>No matching barangays</strong><span>Try another name or report status.</span></div>
        </section>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('js/barangay-directory.js') }}?v={{ filemtime(public_path('js/barangay-directory.js')) }}" defer></script>
@endpush
