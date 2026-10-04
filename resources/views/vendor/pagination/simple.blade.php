{{--
    Simple paginator (Illuminate\Pagination\Paginator, from simplePaginate()).
    Kept separate from default.blade.php because a Paginator has no
    total()/firstItem()/lastItem(), so the length-aware view would throw here.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination Navigation">
        <div class="pager-info">Page <strong>{{ $paginator->currentPage() }}</strong></div>

        <div class="pager-nav">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-hidden="true">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="is-disabled" aria-hidden="true">Next</span>
            @endif
        </div>
    </nav>
@endif