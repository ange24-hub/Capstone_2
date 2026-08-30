@extends('layouts.app')

@section('content')
    @php
        $barangaysWithReports = $barangays->filter(fn ($barangay) => $rbiUpdates->where('barangay_name', $barangay->name)->isNotEmpty())->count();
        $activeSecretaries = $barangays->sum('secretaries_count');
    @endphp

    <section class="municipal-directory-page panel stack" aria-labelledby="municipal-directory-title">
        <div class="government-page-heading">
            <div>
                <nav class="government-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('dashboard.municipal') }}">Municipal Dashboard</a><span>/</span><strong>Barangay Directory</strong></nav>
                <h1 id="municipal-directory-title">Tomas Oppus Barangay Directory</h1>
                <p>Municipal overview of barangay registry activity, assigned secretaries, RBI reports, and approved GCash collection profiles.</p>
            </div>
            <a class="button government-outline-button" href="{{ route('dashboard.municipal') }}">Back to Municipal Dashboard</a>
        </div>

        <div class="municipal-directory-summary-grid">
            <article><span>Total Barangays</span><strong>{{ $barangays->count() }}</strong><small>Official barangays of Tomas Oppus</small></article>
            <article class="has-reports"><span>With RBI Reports</span><strong>{{ $barangaysWithReports }}</strong><small>Barangays with received submissions</small></article>
            <article class="needs-report"><span>Awaiting Reports</span><strong>{{ $barangays->count() - $barangaysWithReports }}</strong><small>No submitted RBI report on record</small></article>
            <article><span>Approved Secretaries</span><strong>{{ $activeSecretaries }}</strong><small>Authorized barangay accounts</small></article>
        </div>

        <div class="municipal-directory-toolbar">
            <div class="directory-search-field">
                <label for="barangay-directory-search">Search the barangay list</label>
                <input id="barangay-directory-search" type="search" placeholder="Enter barangay or local name..." autocomplete="off">
            </div>
            <div class="directory-filter-group" role="group" aria-label="Filter barangays by report status">
                <button type="button" class="is-active" data-directory-filter="all">All Barangays</button>
                <button type="button" data-directory-filter="reported">With Reports</button>
                <button type="button" data-directory-filter="missing">Awaiting Reports</button>
            </div>
            <span id="barangay-directory-count">Showing {{ $barangays->count() }} barangays</span>
        </div>

        <div class="municipal-directory-grid" id="municipal-directory-grid">
            @foreach ($barangays as $index => $barangay)
                @php
                    $barangayReports = $rbiUpdates->where('barangay_name', $barangay->name);
                    $latestReport = $barangayReports->first();
                    $hasReports = $barangayReports->isNotEmpty();
                @endphp
                <article class="municipal-directory-card {{ $hasReports ? 'has-reports' : 'needs-report' }}" data-directory-card data-status="{{ $hasReports ? 'reported' : 'missing' }}" data-name="{{ str($barangay->name.' '.$barangay->localName())->lower() }}">
                    <header>
                        <span class="barangay-number">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        <div><small>Barangay</small><h2>{{ $barangay->name }}</h2><p>{{ $barangay->localName() ?: 'Tomas Oppus, Southern Leyte' }}</p></div>
                        <span class="account-status {{ $hasReports ? 'is-active' : 'is-pending' }}"><span class="status-dot"></span>{{ $hasReports ? 'Reporting' : 'Awaiting report' }}</span>
                    </header>

                    <dl class="directory-card-metrics">
                        <div><dt>Inhabitants</dt><dd>{{ number_format($barangay->inhabitants_count) }}</dd></div>
                        <div><dt>Households</dt><dd>{{ number_format($barangay->households_count) }}</dd></div>
                        <div><dt>RBI Reports</dt><dd>{{ number_format($barangayReports->count()) }}</dd></div>
                    </dl>

                    <div class="directory-card-office">
                        <span>Approved secretary accounts</span><strong>{{ $barangay->secretaries_count }}</strong>
                    </div>

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

                    @if ($latestReport)
                        <div class="directory-latest-report">
                            <span>Latest municipal submission</span>
                            <strong>{{ optional($latestReport->reporting_month)->format('F Y') ?: 'Reporting month not set' }}</strong>
                            <small>Received {{ optional($latestReport->submitted_at)->format('M d, Y · h:i A') }}</small>
                            <div class="row-actions"><a href="{{ route('rbi-updates.show', $latestReport) }}">Review latest</a><a href="{{ route('rbi-updates.export-pdf', $latestReport) }}">PDF</a></div>
                        </div>
                    @else
                        <div class="directory-missing-report"><strong>No RBI report received</strong><span>The next submitted monthly form will appear here automatically.</span></div>
                    @endif

                    @if ($hasReports)
                        <details class="directory-report-history">
                            <summary>View all {{ $barangayReports->count() }} received report{{ $barangayReports->count() === 1 ? '' : 's' }}</summary>
                            <div>
                                @foreach ($barangayReports as $report)
                                    <article>
                                        <div><strong>{{ optional($report->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><span>{{ collect($report->rows ?? [])->pluck('household_head')->filter()->unique()->count() }} families · {{ count($report->rows ?? []) }} members</span></div>
                                        <div class="row-actions"><a href="{{ route('rbi-updates.show', $report) }}">View</a><a href="{{ route('rbi-updates.export-pdf', $report) }}">PDF</a><a href="{{ route('rbi-updates.export-word', $report) }}">Word</a></div>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>

        <div class="empty-directory" id="empty-directory" hidden>No barangay matches the selected search and status filter.</div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const search = document.getElementById('barangay-directory-search');
            const cards = [...document.querySelectorAll('[data-directory-card]')];
            const filters = [...document.querySelectorAll('[data-directory-filter]')];
            const count = document.getElementById('barangay-directory-count');
            const empty = document.getElementById('empty-directory');
            let activeFilter = 'all';

            const refresh = () => {
                const query = search.value.trim().toLowerCase();
                let visible = 0;

                cards.forEach((card) => {
                    const matchesSearch = card.dataset.name.includes(query);
                    const matchesStatus = activeFilter === 'all' || card.dataset.status === activeFilter;
                    card.hidden = !matchesSearch || !matchesStatus;
                    if (!card.hidden) visible++;
                });

                count.textContent = `Showing ${visible} ${visible === 1 ? 'barangay' : 'barangays'}`;
                empty.hidden = visible !== 0;
            };

            search.addEventListener('input', refresh);
            filters.forEach((button) => button.addEventListener('click', () => {
                activeFilter = button.dataset.directoryFilter;
                filters.forEach((item) => item.classList.toggle('is-active', item === button));
                refresh();
            }));
        })();
    </script>
@endpush
