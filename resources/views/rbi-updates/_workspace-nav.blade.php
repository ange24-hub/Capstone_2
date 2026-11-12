<nav class="rbi-subpages" aria-label="RBI Forms subpages">
    <a href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}" @if($current === 'form') aria-current="page" @endif>Monthly form</a>
    <a href="{{ route('barangay.rbi-updates.history') }}" @if($current === 'history') aria-current="page" @endif>Report history</a>
    <a href="{{ route('rbi.arrivals.index') }}" @if($current === 'arrivals') aria-current="page" @endif>3-month arrivals</a>
</nav>
