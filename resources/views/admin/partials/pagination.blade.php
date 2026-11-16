{{-- Compact admin pagination (dark brown + gold). Same paginator data/links as Laravel's default view. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="admin-pagination" data-admin-pagination>
        <p>Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ number_format($paginator->total()) }}</p>

        <ul>
            <li>
                @if ($paginator->onFirstPage())
                    <span class="admin-page-link" aria-disabled="true" aria-label="Previous page">&lsaquo;</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="admin-page-link" aria-label="Previous page">&lsaquo;</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="admin-page-link" aria-disabled="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="admin-page-link" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="admin-page-link" aria-label="Page {{ $page }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="admin-page-link" aria-label="Next page">&rsaquo;</a>
                @else
                    <span class="admin-page-link" aria-disabled="true" aria-label="Next page">&rsaquo;</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
