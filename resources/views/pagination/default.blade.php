@if ($paginator->hasPages())
    <nav aria-label="{{ t('Pagination Navigation') }}" class="sitemanager-pagination">

        <div class="per-page-selector me-2">
            <form method="GET">
                {{-- 기존 파라미터 유지 --}}
                @foreach(request()->except(['page', 'per_page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $arrayKey => $arrayValue)
                            <input type="hidden" name="{{ $key }}[{{ $arrayKey }}]" value="{{ $arrayValue }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                
                <select name="per_page" id="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="10" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 10 ? 'selected' : '' }}>10</option>
                    <option value="20" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 20 ? 'selected' : '' }}>20</option>
                    <option value="30" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 30 ? 'selected' : '' }}>30</option>
                    <option value="50" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 100 ? 'selected' : '' }}>100</option>
                </select>
            </form>
        </div>

        <ul class="pagination-list">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link">
                        <i class="bi bi-chevron-left"></i>
                    </span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled">
                        <span class="page-link">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
