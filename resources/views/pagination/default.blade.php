@if ($paginator->hasPages())
    <nav aria-label="{{ t('Pagination Navigation') }}" class="d-flex justify-content-center align-items-center pagination-nav my-4">
        {{-- Per Page Selector --}}
        {{-- 
        <div class="pagination-info">
            <small class="text-muted">
                {{ t('Showing') }} 
                <strong>{{ $paginator->firstItem() ?? 0 }}</strong> 
                {{ t('to') }} 
                <strong>{{ $paginator->lastItem() ?? 0 }}</strong> 
                {{ t('of') }} 
                <strong>{{ $paginator->total() }}</strong> 
                {{ t('results') }}
            </small>
        </div>
        --}}
        
        <div class="per-page-selector me-2">
            <form method="GET" class="input-group input-group-sm">
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
                
                <label for="per_page" class="input-group-text">
                    <small>{{ t('Per page') }}</small>
                </label>
                <select name="per_page" id="per_page" class="form-select" style="width: auto;" onchange="this.form.submit()">
                    <option value="10" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 10 ? 'selected' : '' }}>10</option>
                    <option value="20" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 20 ? 'selected' : '' }}>20</option>
                    <option value="30" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 30 ? 'selected' : '' }}>30</option>
                    <option value="50" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page', config('sitemanager.ui.pagination_per_page', 20)) == 100 ? 'selected' : '' }}>100</option>
                </select>
            </form>
        </div>

        <ul class="pagination justify-content-center mb-0">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled" aria-disabled="true" aria-label="{{ t('Previous') }}">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ t('Previous') }}">&lsaquo;</a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ t('Next') }}">&rsaquo;</a>
                </li>
            @else
                <li class="page-item disabled" aria-disabled="true" aria-label="{{ t('Next') }}">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
