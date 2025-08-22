@if($breadcrumb && count($breadcrumb) > 0)
<nav aria-label="breadcrumb" class="menu-breadcrumb" style="--breadcrumb-separator: '{{ $separator ?? '/' }}';">
    <ol class="breadcrumb">
        @foreach($breadcrumb as $index => $crumb)
            @if($loop->last)
                <li class="breadcrumb-item active" aria-current="page">
                    <span>{{ $crumb['title'] }}</span>
                </li>
            @else
                <li class="breadcrumb-item">
                    @if($crumb['url'])
                        <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                    @else
                        <span>{{ $crumb['title'] }}</span>
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</nav>

<style>
.menu-breadcrumb {
    margin-bottom: 1rem;
}

.menu-breadcrumb .breadcrumb {
    background: transparent;
    padding: 0;
    margin-bottom: 0;
    font-size: 0.875rem;
}

.menu-breadcrumb .breadcrumb-item {
    color: #6c757d;
}

.menu-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
    content: var(--breadcrumb-separator, "/");
    color: #6c757d;
    margin: 0 0.5rem;
}

.menu-breadcrumb .breadcrumb-item a {
    color: #007bff;
    text-decoration: none;
}

.menu-breadcrumb .breadcrumb-item a:hover {
    color: #0056b3;
    text-decoration: underline;
}

.menu-breadcrumb .breadcrumb-item.active {
    color: #495057;
    font-weight: 500;
}
</style>
@endif
