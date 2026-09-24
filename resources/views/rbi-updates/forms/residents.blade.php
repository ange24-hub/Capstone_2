                <form method="POST" action="{{ $editingReport ? route('barangay.rbi-updates.update', $editingReport) : route('barangay.rbi-updates.store') }}" id="rbi-monthly-form" autocomplete="off">
                    @csrf
                    <input type="hidden" name="form_section" value="residents">
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
                                                        @if (in_array($field, ['civil_status', 'education_level', 'religion'], true))
                                                            <x-rbi-dropdown :field="$field" :name="'rows['.$currentRowIndex.']['.$field.']'" :value="$member[$field] ?? ''" />
                                                        @elseif ($field === 'sex')
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

                    @include('rbi-updates.forms._certification')

                    <div class="form-actions split-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <span>Save your changes first, then submit the saved report from Report history.</span>
                        <div class="toolbar compact-toolbar flex flex-wrap items-end gap-3">
                            <button class="secondary-button" type="submit">{{ $editingReport?->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Save Updated Residents' : 'Save Residents' }}</button>
                        </div>
                    </div>
                </form>
