@extends('layouts.app')

@section('content')
    <section class="dashboard-page workspace-page" aria-labelledby="approval-page-title">
        <header class="dashboard-page-header dashboard-page-header-with-actions flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-transparent bg-none pb-6 shadow-none">
            <x-workspace-heading icon="shield">
                <span class="dashboard-eyebrow text-xs font-semibold uppercase tracking-widest text-blue-700">Account Authorization</span>
                <h1 id="approval-page-title">Barangay secretary approvals</h1>
                <p>Review pending staff registrations before granting access to protected barangay records.</p>
            </x-workspace-heading>
            <div class="dashboard-context-card">
                <span class="context-icon"><x-app-icon name="shield" /></span>
                <div><small>Verification queue</small><strong>{{ $secretaryApprovalRequests->count() }} pending</strong><span>Municipal LGU review</span></div>
            </div>
        </header>

        @if (session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900" role="status">{{ session('status') }}</div>@endif

        <section class="workflow-card focus-workspace-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
            <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                <div><span class="step-pill">Pending Review</span><h2 class="section-title text-lg font-semibold text-slate-900">Secretary registrations</h2><p>Confirm the staff identity and assigned barangay before approval.</p></div>
                <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $secretaryApprovalRequests->count() }} pending</span>
            </div>
            @if ($secretaryApprovalRequests->isEmpty())
                <div class="dashboard-empty-state rounded-xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-slate-600"><span class="empty-icon"><x-app-icon name="check" /></span><strong>Approval queue is clear</strong><span>New secretary registrations will appear here automatically.</span></div>
            @else
                <div class="municipal-approval-list">
                    @foreach ($secretaryApprovalRequests as $secretary)
                        <article class="municipal-approval-item rounded-xl border border-slate-200 bg-white bg-none shadow-sm p-5">
                            <div class="approval-identity"><span class="account-avatar">{{ str($secretary->name)->substr(0, 1)->upper() }}</span><div><strong>{{ $secretary->name }}</strong><span>{{ $secretary->email }}</span></div></div>
                            <div class="approval-details"><div><span>User ID</span><strong>{{ $secretary->staff_id ?: 'Not provided' }}</strong></div><div><span>Barangay</span><strong>{{ $secretary->barangay?->name ?? 'Not assigned' }}</strong></div><div><span>Registered</span><strong>{{ $secretary->created_at->format('M d, Y') }}</strong></div></div>
                            <div class="approval-actions flex flex-wrap items-center gap-2">
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
