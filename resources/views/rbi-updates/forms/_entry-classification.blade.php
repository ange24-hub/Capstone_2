@php
    $entryTemplate=$entryTemplate ?? false;
    $entryMember=$entryTemplate ? [] : ($member ?? []);
@endphp
<details class="rbi-entry-review" @if(!empty($entryMember['entry_type'])) open @endif><summary>Reason for registration <span>Review entry details</span></summary>
<fieldset class="rbi-entry-classification" data-entry-classification>
    <legend>Reason for this RBI entry</legend>
    <p class="field-help">Classify why this person is being added. A baby who actually moved here can be an in-migrant; birth date alone does not decide this.</p>
    <div class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
        <div><label>Entry type</label>
            <select data-entry-type @if($entryTemplate) data-row-field="entry_type" @else name="rows[{{ $currentRowIndex }}][entry_type]" @endif>
                <option value="">Needs review — not yet confirmed</option>
                @foreach(App\Support\RbiEntryClassification::labels() as $value=>$label)<option value="{{ $value }}" @selected(($entryMember['entry_type'] ?? '')===$value)>{{ $label }}</option>@endforeach
            </select>
            <small class="field-help">You can save unfinished reviews. Unconfirmed entries must be classified before arrival counts can be reviewed.</small>
        </div>
        <div data-arrival-fields><label>Previous barangay / municipality</label><input data-entry-origin @if($entryTemplate) data-row-field="previous_residence" @else name="rows[{{ $currentRowIndex }}][previous_residence]" @endif value="{{ $entryMember['previous_residence'] ?? '' }}" maxlength="255" placeholder="Where the person lived before moving here">
            <label><input type="checkbox" data-entry-origin-unknown @if($entryTemplate) data-row-field="previous_residence_unknown" @else name="rows[{{ $currentRowIndex }}][previous_residence_unknown]" @endif value="1" @checked(filter_var($entryMember['previous_residence_unknown'] ?? false, FILTER_VALIDATE_BOOLEAN))> Previous residence not recorded; confirmed that this person moved here from another place.</label>
        </div>
        <div data-arrival-fields><label>Confirmed arrival month</label><input data-entry-month @if($entryTemplate) data-row-field="arrival_month" @else name="rows[{{ $currentRowIndex }}][arrival_month]" @endif type="month" value="{{ $entryMember['arrival_month'] ?? substr($entryMember['arrival_date'] ?? '',0,7) }}"><small class="field-help">Confirm against the report month. Change the monthly form if the person arrived in another month.</small></div>
        <div data-arrival-fields><label>Exact arrival date (if known)</label><input data-entry-arrival @if($entryTemplate) data-row-field="arrival_date" @else name="rows[{{ $currentRowIndex }}][arrival_date]" @endif type="date" value="{{ $entryMember['arrival_date'] ?? '' }}"><small class="field-help">Leave blank when the history records only the month.</small></div>
    </div>
    <small class="field-help">Birth / Newborn: added because of birth. Late registration: already living here, newly encoded. These are excluded from arrival counts.</small>
</fieldset></details>
