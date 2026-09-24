<nav class="rbi-household-pagination" aria-label="Saved household pages">
    <span>Household <strong>{{ $savedHouseholdPage->firstItem() }}</strong> of {{ $savedHouseholdPage->total() }} · One household per page</span>
    @if($savedHouseholdPage->hasPages())
        <div class="rbi-page-links">
            @if($savedHouseholdPage->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $savedHouseholdPage->previousPageUrl() }}" rel="prev">Previous</a>@endif
            @php($pageNumbers = collect([1, ...range(max(1, $savedHouseholdPage->currentPage() - 1), min($savedHouseholdPage->lastPage(), $savedHouseholdPage->currentPage() + 1)), $savedHouseholdPage->lastPage()])->unique()->sort()->values())
            @foreach($pageNumbers as $number)
                @if($loop->index > 0 && $number > $pageNumbers[$loop->index - 1] + 1)<span aria-hidden="true">…</span>@endif
                @if($number === $savedHouseholdPage->currentPage())<span aria-current="page" aria-label="Page {{ $number }}">{{ $number }}</span>@else<a href="{{ $savedHouseholdPage->url($number) }}" aria-label="Page {{ $number }}">{{ $number }}</a>@endif
            @endforeach
            @if($savedHouseholdPage->hasMorePages())<a href="{{ $savedHouseholdPage->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
        </div>
    @endif
</nav>
