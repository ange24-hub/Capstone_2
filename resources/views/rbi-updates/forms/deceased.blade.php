                <form method="POST" action="{{ $editingReport ? route('barangay.rbi-updates.update', $editingReport) : route('barangay.rbi-updates.store') }}" id="rbi-monthly-form">
                    @csrf
                    <input type="hidden" name="form_section" value="deceased">
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

                    <h3 class="section-title text-lg font-semibold text-slate-900" id="deceased-section">Deceased inhabitants</h3>
                    <div class="table-wrap form-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                        <table id="rbi-deceased-rows-table">
                            <thead><tr><th>Household / Family</th>@foreach ($rbiDeceasedRowFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($formDeceasedRows as $index => $row)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="deceased_rows[{{ $index }}][household_id]" value="{{ $row['household_id'] ?? '' }}" data-deceased-household-id>
                                            <input type="hidden" name="deceased_rows[{{ $index }}][inhabitant_id]" value="{{ $row['inhabitant_id'] ?? '' }}">
                                            <select name="deceased_rows[{{ $index }}][household_head]" data-deceased-family data-selected="{{ $row['household_head'] ?? '' }}" aria-label="Household for deceased inhabitant {{ $index + 1 }}">
                                                <option value="">Select family</option>
                                                @foreach ($rbiHouseholds as $household)
                                                    <option value="{{ $household['label'] }}" data-household-id="{{ $household['id'] }}" @selected((string) ($row['household_id'] ?? '') === (string) $household['id'])>{{ $household['label'] }} (Household {{ $household['household_number'] }})</option>
                                                @endforeach
                                                @if (!empty($row['household_head']) && empty($row['household_id']))<option value="{{ $row['household_head'] }}" selected>{{ $row['household_head'] }}</option>@endif
                                            </select>
                                        </td>
                                        @foreach ($rbiDeceasedRowFields as $field => $label)<td><input name="deceased_rows[{{ $index }}][{{ $field }}]" type="{{ $field === 'death_date' ? 'date' : 'text' }}" value="{{ $row[$field] ?? '' }}" aria-label="{{ $label }}"></td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button type="button" class="secondary-button" id="add-rbi-deceased-row">Add Deceased Inhabitant</button></div>

                    @include('rbi-updates.forms._certification')

                    <div class="form-actions split-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <span>Save your changes first, then submit the saved report from Monthly RBI Form History below.</span>
                        <div class="toolbar compact-toolbar flex flex-wrap items-end gap-3">
                            <button class="secondary-button" type="submit">{{ $editingReport?->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Save Updated Deceased Records' : 'Save Deceased Records' }}</button>
                        </div>
                    </div>
                </form>
