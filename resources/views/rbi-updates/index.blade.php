@extends('layouts.app')

@push('head')<link rel="stylesheet" href="{{ asset('css/rbi-workspace.css') }}?v={{ filemtime(public_path('css/rbi-workspace.css')) }}">@endpush

@section('content')
    @php
        $editingReport = $draftRbiUpdate;
        $formRows = array_map([\App\Support\HouseholdRbi::class, 'normalize'], old('rows', $editingReport?->rows ?: [['household_head' => '']]));
        $memberFields = collect($rbiRowFields)->except('household_head')->all();
        $formFamilies = [];

        foreach ($formRows as $row) {
            $householdHead = trim((string) ($row['household_head'] ?? ''));
            $householdId = (string) ($row['household_id'] ?? '');
            $familyKey = $householdId !== '' ? 'household:'.$householdId : ($householdHead !== '' ? mb_strtolower($householdHead) : '__blank_family__');
            $formFamilies[$familyKey] ??= ['household_id' => $householdId, 'household_head' => $householdHead, 'household_number' => $row['household_number'] ?? '', 'members' => []];
            unset($row['household_head'], $row['household_id']);
            $formFamilies[$familyKey]['members'][] = $row;
        }

        if ($formFamilies === []) {
            $formFamilies = [['household_id' => '', 'household_head' => '', 'household_number' => '', 'members' => [[]]]];
        }

        $rowInputIndex = 0;
        $formDeceasedRows = old('deceased_rows', $editingReport?->deceased_rows ?: array_fill(0, 3, []));
        $submittedReport = session('submitted_rbi_update_id')
            ? $rbiUpdates->firstWhere('id', (int) session('submitted_rbi_update_id'))
            : null;
        $defaultReportingMonth = request()->boolean('new')
            ? ''
            : (optional($editingReport?->reporting_month)->format('Y-m') ?: now()->format('Y-m'));
    @endphp

    <section class="panel  rbi-forms-workspace rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
        <div class="dashboard-hero rounded-xl border border-slate-200 bg-white bg-none shadow-sm border-l-4 border-l-blue-600 p-5 sm:p-6">
            <div>
                <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">RBI Monthly Reporting</div>
                <h1>RBI Forms</h1>
                <p>{{ $barangay?->name }} &middot; Manage household updates, prepare monthly reports, and submit to Municipal LGU.</p>
                <div class="hero-actions flex flex-wrap gap-3">
                    <a class="button" href="{{ route('dashboard.barangay') }}">Back to Dashboard</a>
                    @if ($editingReport)
                        <a class="button secondary-button" href="{{ route('barangay.rbi-updates.index', ['new' => 1]) }}">Start Another Month</a>
                    @endif
                </div>
            </div>
            <div class="hero-side">
                <div class="hero-mini-card rounded-xl border border-blue-100 bg-blue-50 p-4 text-blue-900"><strong>{{ $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)->count() }}</strong><span>Submitted monthly forms</span></div>
                <div class="hero-mini-card rounded-xl border border-blue-100 bg-blue-50 p-4 text-blue-900"><strong>{{ $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_DRAFT)->count() }}</strong><span>Draft monthly forms</span></div>
            </div>
        </div>

        <nav class="rbi-section-nav" aria-label="RBI form sections">
            <a href="#monthly-report"><span>01</span> Monthly report</a>
            <a href="#family-section"><span>02</span> Family members</a>
            <a href="#certification-section"><span>03</span> Certification</a>
            <a href="#report-history"><span>04</span> Report history</a>
        </nav>
        @if (session('status'))
            <div class="status-message">{{ session('status') }}</div>
        @endif
        @if ($submittedReport)
            <div class="workflow-card highlight-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="submitted-report">
                <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <span class="step-pill">Submission Complete</span>
                        <h2 class="section-title text-lg font-semibold text-slate-900">{{ optional($submittedReport->reporting_month)->format('F Y') }} RBI form is now displayed in the records</h2>
                        <p>Municipal LGU has received this form. A copy also remains in the secretary's Monthly RBI Form History below.</p>
                    </div>
                    <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $submittedReport->statusLabel() }}</span>
                </div>
                <div class="toolbar flex flex-wrap items-end gap-3">
                    <a class="button" href="{{ route('rbi-updates.show', $submittedReport) }}">View Submitted Form</a>
                    <a class="button secondary-button" href="{{ route('rbi-updates.export-pdf', $submittedReport) }}">Download Consolidated PDF</a>
                    <a class="button secondary-button" href="{{ route('rbi-updates.export-word', $submittedReport) }}">Download Word Copy</a>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <strong>Please check the monthly form:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $barangay)
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">This secretary account is not assigned to a barangay.</div>
        @else
            <div class="workflow-card report-editor rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="monthly-report">
                <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <span class="step-pill">{{ $editingReport ? ($editingReport->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Update Submitted Monthly Form' : 'Continue Monthly Draft') : 'New Monthly Form' }}</span>
                        <h2 class="section-title text-lg font-semibold text-slate-900">Monthly report</h2>
                        <p>Choose a month, add the families, and complete the certification before saving.</p>
                    </div>
                    @if ($editingReport)<span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $editingReport->statusLabel() }}</span>@endif
                </div>

                <form method="POST" action="{{ $editingReport ? route('barangay.rbi-updates.update', $editingReport) : route('barangay.rbi-updates.store') }}" id="rbi-monthly-form">
                    @csrf
                    @if ($editingReport) @method('PUT') @endif

                    <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                        <div><label>Barangay</label><input type="text" value="{{ $barangay->name }}" readonly></div>
                        <div>
                            <label for="reporting_month">For the month of</label>
                            <input id="reporting_month" name="reporting_month" type="month" value="{{ old('reporting_month', $defaultReportingMonth) }}" required>
                            @if (request()->boolean('new'))
                                <small class="field-help">Select the month for the new report. To add entries to an already submitted month, use “Update form” in the history below.</small>
                            @endif
                        </div>
                    </div>

                    <h3 class="section-title text-lg font-semibold text-slate-900" id="family-section">Family members</h3>
                    <div id="family-forms" class=" grid gap-6">
                        @foreach ($formFamilies as $family)
                            <article class="workflow-card family-entry-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" data-family-card>
                                <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                                    <div>
                                        <span class="step-pill" data-family-number>Family {{ $loop->iteration }}</span>
                                        <h4 class="section-title text-lg font-semibold text-slate-900">Household Information</h4>
                                    </div>
                                    <button class="secondary-button" type="button" data-remove-family @disabled(count($formFamilies) === 1)>Remove Family</button>
                                </div>

                                <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                                    <div>
                                        <label>Household head</label>
                                        <input type="text" value="{{ $family['household_head'] }}" list="rbi-household-heads" data-family-head required autocomplete="off">
                                        <input type="hidden" value="{{ $family['household_id'] }}" data-family-household-id><label>Household number</label><input value="{{ $family['household_number'] ?? '' }}" data-family-household-number maxlength="100" placeholder="Existing household number or leave blank for a new household">
                                        <small class="field-help">Choose a registry match when available. A typed name is allowed for a newly registered household.</small>
                                    </div>
                                </div>

                                <h5 class="section-title text-lg font-semibold text-slate-900">Newly Registered Family Members</h5>
                                <div class=" grid gap-6" data-family-members>
                                    @foreach (($family['members'] ?: [[]]) as $member)
                                        @php($currentRowIndex = $rowInputIndex++)
                                        <article class="member-entry-card" data-member-card>
                                            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                                                <strong data-member-number>Member {{ $loop->iteration }}</strong>
                                                <button class="secondary-button" type="button" data-remove-member @disabled(count($family['members']) === 1)>Remove Member</button>
                                            </div>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][household_head]" value="{{ $family['household_head'] }}" data-household-head-hidden>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][household_number]" value="{{ $family['household_number'] ?? '' }}" data-household-number-hidden><input type="hidden" name="rows[{{ $currentRowIndex }}][household_id]" value="{{ $family['household_id'] }}" data-household-id-hidden>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][inhabitant_id]" value="{{ $member['inhabitant_id'] ?? '' }}">
                                            <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                                                @foreach ($memberFields as $field => $label)
                                                    <div>
                                                        <label>{{ $label }}</label>
                                                        @if ($field === 'sex')
                                                            <select name="rows[{{ $currentRowIndex }}][{{ $field }}]">
                                                                <option value=""></option>
                                                                <option value="Male" @selected(($member[$field] ?? '') === 'Male')>Male</option>
                                                                <option value="Female" @selected(($member[$field] ?? '') === 'Female')>Female</option>
                                                            </select>
                                                        @else
                                                            <input name="rows[{{ $currentRowIndex }}][{{ $field }}]" type="{{ $field === 'birth_date' ? 'date' : ($field === 'recorded_age' ? 'number' : 'text') }}" value="{{ $member[$field] ?? '' }}">
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                                <div class="form-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button class="secondary-button" type="button" data-add-member>Add Member to This Family</button></div>
                            </article>
                        @endforeach
                    </div>
                    <div class="form-actions split-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <small class="field-help">Each family becomes a complete RBI form/page inside one monthly PDF.</small>
                        <button type="button" id="add-family-form">Add Another Family Form</button>
                    </div>

                    <h3 class="section-title text-lg font-semibold text-slate-900">Deceased inhabitants</h3>
                    <div class="table-wrap form-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                        <table id="rbi-deceased-rows-table">
                            <thead><tr><th>Household / Family</th>@foreach ($rbiDeceasedRowFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($formDeceasedRows as $index => $row)
                                    <tr>
                                        <td><select name="deceased_rows[{{ $index }}][household_head]" data-deceased-family data-selected="{{ $row['household_head'] ?? '' }}"><option value="">Select family</option></select></td>
                                        @foreach ($rbiDeceasedRowFields as $field => $label)<td><input name="deceased_rows[{{ $index }}][{{ $field }}]" type="{{ $field === 'death_date' ? 'date' : 'text' }}" value="{{ $row[$field] ?? '' }}" aria-label="{{ $label }}"></td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button type="button" class="secondary-button" id="add-rbi-deceased-row">Add Deceased Inhabitant</button></div>

                    <h3 class="section-title text-lg font-semibold text-slate-900" id="certification-section">Monthly Form Certification</h3>
                    <p>The following names and signatures are repeated on every family form in the consolidated PDF.</p>
                    <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                        <div><label for="certified_by">Certified Correct (Barangay Secretary)</label><input id="certified_by" name="certified_by" value="{{ old('certified_by', $editingReport?->certified_by ?: $barangay->secretary_name ?: auth()->user()->name) }}"></div>
                        <div>
                            <label for="prepared_by">Prepared by (BHW / Encoder)</label>
                            <input id="prepared_by" name="prepared_by" type="text" value="{{ old('prepared_by', $editingReport?->prepared_by ?: $barangay->secretary_name ?: auth()->user()->name) }}">
                        </div>
                        <div>
                            <label for="attested_by">Verified by (Punong Barangay)</label>
                            <input id="attested_by" name="attested_by" type="text" value="{{ old('attested_by', $editingReport?->attested_by ?: $barangay->punong_barangay_name) }}" placeholder="Punong Barangay name">
                        </div>
                        @foreach ([['prepared_signature_data', 'prepared_signature_path', 'Prepared by', 'secretary'], ['certified_signature_data', 'certified_signature_path', 'Certified Correct', 'certified'], ['attested_signature_data', 'attested_signature_path', 'Punong Barangay', 'captain']] as [$field, $pathField, $label, $type])
                            <div class="signature-pad-field">
                                <label>{{ $label }} signature</label>
                                @if ($editingReport?->{$pathField})
                                    <img class="signature-upload-preview" src="{{ route('rbi-updates.signature', [$editingReport, $type]) }}" alt="Saved {{ $label }} signature">
                                    <small class="field-help">Saved signature. Draw below only to replace it.</small>
                                @endif
                                <div class="signature-pad" data-signature-pad>
                                    <canvas aria-label="Draw the {{ $label }} signature"></canvas>
                                    <div class="signature-pad-actions"><span>Sign using a mouse, finger, or stylus.</span><button class="secondary-button" type="button" data-clear-signature>Clear</button></div>
                                    <input name="{{ $field }}" type="hidden">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="form-actions split-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <span>Save your changes first, then submit the saved report from Monthly RBI Form History below.</span>
                        <div class="toolbar compact-toolbar flex flex-wrap items-end gap-3">
                            <button class="secondary-button" type="submit">{{ $editingReport?->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Save Updated Form' : 'Save Monthly Draft' }}</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        <div class="workflow-card report-history rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="report-history">
            <div class="history-heading"><div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Saved reports</div><h2 class="section-title text-lg font-semibold text-slate-900">Monthly RBI Form History</h2></div>
            <p>Review saved reports, add members to Consolidated RBI, or submit a completed monthly form.</p>
            @include('rbi-updates._saved-inhabitants')
            @if ($rbiUpdates->isEmpty() && $newInhabitantRecords->isEmpty())
                <p>No monthly RBI forms created yet.</p>
            @endif
            @if ($rbiUpdates->isNotEmpty())
                <div class="table-wrap official-report-history w-full overflow-x-auto rounded-xl border border-slate-200">
                    <table>
                        <thead><tr><th>Month</th><th>Families</th><th>Inhabitants</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($rbiUpdates as $report)
                                @php($familyCount = collect($report->rows ?? [])->pluck('household_head')->filter()->unique()->count())
                                <tr>
                                    <td>{{ optional($report->reporting_month)->format('F Y') ?: 'Not set' }}</td>
                                    <td>{{ $familyCount }}</td>
                                    <td>{{ count($report->rows ?? []) }}</td>
                                    <td><span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $report->statusLabel() }}</span></td>
                                    <td>{{ optional($report->submitted_at)->format('M d, Y h:i A') ?: 'Not submitted' }}</td>
                                    <td><div class="rbi-history-actions">
                                        <a href="{{ route('rbi-updates.show', $report) }}">View form</a>
                                        <a href="{{ route('barangay.rbi-updates.index', ['edit' => $report->id]) }}">{{ $report->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue draft' : 'Update form' }}</a>
                                        <a href="{{ route('rbi-updates.export-pdf', $report) }}">Download PDF</a>
                                        <a href="{{ route('rbi-updates.export-word', $report) }}">Download Word</a>
                                        @include('rbi-updates._registry-action', ['report' => $report])
                                        @include('rbi-updates._submit-action', ['report' => $report])
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <datalist id="rbi-household-heads">
        @foreach ($rbiHouseholds as $household)
            <option value="{{ $household['label'] }} [Household {{ $household['household_number'] }}]">Household {{ $household['household_number'] }}</option>
        @endforeach
    </datalist>

    <template id="member-entry-template">
        <article class="member-entry-card" data-member-card>
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <strong data-member-number>Member</strong>
                <button class="secondary-button" type="button" data-remove-member>Remove Member</button>
            </div>
            <input type="hidden" data-row-field="household_head" data-household-head-hidden>
            <input type="hidden" data-row-field="household_number" data-household-number-hidden><input type="hidden" data-row-field="household_id" data-household-id-hidden>
            <input type="hidden" data-row-field="inhabitant_id">
            <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                @foreach ($memberFields as $field => $label)
                    <div>
                        <label>{{ $label }}</label>
                        @if ($field === 'sex')
                            <select data-row-field="{{ $field }}"><option value=""></option><option value="Male">Male</option><option value="Female">Female</option></select>
                        @else
                            <input data-row-field="{{ $field }}" type="{{ $field === 'birth_date' ? 'date' : ($field === 'recorded_age' ? 'number' : 'text') }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </article>
    </template>

    <template id="family-form-template">
        <article class="workflow-card family-entry-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" data-family-card>
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div><span class="step-pill" data-family-number></span><h4 class="section-title text-lg font-semibold text-slate-900">Household Information</h4></div>
                <button class="secondary-button" type="button" data-remove-family>Remove Family</button>
            </div>
            <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2"><div><label>Household head</label><input type="text" list="rbi-household-heads" data-family-head required autocomplete="off"><input type="hidden" data-family-household-id><label>Household number</label><input data-family-household-number maxlength="100" placeholder="Existing household number or leave blank for a new household"><small class="field-help">Choose a registry match when available.</small></div></div>
            <h5 class="section-title text-lg font-semibold text-slate-900">Newly Registered Family Members</h5>
            <div class=" grid gap-6" data-family-members></div>
            <div class="form-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button class="secondary-button" type="button" data-add-member>Add Member to This Family</button></div>
        </article>
    </template>

    <script>
        (() => {
            const familyForms = document.getElementById('family-forms');
            const familyTemplate = document.getElementById('family-form-template');
            const memberTemplate = document.getElementById('member-entry-template');
            const existingHouseholds = @json($rbiHouseholds);
            let nextRowIndex = {{ $rowInputIndex }};

            const syncHouseholdHead = (familyCard) => {
                const headInput = familyCard.querySelector('[data-family-head]');
                const enteredHead = headInput.value.trim();
                const currentId = familyCard.querySelector('[data-family-household-id]').value;
                const matches = existingHouseholds.filter((household) =>
                    household.label.toLocaleLowerCase() === enteredHead.toLocaleLowerCase()
                    || `${household.label} [Household ${household.household_number}]`.toLocaleLowerCase() === enteredHead.toLocaleLowerCase());
                const selectedHousehold = matches.length === 1 ? matches[0] : matches.find((household) => String(household.id) === currentId);
                const originalId = familyCard.querySelector('[data-family-household-id]').defaultValue;
                const retainedHousehold = enteredHead === headInput.defaultValue.trim()
                    ? existingHouseholds.find((household) => String(household.id) === originalId) : null;
                const matchedHousehold = selectedHousehold || retainedHousehold;
                const householdHead = selectedHousehold ? selectedHousehold.label : enteredHead;
                if (selectedHousehold) headInput.value = `${selectedHousehold.label} [Household ${selectedHousehold.household_number}]`;
                const householdId = matchedHousehold ? matchedHousehold.id : '';
                const numberInput = familyCard.querySelector('[data-family-household-number]');
                if (matchedHousehold) numberInput.value = matchedHousehold.household_number;
                numberInput.readOnly = Boolean(matchedHousehold);
                familyCard.querySelectorAll('[data-household-number-hidden]').forEach(input => input.value = numberInput.value.trim());
                familyCard.querySelector('[data-family-household-id]').value = householdId;
                familyCard.querySelectorAll('[data-household-head-hidden]').forEach((input) => input.value = householdHead);
                familyCard.querySelectorAll('[data-household-id-hidden]').forEach((input) => input.value = householdId);
                refreshDeceasedFamilyOptions();
            };

            const refreshDeceasedFamilyOptions = () => {
                const heads = [...familyForms.querySelectorAll('[data-family-card]')].map((card) => card.querySelector('[data-household-head-hidden]')?.value.trim()).filter(Boolean);
                document.querySelectorAll('[data-deceased-family]').forEach((select) => {
                    const selected = select.value || select.dataset.selected || '';
                    select.innerHTML = '<option value="">Select family</option>' + heads.map((head) => {
                        const safeHead = head.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
                        return `<option value="${safeHead}">${safeHead}</option>`;
                    }).join('');
                    select.value = heads.includes(selected) ? selected : (heads.length === 1 ? heads[0] : '');
                    select.dataset.selected = '';
                });
            };

            const refreshMemberNumbers = (familyCard) => {
                const members = [...familyCard.querySelectorAll('[data-member-card]')];
                members.forEach((member, index) => {
                    member.querySelector('[data-member-number]').textContent = `Member ${index + 1}`;
                    member.querySelector('[data-remove-member]').disabled = members.length === 1;
                });
            };

            const refreshFamilyNumbers = () => {
                const families = [...familyForms.querySelectorAll('[data-family-card]')];
                families.forEach((family, index) => {
                    family.querySelector('[data-family-number]').textContent = `Family ${index + 1}`;
                    family.querySelector('[data-remove-family]').disabled = families.length === 1;
                    refreshMemberNumbers(family);
                });
                refreshDeceasedFamilyOptions();
            };

            const addMember = (familyCard) => {
                const member = memberTemplate.content.firstElementChild.cloneNode(true);
                const rowIndex = nextRowIndex++;
                member.querySelectorAll('[data-row-field]').forEach((input) => {
                    input.name = `rows[${rowIndex}][${input.dataset.rowField}]`;
                });
                familyCard.querySelector('[data-family-members]').appendChild(member);
                syncHouseholdHead(familyCard);
                refreshMemberNumbers(familyCard);
                member.querySelector('input:not([type="hidden"]), select')?.focus();
            };

            document.getElementById('add-family-form')?.addEventListener('click', () => {
                const family = familyTemplate.content.firstElementChild.cloneNode(true);
                familyForms.appendChild(family);
                addMember(family);
                refreshFamilyNumbers();
                family.querySelector('[data-family-head]').focus();
                family.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });

            familyForms?.addEventListener('input', (event) => {
                if (event.target.matches('[data-family-head], [data-family-household-number]')) syncHouseholdHead(event.target.closest('[data-family-card]'));
            });

            familyForms?.addEventListener('click', (event) => {
                const familyCard = event.target.closest('[data-family-card]');
                if (! familyCard) return;

                if (event.target.closest('[data-add-member]')) addMember(familyCard);

                if (event.target.closest('[data-remove-member]') && familyCard.querySelectorAll('[data-member-card]').length > 1) {
                    event.target.closest('[data-member-card]').remove();
                    refreshMemberNumbers(familyCard);
                }

                if (event.target.closest('[data-remove-family]') && familyForms.querySelectorAll('[data-family-card]').length > 1) {
                    familyCard.remove();
                    refreshFamilyNumbers();
                }
            });

            document.getElementById('rbi-monthly-form')?.addEventListener('submit', () => {
                familyForms.querySelectorAll('[data-family-card]').forEach(syncHouseholdHead);
            });

            refreshFamilyNumbers();
            familyForms?.querySelectorAll('[data-family-card]').forEach(syncHouseholdHead);

            document.getElementById('add-rbi-deceased-row')?.addEventListener('click', () => {
                const body = document.querySelector('#rbi-deceased-rows-table tbody');
                const index = body.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.insertAdjacentHTML('beforeend', '<td><select name="deceased_rows[' + index + '][household_head]" data-deceased-family><option value="">Select family</option></select></td>');
                @foreach ($rbiDeceasedRowFields as $field => $label)
                    row.insertAdjacentHTML('beforeend', `<td><input name="deceased_rows[${index}][{{ $field }}]" type="{{ $field === 'death_date' ? 'date' : 'text' }}" aria-label="{{ $label }}"></td>`);
                @endforeach
                body.appendChild(row);
                refreshDeceasedFamilyOptions();
                row.querySelector('input')?.focus();
            });

            const initializeSignaturePad = (pad) => {
                const canvas = pad.querySelector('canvas');
                const input = pad.querySelector('input[type="hidden"]');
                const context = canvas.getContext('2d');
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                let drawing = false;
                let signed = false;
                const bounds = canvas.getBoundingClientRect();
                canvas.width = Math.max(Math.round(bounds.width * ratio), 1);
                canvas.height = Math.max(Math.round(bounds.height * ratio), 1);
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
                context.strokeStyle = '#073353';
                context.lineWidth = 2.25;
                context.lineCap = 'round';
                context.lineJoin = 'round';
                const point = (event) => { const rect = canvas.getBoundingClientRect(); return { x: event.clientX - rect.left, y: event.clientY - rect.top }; };
                canvas.addEventListener('pointerdown', (event) => { event.preventDefault(); drawing = true; const p = point(event); context.beginPath(); context.moveTo(p.x, p.y); context.lineTo(p.x + .01, p.y + .01); context.stroke(); });
                canvas.addEventListener('pointermove', (event) => { if (!drawing) return; event.preventDefault(); const p = point(event); context.lineTo(p.x, p.y); context.stroke(); });
                const finish = () => { if (!drawing) return; drawing = false; signed = true; input.value = canvas.toDataURL('image/png'); };
                canvas.addEventListener('pointerup', finish);
                canvas.addEventListener('pointercancel', finish);
                pad.querySelector('[data-clear-signature]').addEventListener('click', () => { context.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio); input.value = ''; drawing = false; signed = false; });
                pad.closest('form').addEventListener('submit', () => { if (signed) input.value = canvas.toDataURL('image/png'); });
            };

            document.querySelectorAll('[data-signature-pad]').forEach(initializeSignaturePad);
        })();
    </script>
@endsection
