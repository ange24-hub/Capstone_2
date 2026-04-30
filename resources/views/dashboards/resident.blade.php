@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Resident Services</div>
        <h1>Resident Dashboard</h1>
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
                Resident
            </div>
            <div class="meta">
                <strong>Permissions</strong>
                Resident area
            </div>
            <div class="meta">
                <strong>Session</strong>
                Authenticated
            </div>
        </div>

        @if (auth()->user()->hasRole(App\Models\User::ROLE_RESIDENT))
            <div>
                <h2 class="section-title">Request a Form</h2>
                <p>Select the barangay form you need and submit your purpose for processing.</p>

                <form method="POST" action="{{ route('resident.document-requests.store') }}">
                    @csrf

                    <label for="document_type">Form type</label>
                    <select id="document_type" name="document_type" required>
                        <option value="">Select a form</option>
                        @foreach ($documentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('document_type') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    <label for="purpose">Purpose</label>
                    <textarea id="purpose" name="purpose" maxlength="255" required>{{ old('purpose') }}</textarea>

                    <div class="form-actions">
                        <button type="submit">Submit Request</button>
                    </div>
                </form>
            </div>
        @endif

        <div>
            <h2 class="section-title">My Form Requests</h2>

            @if ($documentRequests->isEmpty())
                <p>No form requests yet.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Form</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documentRequests as $request)
                                <tr>
                                    <td>{{ $request->typeLabel() }}</td>
                                    <td>{{ $request->purpose }}</td>
                                    <td><span class="badge">{{ $request->statusLabel() }}</span></td>
                                    <td>{{ $request->created_at->format('M d, Y h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
