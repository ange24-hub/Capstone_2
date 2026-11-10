                <form method="POST" action="{{ $editingReport ? route('barangay.rbi-updates.update', $editingReport) : route('barangay.rbi-updates.store') }}" id="rbi-monthly-form" data-entry-today="{{ now()->format('Y-m-d') }}" autocomplete="off">
                    @csrf
                    <input type="hidden" name="form_section" value="monthly">
                    @if ($editingReport) @method('PUT') @endif
                    <p class="field-help"><x-required-mark /> Required fields must be filled out.</p>

                    <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2" data-rbi-common-fields>
                        <div><label>Barangay</label><input type="text" value="{{ $barangay->name }}" readonly></div>
                        <div>
                            <label for="reporting_month">For the month of <x-required-mark /></label>
                            <input id="reporting_month" name="reporting_month" type="month" value="{{ old('reporting_month', $defaultReportingMonth) }}" required>
                            @if (request()->boolean('new'))
                                <small class="field-help">Select the month for the new report. To add entries to an already submitted month, use “Update form” in the history below.</small>
                            @endif
                        </div>
                    </div>

                    <section class="rbi-monthly-section rbi-monthly-residents" data-rbi-step-panel="residents">
                    <div class="rbi-section-heading"><div><h3 class="section-title" id="family-section"><span class="rbi-section-letter">A</span> Newly registered inhabitants</h3><p class="rbi-section-help">Group residents by household. Section B follows below on the same monthly form.</p></div>
                    @if(empty($editingReport?->rows))
                        <label class="rbi-no-residents"><input type="checkbox" name="no_new_residents" value="1" data-no-new-residents @checked($noNewResidents)> No new residents this month</label>
                    @endif
                    </div>
                    <fieldset class="rbi-resident-fields" data-resident-fields @disabled($noNewResidents)><legend class="sr-only">Newly registered residents and households</legend>
                    <div id="family-forms" class=" grid gap-6">
                        @foreach ($formFamilies as $family)
                            <article class="workflow-card family-entry-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" data-family-card data-family-key="{{ $family['family_key'] }}">
                                <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                                    <div>
                                        <span class="step-pill" data-family-number>Family {{ $loop->iteration }}</span>
                                        <h4 class="section-title text-lg font-semibold text-slate-900">Household Information</h4>
                                    </div>
                                    <button class="secondary-button" type="button" data-remove-family @disabled(count($formFamilies) === 1)>Remove Family</button>
                                </div>

                                <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                                    <div>
                                        <label data-family-head-label>Household head <span class="rbi-optional-label">Optional</span></label>
                                        <input type="text" value="{{ $family['household_head'] }}" list="rbi-household-heads" data-family-head autocomplete="off">
                                        <input type="hidden" value="{{ $family['household_id'] }}" data-family-household-id></div><div><label>Household number</label><input value="{{ $family['household_number'] ?? '' }}" data-family-household-number maxlength="100" placeholder="Existing household number or leave blank for a new household">
                                        <small class="field-help">Use a known household number, or leave it blank if it is not recorded.</small>
                                        <p class="rbi-household-notice" data-household-match-notice role="status" aria-live="polite" hidden></p>
                                    </div>
                                </div>

                                <h5 class="section-title text-lg font-semibold text-slate-900">Newly Registered Family Members</h5>
                                <div class=" grid gap-6" data-family-members>
                                    @foreach (($family['members'] ?: [[]]) as $member)
                                        @php($currentRowIndex = $rowInputIndex++)
                                        <article class="member-entry-card" data-member-card @if(!empty($member['history_record_id'])) id="rbi-history-entry-{{ $member['history_record_id'] }}" @endif>
                                            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                                                <strong data-member-number>Member {{ $loop->iteration }}</strong>
                                                <button class="secondary-button" type="button" data-remove-member @disabled(count($family['members']) === 1)>Remove Member</button>
                                            </div>
                                            @if(!empty($member['history_record_id']))<p class="field-help">History entry #{{ $member['history_record_id'] }} · {{ trim(implode(' ', array_filter([$member['first_name'] ?? '', $member['middle_name'] ?? '', $member['last_name'] ?? '', $member['suffix'] ?? '']))) }}</p>@endif
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][family_key]" value="{{ $family['family_key'] }}" data-family-key-hidden>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][household_head]" value="{{ $family['household_head'] }}" data-household-head-hidden>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][household_number]" value="{{ $family['household_number'] ?? '' }}" data-household-number-hidden><input type="hidden" name="rows[{{ $currentRowIndex }}][household_id]" value="{{ $family['household_id'] }}" data-household-id-hidden>
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][inhabitant_id]" value="{{ $member['inhabitant_id'] ?? '' }}">
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][history_record_id]" value="{{ $member['history_record_id'] ?? '' }}">
                                            <input type="hidden" name="rows[{{ $currentRowIndex }}][source_row_id]" value="{{ $member['source_row_id'] ?? '' }}">
                                            @include('rbi-updates.forms._entry-classification',['entryTemplate'=>false])
                                            <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
                                                @foreach ($memberFields as $field => $label)
                                                    <div>
                                                        <label>{{ $label }} @if(in_array($field, $requiredMemberFields, true))<x-required-mark />@endif</label>
                                                        @if (in_array($field, ['civil_status', 'education_level', 'religion'], true))
                                                            <x-rbi-dropdown :field="$field" :name="'rows['.$currentRowIndex.']['.$field.']'" :value="$member[$field] ?? ''" :required="in_array($field, $requiredMemberFields, true)" />
                                                        @elseif ($field === 'sex')
                                                            <select name="rows[{{ $currentRowIndex }}][{{ $field }}]" required>
                                                                <option value=""></option>
                                                                <option value="Male" @selected(($member[$field] ?? '') === 'Male')>Male</option>
                                                                <option value="Female" @selected(($member[$field] ?? '') === 'Female')>Female</option>
                                                            </select>
                                                        @else
                                                            <input name="rows[{{ $currentRowIndex }}][{{ $field }}]" type="{{ $field === 'birth_date' ? 'date' : 'text' }}" value="{{ $member[$field] ?? '' }}" @required(in_array($field, $requiredMemberFields, true)) @if($field === 'recorded_age') maxlength="30" placeholder="e.g. 25 or 2 months old" @endif>
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

                    </fieldset></section>
                    @include('rbi-updates.forms._deceased-fields')
                    <section data-rbi-step-panel="certification">
                    @include('rbi-updates.forms._certification')
                    </section>

                    <div class="form-actions split-actions civic-sticky-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5">
                        <span data-rbi-step-status>Complete the monthly form, then save once.</span>
                        <div class="toolbar compact-toolbar flex flex-wrap items-end gap-3">
                            <button class="secondary-button" type="button" data-rbi-previous hidden>Back</button>
                            <button class="button" type="button" data-rbi-next hidden>Continue</button>
                            <button class="button" type="submit" data-rbi-save>{{ $editingReport?->status === App\Models\BarangayRbiUpdate::STATUS_SUBMITTED ? 'Save Updated Monthly Form' : 'Save Monthly Form' }}</button>
                        </div>
                    </div>
                </form>
