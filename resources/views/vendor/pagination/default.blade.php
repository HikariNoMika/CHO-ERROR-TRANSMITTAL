{{--
    Custom paginator. Laravel's default "tailwind" view emits utility classes,
    but this app ships no Tailwind build, so those links rendered unstyled.
    This markup matches .pager / .pager-nav in layouts/app.blade.php and is
    used by every paginated table automatically.
--}}
@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination Navigation">
        <div class="pager-info">
            Showing <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ $paginator->total() }}</strong>
        </div>

        <div class="pager-nav">
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-hidden="true">Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Prev</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="ellipsis">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="is-disabled" aria-hidden="true">Next</span>
            @endif
        </div>
    </nav>
@endif