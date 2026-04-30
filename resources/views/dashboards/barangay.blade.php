@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Barangay Records Desk</div>
        <h1>Barangay Dashboard</h1>
        <p>{{ auth()->user()->name }} is signed in as {{ auth()->user()->roleLabel() }}.</p>

        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="meta-grid">
            <div class="meta">
                <strong>Access tier</strong>
                Barangay
            </div>
            <div class="meta">
                <strong>Permissions</strong>
                Barangay and resident areas
            </div>
            <div class="meta">
                <strong>Session</strong>
                Authenticated
            </div>
        </div>

        @if (auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
            <div class="workflow-card">
                <div class="workflow-head">
                    <div>
                        <span class="step-pill">Step 1</span>
                        <h2 class="section-title">Upload RBI Monthly Update</h2>
                        <p>Upload the completed workbook, then review the entries in the editable registry table.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('barangay.rbi-updates.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="form-grid">
                        <div>
                            <label for="barangay_name">Barangay</label>
                            <input id="barangay_name" name="barangay_name" type="text" value="{{ old('barangay_name') }}" required>
                        </div>
                        <div>
                            <label for="reporting_month">Reporting month</label>
                            <input id="reporting_month" name="reporting_month" type="date" value="{{ old('reporting_month') }}" required>
                        </div>
                        <div>
                            <label for="as_of_date">As of date</label>
                            <input id="as_of_date" name="as_of_date" type="date" value="{{ old('as_of_date') }}">
                        </div>
                        <div>
                            <label for="prepared_by">Prepared by</label>
                            <input id="prepared_by" name="prepared_by" type="text" value="{{ old('prepared_by', auth()->user()->name) }}">
                        </div>
                        <div>
                            <label for="attested_by">Attested by</label>
                            <input id="attested_by" name="attested_by" type="text" value="{{ old('attested_by') }}" placeholder="Punong Barangay / Authorized Official">
                        </div>
                        <div>
                            <label for="source_file">RBI workbook</label>
                            <input id="source_file" name="source_file" type="file" accept=".xlsx,.xls,.csv" required>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit">Upload Draft</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($draftRbiUpdate)
            <div class="workflow-card highlight-card">
                <div class="workflow-head">
                    <div>
                        <span class="step-pill">Step 2</span>
                        <h2 class="section-title">Review and Submit RBI Update</h2>
                        <p>Edit the table, certification names, and dates. Use “Save and Submit” to send the current entries to Municipal LGU in one action.</p>
                    </div>
                    <span class="badge">Draft</span>
                </div>

                <form method="POST" action="{{ route('barangay.rbi-updates.update', $draftRbiUpdate) }}" id="rbi-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="form-grid">
                        <div>
                            <label for="draft_barangay_name">Barangay</label>
                            <input id="draft_barangay_name" name="barangay_name" type="text" value="{{ old('barangay_name', $draftRbiUpdate->barangay_name) }}" required>
                        </div>
                        <div>
                            <label for="draft_reporting_month">Reporting month</label>
                            <input id="draft_reporting_month" name="reporting_month" type="date" value="{{ old('reporting_month', optional($draftRbiUpdate->reporting_month)->format('Y-m-d')) }}" required>
                        </div>
                        <div>
                            <label for="draft_as_of_date">As of date</label>
                            <input id="draft_as_of_date" name="as_of_date" type="date" value="{{ old('as_of_date', optional($draftRbiUpdate->as_of_date)->format('Y-m-d')) }}">
                        </div>
                        <div>
                            <label for="draft_prepared_by">Prepared by</label>
                            <input id="draft_prepared_by" name="prepared_by" type="text" value="{{ old('prepared_by', $draftRbiUpdate->prepared_by ?: auth()->user()->name) }}">
                        </div>
                        <div>
                            <label for="draft_attested_by">Attested by</label>
                            <input id="draft_attested_by" name="attested_by" type="text" value="{{ old('attested_by', $draftRbiUpdate->attested_by) }}" placeholder="Punong Barangay / Authorized Official">
                        </div>
                        <div>
                            <label>RBI file</label>
                            <div class="toolbar compact-toolbar">
                                <a class="button secondary-button" href="{{ route('rbi-updates.show', $draftRbiUpdate) }}">View Edited</a>
                                <a class="button secondary-button" href="{{ route('rbi-updates.download', $draftRbiUpdate) }}">Original</a>
                            </div>
                        </div>
                    </div>

                    <div class="table-wrap form-table-wrap">
                        <table id="rbi-rows-table">
                            <thead>
                                <tr>
                                    @foreach ($rbiRowFields as $label)
                                        <th>{{ $label }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (old('rows', $draftRbiUpdate->rows ?: []) as $index => $row)
                                    <tr>
                                        @foreach ($rbiRowFields as $field => $label)
                                            <td>
                                                @if ($field === 'sex')
                                                    <select name="rows[{{ $index }}][{{ $field }}]" aria-label="{{ $label }}">
                                                        <option value=""></option>
                                                        <option value="Male" @selected(($row[$field] ?? '') === 'Male')>Male</option>
                                                        <option value="Female" @selected(($row[$field] ?? '') === 'Female')>Female</option>
                                                    </select>
                                                @elseif ($field === 'birth_date')
                                                    <input name="rows[{{ $index }}][{{ $field }}]" type="date" value="{{ $row[$field] ?? '' }}" aria-label="{{ $label }}">
                                                @else
                                                    <input name="rows[{{ $index }}][{{ $field }}]" type="text" value="{{ $row[$field] ?? '' }}" aria-label="{{ $label }}">
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-actions split-actions">
                        <button type="button" class="secondary-button" id="add-rbi-row">Add Row</button>
                        <div class="toolbar compact-toolbar">
                            <button class="secondary-button" type="submit">Save Draft</button>
                            <button type="submit" name="submit_to_municipal" value="1">Save and Submit to Municipal LGU</button>
                        </div>
                    </div>
                </form>
            </div>
        @endif

        <div class="workflow-card">
            <h2 class="section-title">RBI Update History</h2>

            @if ($rbiUpdates->isEmpty())
                <p>No RBI updates uploaded yet.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Reporting Month</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Files</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rbiUpdates as $update)
                                <tr>
                                    <td>{{ $update->barangay_name ?: 'Not set' }}</td>
                                    <td>{{ optional($update->reporting_month)->format('M Y') ?: 'Not set' }}</td>
                                    <td><span class="badge">{{ $update->statusLabel() }}</span></td>
                                    <td>{{ optional($update->submitted_at)->format('M d, Y h:i A') ?: 'Not submitted' }}</td>
                                    <td class="row-actions">
                                        <a href="{{ route('rbi-updates.show', $update) }}">View edited</a>
                                        <a href="{{ route('rbi-updates.export-edited', $update) }}">Download edited</a>
                                        @if ($update->source_file_path)
                                            <a href="{{ route('rbi-updates.download', $update) }}">Original</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>

    <script>
        const addRowButton = document.getElementById('add-rbi-row');
        const rowsTable = document.getElementById('rbi-rows-table');
        const fields = @json($rbiRowFields);

        if (addRowButton && rowsTable) {
            addRowButton.addEventListener('click', () => {
                const tbody = rowsTable.querySelector('tbody');
                const rowIndex = tbody.querySelectorAll('tr').length;
                const row = document.createElement('tr');

                Object.entries(fields).forEach(([field, label]) => {
                    const cell = document.createElement('td');

                    if (field === 'sex') {
                        const select = document.createElement('select');
                        select.name = `rows[${rowIndex}][${field}]`;
                        select.setAttribute('aria-label', label);
                        ['', 'Male', 'Female'].forEach((value) => {
                            const option = document.createElement('option');
                            option.value = value;
                            option.textContent = value;
                            select.appendChild(option);
                        });
                        cell.appendChild(select);
                    } else {
                        const input = document.createElement('input');
                        input.name = `rows[${rowIndex}][${field}]`;
                        input.type = field === 'birth_date' ? 'date' : 'text';
                        input.setAttribute('aria-label', label);
                        cell.appendChild(input);
                    }

                    row.appendChild(cell);
                });

                tbody.appendChild(row);
            });
        }
    </script>
@endsection
