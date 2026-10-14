@props(['status' => 'neutral'])

@php
    $tone = match($status) {
        'pending', 'draft', 'under_review', 'pending_verification', 'unpaid' => 'warning',
        'approved', 'completed', 'released', 'resolved', 'paid', 'verified' => 'success',
        'rejected', 'cancelled' => 'danger',
        'processing', 'in_progress', 'ready', 'submitted' => 'info',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['badge', $status, 'civic-status', 'civic-status--'.$tone]) }}>{{ $slot }}</span>
