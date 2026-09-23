                    <h3 class="section-title text-lg font-semibold text-slate-900" id="certification-section">Monthly Form Certification</h3>
                    <p>Enter each signer's full name and place their signature in the same card. The document follows this same order.</p>
                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                        @foreach ([['prepared_by', 'prepared_signature_data', 'prepared_signature_path', 'Prepared by', 'BHW / Encoder', 'secretary', $editingReport?->prepared_by ?? ''], ['certified_by', 'certified_signature_data', 'certified_signature_path', 'Certified Correct', 'Barangay Secretary', 'certified', $editingReport?->certified_by ?: $barangay->secretary_name ?: auth()->user()->name], ['attested_by', 'attested_signature_data', 'attested_signature_path', 'Verified by', 'Barangay Captain / Punong Barangay', 'captain', $editingReport?->attested_by ?: $barangay->punong_barangay_name]] as [$nameField, $field, $pathField, $label, $role, $type, $defaultName])
                            <section class="signature-pad-field flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4" aria-labelledby="{{ $nameField }}-heading">
                                <div class="min-h-16"><p class="text-xs font-semibold uppercase tracking-wide text-blue-800">{{ $label }}</p><h4 class="text-base font-bold text-slate-900" id="{{ $nameField }}-heading">{{ $role }}</h4></div>
                                <div><label for="{{ $nameField }}">Full name</label><input id="{{ $nameField }}" name="{{ $nameField }}" type="text" value="{{ old($nameField, $rbiSection === 'residents' && ! $editingReport ? '' : $defaultName) }}" placeholder="Full name of {{ $role }}" maxlength="255"></div>
                                <label>{{ $role }} signature</label>
                                @if ($editingReport?->{$pathField})
                                    <img class="signature-upload-preview" src="{{ route('rbi-updates.signature', [$editingReport, $type]) }}" alt="Saved {{ $role }} signature">
                                    <small class="field-help">Saved signature. Draw below only to replace it.</small>
                                @endif
                                <div class="signature-pad mt-auto" data-signature-pad>
                                    <canvas aria-label="Draw the {{ $role }} signature"></canvas>
                                    <div class="signature-pad-actions"><span>Sign using a mouse, finger, or stylus.</span><button class="secondary-button" type="button" data-clear-signature>Clear</button></div>
                                    <input name="{{ $field }}" type="hidden">
                                </div>
                            </section>
                        @endforeach
                    </div>

