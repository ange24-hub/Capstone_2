@if ($paginator->hasPages())
    <nav class="document-pagination" aria-label="Document request pages">
        <span class="document-page-summary">Page <strong>{{ $paginator->currentPage() }}</strong> of <strong>{{ $paginator->lastPage() }}</strong></span>
        <div class="document-page-links">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="document-page-gap" aria-hidden="true">{{ $element }}</span>
                @else
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
