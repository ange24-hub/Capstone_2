<section class="rbi-monthly-section rbi-monthly-deceased" data-rbi-step-panel="deceased">
                    <h3 class="section-title text-lg font-semibold text-slate-900" id="deceased-section"><span class="rbi-section-letter">B</span> Deceased registered inhabitants</h3>
                    <p class="rbi-section-help">List deaths for this reporting month. Leave this section blank when there are none.</p>
                    <div class="table-wrap form-table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                        <table id="rbi-deceased-rows-table">
                            <thead><tr><th>Household / Family</th>@foreach ($rbiDeceasedRowFields as $field => $label)<th>{{ $field === 'deceased_name' ? 'Full name' : $label }}</th>@endforeach</tr></thead>
                            <tbody>
                                @foreach ($formDeceasedRows as $index => $row)
                                    <tr>
                                        <td data-label="Household / Family">
                                            <input type="hidden" name="deceased_rows[{{ $index }}][family_key]" value="{{ $row['family_key'] ?? '' }}" data-deceased-family-key>
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
                                        @foreach ($rbiDeceasedRowFields as $field => $label)<td data-label="{{ $field === 'deceased_name' ? 'Full name' : $label }}"><input name="deceased_rows[{{ $index }}][{{ $field }}]" type="{{ $field === 'death_date' ? 'date' : 'text' }}" value="{{ $row[$field] ?? '' }}" aria-label="{{ $label }}"></td>@endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-actions flex flex-wrap items-center gap-3 border-t border-slate-200 pt-5"><button type="button" class="secondary-button" id="add-rbi-deceased-row">Add Deceased Inhabitant</button></div>

</section>
