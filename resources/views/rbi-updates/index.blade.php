@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-workspace.css') }}?v={{ filemtime(public_path('css/rbi-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/rbi-report-view.css') }}?v={{ filemtime(public_path('css/rbi-report-view.css')) }}">
@endpush

@section('content')
    @php
        $editingReport = ($rbiSection ?? '') === 'history' ? null : $draftRbiUpdate;
        $rbiSection = $rbiSection ?? 'residents';
        $rbiFormRoute = $rbiSection === 'deceased' ? 'barangay.rbi-updates.deceased' : 'barangay.rbi-updates.residents';
        $sectionParameters = $editingReport ? ['edit' => $editingReport->id] : (request()->boolean('new') ? ['new' => 1] : []);
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
        $defaultReportingMonth = request()->boolean('new') || ($rbiSection === 'residents' && ! $editingReport)
            ? ''
            : (optional($editingReport?->reporting_month)->format('Y-m') ?: now()->format('Y-m'));
    @endphp

    <section class="panel rbi-forms-workspace">
        <div class="rbi-report-view rbi-workspace-overview">
            <header class="rbi-report-header">
                <div class="rbi-report-heading">
                    <span class="rbi-report-emblem" aria-hidden="true"><x-app-icon name="document" /></span>
                    <div>
                        <span class="rbi-report-eyebrow">RBI Monthly Form</span>
                        <h1>{{ $rbiSection === 'history' ? 'Report history' : ($rbiSection === 'deceased' ? 'Deceased inhabitants' : 'Add residents') }}</h1>
                        <p>Barangay {{ $barangay?->name ?: 'not set' }} <span aria-hidden="true">&middot;</span> Monthly reporting</p>
                    </div>
                </div>
                <div class="rbi-report-downloads" aria-label="Workspace actions">
                    <a class="rbi-report-button rbi-report-pdf" href="{{ route('dashboard.barangay') }}">Back to Dashboard</a>
                    @if ($editingReport)
                        <a class="rbi-report-button rbi-report-word" href="{{ route($rbiFormRoute, ['new' => 1]) }}">Start Another Month</a>
                    @endif
                </div>
            </header>
            <div class="rbi-report-metrics" aria-label="Monthly reports summary">
                <article class="rbi-report-metric rbi-metric-residents">
                    <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="check" /></span>
                    <div><span class="rbi-metric-label">Submitted reports</span><strong>{{ $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)->count() }}</strong></div>
                </article>
                <article class="rbi-report-metric rbi-metric-status">
                    <span class="rbi-metric-icon" aria-hidden="true"><x-app-icon name="document" /></span>
                    <div><span class="rbi-metric-label">Draft reports</span><strong>{{ $rbiUpdates->where('status', App\Models\BarangayRbiUpdate::STATUS_DRAFT)->count() }}</strong></div>
                </article>
            </div>
        </div>

        <nav class="rbi-subpages" aria-label="RBI Forms subpages">
            <a href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}" @if ($rbiSection === 'residents') aria-current="page" @endif><x-app-icon name="users" /><span>Add residents</span></a>
            <a href="{{ route('barangay.rbi-updates.deceased', $sectionParameters) }}" @if ($rbiSection === 'deceased') aria-current="page" @endif><x-app-icon name="document" /><span>Deceased inhabitants</span></a>
            <a href="{{ route('barangay.rbi-updates.history') }}" @if($rbiSection === 'history') aria-current="page" @endif><x-app-icon name="calendar" /><span>Report history</span></a>
        </nav>
        @if($rbiSection !== 'history')
        <nav class="rbi-section-nav" aria-label="RBI form sections">
            <a href="#monthly-report"><span>01</span> Monthly report</a>
            <a href="#{{ $rbiSection === 'deceased' ? 'deceased-section' : 'family-section' }}"><span>02</span> {{ $rbiSection === 'deceased' ? 'Deceased inhabitants' : 'Family members' }}</a>
            <a href="#certification-section"><span>03</span> Certification</a>
            <a href="{{ route('barangay.rbi-updates.history') }}"><span>04</span> Report history</a>
        </nav>
        @endif
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
                    <x-status-badge :status="$submittedReport->status">{{ $submittedReport->statusLabel() }}</x-status-badge>
                </div>
                <div class="toolbar flex flex-wrap items-end gap-3">
                    <a class="button" href="{{ route('rbi-updates.show', $submittedReport) }}">View Submitted Form</a>
                    <a class="button secondary-button" href="{{ route('rbi-updates.export-pdf', $submittedReport) }}">Download Consolidated PDF</a>
                    <a class="button secondary-button" href="{{ route('rbi-updates.export-word', $submittedReport) }}">Download Word Copy</a>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div role="alert" class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                <strong>Please check the monthly form:</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (! $barangay)
            <div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">This secretary account is not assigned to a barangay.</div>
        @elseif($rbiSection !== 'history')
            <div class="workflow-card report-editor rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="monthly-report">
                <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <span class="step-pill">{{ $editingReport ? ($editingReport->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Update Submitted Monthly Form' : 'Continue Monthly Draft') : 'New Monthly Form' }}</span>
                        <h2 class="section-title text-lg font-semibold text-slate-900">{{ $rbiSection === 'deceased' ? 'Deceased Inhabitants Form' : 'Add Residents Form' }}</h2>
                        <p>Choose a month, {{ $rbiSection === 'deceased' ? 'record deceased inhabitants' : 'add the families' }}, and complete the certification before saving.</p>
                    </div>
                    @if ($editingReport)<x-status-badge :status="$editingReport->status">{{ $editingReport->statusLabel() }}</x-status-badge>@endif
                </div>

                @if ($rbiSection === 'deceased')
                    @include('rbi-updates.forms.deceased')
                @else
                    @include('rbi-updates.forms.residents')
                @endif
            </div>
        @endif

        @if($rbiSection === 'history')
            @include('rbi-updates._history')
        @endif
    </section>

    <datalist id="rbi-household-heads">
        @foreach ($rbiHouseholds as $household)
            <option value="{{ $household['label'] }} [Household {{ $household['household_number'] }}]">Household {{ $household['household_number'] }}</option>
        @endforeach
    </datalist>

    @if ($rbiSection === 'residents')
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
                        @if (in_array($field, ['civil_status', 'education_level', 'religion'], true))
                            <x-rbi-dropdown :field="$field" :row-field="true" />
                        @elseif ($field === 'sex')
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

    @endif
    <script>
        (() => {
            const monthlyForm = document.getElementById('rbi-monthly-form');
            if (!monthlyForm) return;
            let unsaved = false;
            monthlyForm?.addEventListener('input', () => unsaved = true);
            monthlyForm?.addEventListener('change', () => unsaved = true);
            monthlyForm?.addEventListener('click', event => { if (event.target.closest('[data-remove-family], [data-remove-member], [data-add-member], #add-family-form, #add-rbi-deceased-row, [data-clear-signature]')) unsaved = true; });
            monthlyForm?.addEventListener('submit', () => unsaved = false);
            monthlyForm?.addEventListener('pointerdown', event => { if (event.target.closest('canvas')) unsaved = true; });
            window.addEventListener('beforeunload', event => { if (unsaved) { event.preventDefault(); event.returnValue = ''; } });
            const familyForms = document.getElementById('family-forms');
            const familyTemplate = document.getElementById('family-form-template');
            const memberTemplate = document.getElementById('member-entry-template');
            const existingHouseholds = @json($rbiHouseholds);
            let nextRowIndex = {{ count($formRows) }};

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

            const savedFamilies = @json(collect($editingReport?->rows ?? [])->map(fn ($row) => ['id' => $row['household_id'] ?? '', 'label' => $row['household_head'] ?? '', 'household_number' => $row['household_number'] ?? ''])->unique('label')->values());
            const refreshDeceasedFamilyOptions = () => {
                const families = [...existingHouseholds, ...savedFamilies.filter(family => family.label && !existingHouseholds.some(household => household.label === family.label))];
                document.querySelectorAll('[data-deceased-family]').forEach(select => {
                    const selected = select.dataset.selected || select.value || '';
                    const hiddenId = select.closest('td').querySelector('[data-deceased-household-id]');
                    const selectedId = hiddenId?.value || '';
                    select.replaceChildren(new Option('Select family', ''));
                    families.forEach(family => {
                        const option = new Option(family.label + (family.household_number ? ` (Household ${family.household_number})` : ''), family.label);
                        option.dataset.householdId = family.id || '';
                        select.add(option);
                    });
                    const match = [...select.options].find(option => selectedId ? option.dataset.householdId === selectedId : option.value === selected);
                    if (match) match.selected = true;
                    else if (selected) select.add(new Option(selected, selected, true, true));
                    select.dataset.selected = '';
                });
            };
            document.getElementById('rbi-deceased-rows-table')?.addEventListener('change', event => {
                if (!event.target.matches('[data-deceased-family]')) return;
                event.target.closest('td').querySelector('[data-deceased-household-id]').value = event.target.selectedOptions[0]?.dataset.householdId || '';
            });

            const refreshMemberNumbers = (familyCard) => {
                const members = [...familyCard.querySelectorAll('[data-member-card]')];
                members.forEach((member, index) => {
                    member.querySelector('[data-member-number]').textContent = `Member ${index + 1}`;
                    member.querySelector('[data-remove-member]').disabled = members.length === 1;
                });
            };

            const refreshFamilyNumbers = () => {
                const families = [...(familyForms?.querySelectorAll('[data-family-card]') || [])];
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
                familyForms?.querySelectorAll('[data-family-card]').forEach(syncHouseholdHead);
            });

            refreshFamilyNumbers();
            familyForms?.querySelectorAll('[data-family-card]').forEach(syncHouseholdHead);

            document.getElementById('add-rbi-deceased-row')?.addEventListener('click', () => {
                const body = document.querySelector('#rbi-deceased-rows-table tbody');
                const index = body.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.insertAdjacentHTML('beforeend', '<td><input type="hidden" name="deceased_rows[' + index + '][household_id]" data-deceased-household-id><select aria-label="Household for deceased inhabitant" name="deceased_rows[' + index + '][household_head]" data-deceased-family><option value="">Select family</option></select></td>');
                @foreach ($rbiDeceasedRowFields as $field => $label)
                    row.insertAdjacentHTML('beforeend', `<td><input name="deceased_rows[${index}][{{ $field }}]" type="{{ $field === 'death_date' ? 'date' : 'text' }}" aria-label="{{ $label }}"></td>`);
                @endforeach
                body.appendChild(row);
                refreshDeceasedFamilyOptions();
                row.querySelector('input:not([type="hidden"])')?.focus();
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
    <script src="{{ asset('js/rbi-duplicates.js') }}?v={{ filemtime(public_path('js/rbi-duplicates.js')) }}" defer></script>
@endsection
