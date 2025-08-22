@if($tabs && count($tabs) > 1)
<div class="menu-tabs menu-tabs-{{ $variant ?? 'tabs' }} menu-tabs-{{ $alignment ?? 'left' }}">
    <ul class="nav nav-{{ $variant ?? 'tabs' }}" role="tablist">
        @foreach($tabs as $tab)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $tab['is_current'] ? 'active' : '' }}" 
                   href="{{ $tab['url'] }}"
                   {{ $tab['is_current'] ? 'aria-current="page"' : '' }}>
                    @if(isset($tab['icon']) && $tab['icon'])
                        <i class="{{ $tab['icon'] }}"></i>
                    @endif
                    {{ $tab['title'] }}
                </a>
            </li>
        @endforeach
    </ul>
</div>

<style>
.menu-tabs {
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #dee2e6;
}

.menu-tabs-center {
    text-align: center;
}

.menu-tabs-center .nav {
    justify-content: center;
}

.menu-tabs-right {
    text-align: right;
}

.menu-tabs-right .nav {
    justify-content: flex-end;
}

.menu-tabs .nav-tabs {
    border-bottom: none;
    margin-bottom: -1px;
}

.menu-tabs .nav-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    background: none;
    color: #6c757d;
    padding: 0.75rem 1rem;
    margin-bottom: 0;
    transition: all 0.2s ease;
}

.menu-tabs .nav-tabs .nav-link:hover {
    border-bottom-color: #08c2b7;
    color: #08c2b7;
    background: none;
}

.menu-tabs .nav-tabs .nav-link.active {
    border-bottom-color: #08c2b7;
    color: #08c2b7;
    background: none;
    font-weight: 500;
}

.menu-tabs .nav-pills .nav-link {
    border-radius: 0.375rem;
    color: #6c757d;
    background: none;
    transition: all 0.2s ease;
}

.menu-tabs .nav-pills .nav-link:hover {
    background-color: rgba(8, 194, 183, 0.1);
    color: #08c2b7;
}

.menu-tabs .nav-pills .nav-link.active {
    background-color: #08c2b7;
    color: white;
}

.menu-tabs-underline .nav {
    border-bottom: 1px solid #dee2e6;
}

.menu-tabs-underline .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    background: none;
    color: #6c757d;
    padding: 0.75rem 0;
    margin-right: 2rem;
    margin-bottom: -1px;
    border-radius: 0;
    transition: all 0.2s ease;
}

.menu-tabs-underline .nav-link:hover {
    border-bottom-color: #08c2b7;
    color: #08c2b7;
    background: none;
}

.menu-tabs-underline .nav-link.active {
    border-bottom-color: #08c2b7;
    color: #08c2b7;
    background: none;
    font-weight: 600;
}

.menu-tabs .nav-link i {
    margin-right: 0.5rem;
}
</style>
@endif
