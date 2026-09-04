@extends('layouts.app')

@section('content')
    <section class="dashboard-page workspace-page" aria-labelledby="approval-page-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions">
            <div class="dashboard-title-group">
                <span class="dashboard-eyebrow">Account Authorization</span>
                <h1 id="approval-page-title">Barangay secretary approvals</h1>
                <p>Review pending staff registrations before granting access to protected barangay records.</p>
            </div>
            <div class="dashboard-context-card">
                <span class="context-icon"><x-app-icon name="shield" /></span>
                <div><small>Verification queue</small><strong>{{ $secretaryApprovalRequests->count() }} pending</strong><span>Municipal LGU review</span></div>
            </div>
        </header>

        @if (session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif

        <section class="workflow-card focus-workspace-card">
            <div class="workflow-head">
                <div><span class="step-pill">Pending Review</span><h2 class="section-title">Secretary registrations</h2><p>Confirm the staff identity and assigned barangay before approval.</p></div>
                <span class="badge">{{ $secretaryApprovalRequests->count() }} pending</span>
            </div>
            @if ($secretaryApprovalRequests->isEmpty())
                <div class="dashboard-empty-state"><span class="empty-icon"><x-app-icon name="check" /></span><strong>Approval queue is clear</strong><span>New secretary registrations will appear here automatically.</span></div>
            @else
                <div class="municipal-approval-list">
                    @foreach ($secretaryApprovalRequests as $secretary)
                        <article class="municipal-approval-item">
                            <div class="approval-identity"><span class="account-avatar">{{ str($secretary->name)->substr(0, 1)->upper() }}</span><div><strong>{{ $secretary->name }}</strong><span>{{ $secretary->email }}</span></div></div>
                            <div class="approval-details"><div><span>User ID</span><strong>{{ $secretary->staff_id ?: 'Not provided' }}</strong></div><div><span>Barangay</span><strong>{{ $secretary->barangay?->name ?? 'Not assigned' }}</strong></div><div><span>Registered</span><strong>{{ $secretary->created_at->format('M d, Y') }}</strong></div></div>
                            <div class="approval-actions">
                                <form method="POST" action="{{ route('municipal.secretaries.approve', $secretary) }}">@csrf<button type="submit">Approve</button></form>
                                <form method="POST" action="{{ route('municipal.secretaries.reject', $secretary) }}" onsubmit="return confirm('Reject this secretary registration?')">@csrf<button type="submit" class="danger-button">Reject</button></form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </section>
@endsection
