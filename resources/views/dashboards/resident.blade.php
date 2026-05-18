@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="dashboard-hero">
            <div>
                <div class="page-kicker">Resident Service Portal</div>
                <h1>Resident Dashboard</h1>
                <p>Request barangay documents, track processing status, and keep your local service transactions in one clean workspace.</p>
            </div>
            <div class="hero-side">
                <div class="hero-mini-card">
                    <strong>{{ number_format($documentRequests->count()) }}</strong>
                    <span>Total form requests</span>
                </div>
                <div class="hero-mini-card">
                    <strong>{{ number_format($documentRequests->where('status', App\Models\DocumentRequest::STATUS_PENDING)->count()) }}</strong>
                    <span>Pending requests</span>
                </div>
            </div>
        </div>

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

        <div class="stat-grid">
            <div class="stat-card">
                <span class="stat-label">Access Tier</span>
                <span class="stat-value">RES</span>
                <span class="stat-note">Resident service area</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Requests</span>
                <span class="stat-value">{{ number_format($documentRequests->count()) }}</span>
                <span class="stat-note">All submitted forms</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Pending</span>
                <span class="stat-value">{{ number_format($documentRequests->where('status', App\Models\DocumentRequest::STATUS_PENDING)->count()) }}</span>
                <span class="stat-note">Awaiting review</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Session</span>
                <span class="stat-value">Live</span>
                <span class="stat-note">{{ auth()->user()->name }}</span>
            </div>
        </div>

        @if (auth()->user()->hasRole(App\Models\User::ROLE_RESIDENT))
            <div class="workflow-card">
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

        <div class="workflow-card">
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
