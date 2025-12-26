<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- 검색엔진 크롤링 차단 -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, nocache">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">

    <title>@yield('title', 'SiteManager Panel') - Site Manager</title>
    
    {!! setResources(['jquery', 'bootstrap', 'sweetalert']) !!}
    {!! resource('sitemanager::css/sitemanager/sitemanager.css') !!}
    {!! resource('sitemanager::css/pagination.css') !!}
    {!! resource('sitemanager::js/sitemanager/sitemanager.js') !!}
    {!! resource('sitemanager::js/notifications.js') !!}
    
    @stack('styles')
    @yield('head')
</head>

<body>
    <div id="page-loader" class="fade show">
        <span class="spinner"></span>
    </div>
    
    <header class="sticky-top">
        <div class="container">
            <nav>
                {{-- Left: Logo --}}
                <div class="nav-section nav-left">
                    <a href="{{ route('sitemanager.dashboard') }}">
                        <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="logo">
                    </a>
                </div>
                
                {{-- Center: Main Menus (PC only) --}}
                <div class="nav-section nav-center d-none d-lg-flex">
                    <ul>
                        {{-- <li>
                            <a @class(['active' => request()->routeIs('sitemanager.dashboard')]) 
                                href="{{ route('sitemanager.dashboard') }}">
                                <i class="bi bi-speedometer2"></i>
                                {{ t('Dashboard') }}
                            </a>
                        </li> --}}

                        <li>
                            <a @class(['active' => request()->routeIs('sitemanager.menus.*')]) 
                                href="{{ route('sitemanager.menus.index') }}">
                                <i class="bi bi-list"></i>
                                {{ t('Menus') }}
                            </a>
                        </li>

                        <li class="dropdown">
                            <a @class(['nav-link dropdown-toggle', 'active' => request()->routeIs('sitemanager.members.*') || request()->routeIs('sitemanager.groups.*')]) 
                                href="#" id="membersDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-people"></i>
                                {{ t('Members') }}
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="membersDropdown">
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.members.*')]) 
                                        href="{{ route('sitemanager.members.index') }}">
                                        <i class="bi bi-people"></i>
                                        {{ t('Members') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.groups.*')]) 
                                        href="{{ route('sitemanager.groups.index') }}">
                                        <i class="bi bi-collection"></i>
                                        {{ t('Groups') }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        <li class="dropdown">
                            <a @class(['nav-link dropdown-toggle', 'active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*') || request()->routeIs('sitemanager.files.*')]) 
                                href="#" id="boardsDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-journal-text"></i>
                                {{ t('Boards') }}
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="boardsDropdown">
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*')]) 
                                        href="{{ route('sitemanager.boards.index') }}">
                                        <i class="bi bi-journal-text"></i>
                                        {{ t('Boards') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.files.editor-images')]) 
                                        href="{{ route('sitemanager.files.editor-images') }}">
                                        <i class="bi bi-image"></i>
                                        {{ t('Editor Images') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.files.board-attachments')]) 
                                        href="{{ route('sitemanager.files.board-attachments') }}">
                                        <i class="bi bi-paperclip"></i>
                                        {{ t('Board Attachments') }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                        
                        {{-- Extension Menus --}}
                        @if(isset($extensionMenuItems) && count($extensionMenuItems) > 0)
                            @foreach($extensionMenuItems as $ext)
                                @php
                                    // children이 있으면 dropdown 메뉴
                                    $hasChildren = isset($ext['children']) && is_array($ext['children']) && count($ext['children']) > 0;
                                    
                                    if ($hasChildren) {
                                        // children 중 하나라도 active면 parent도 active
                                        $isActive = false;
                                        foreach ($ext['children'] as $child) {
                                            $childRouteBase = Str::beforeLast($child['route'], '.');
                                            if (request()->routeIs($childRouteBase . '.*')) {
                                                $isActive = true;
                                                break;
                                            }
                                        }
                                        $dropdownId = 'extension-' . $ext['key'] . '-dropdown';
                                    } else {
                                        $routeBase = Str::beforeLast($ext['route'], '.');
                                        $isActive = request()->routeIs($routeBase . '.*');
                                    }
                                @endphp
                                
                                @if($hasChildren)
                                    <li class="dropdown">
                                        <a @class(['nav-link dropdown-toggle', 'active' => $isActive])
                                            href="#" id="{{ $dropdownId }}" role="button" data-bs-toggle="dropdown">
                                            <i class="{{ $ext['icon'] }}"></i>
                                            {{ t($ext['name']) }}
                                        </a>
                                        <ul class="dropdown-menu" aria-labelledby="{{ $dropdownId }}">
                                            @foreach($ext['children'] as $child)
                                                @php
                                                    $childRouteBase = Str::beforeLast($child['route'], '.');
                                                    $childActive = request()->routeIs($childRouteBase . '.*');
                                                @endphp
                                                <li>
                                                    <a @class(['dropdown-item', 'active' => $childActive])
                                                        href="{{ route($child['route']) }}">
                                                        <i class="{{ $child['icon'] }}"></i>
                                                        {{ t($child['name']) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </li>
                                @else
                                    <li>
                                        <a @class(['active' => $isActive])
                                            href="{{ route($ext['route']) }}">
                                            <i class="{{ $ext['icon'] }}"></i>
                                            {{ t($ext['name']) }}
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        @endif
                    </ul>
                </div>

                {{-- Right: User & Home (PC) --}}
                <div class="nav-section nav-right d-none d-lg-flex">
                    <ul>
                        <li class="dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                @if(auth()->user()->profile_photo)
                                    <img src="{{ auth()->user()->profile_photo_url }}" 
                                            alt="{{ auth()->user()->name }}'s profile photo" 
                                            class="admin-profile-photo">
                                @else
                                    <i class="bi bi-person-circle"></i>
                                @endif
                                {{ auth()->user()->name }}
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <li>
                                    <a class="dropdown-item" href="{{ route('sitemanager.members.edit', auth()->user()->id) }}">
                                        <i class="bi bi-person"></i>{{ t('Profile') }}
                                    </a>
                                </li>

                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.languages.*')])
                                        href="{{ route('sitemanager.languages.index') }}">
                                        <i class="bi bi-translate"></i>
                                        {{ t('Languages') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['dropdown-item', 'active' => request()->routeIs('sitemanager.settings.*')])
                                        href="{{ route('sitemanager.settings') }}">
                                        <i class="bi bi-gear"></i>
                                        {{ t('System Settings') }}
                                    </a>
                                </li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="bi bi-box-arrow-right"></i> {{ t('Logout') }}
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>

                        <li>
                            <a href="/">
                                <i class="bi bi-house-door"></i>
                            </a>
                        </li>
                    </ul>

                    @if(auth()->check() && auth()->user()->level === 255 && config('sitemanager.language.trace_enabled', false))
                        <button type="button" class="btn btn-sm btn-outline-danger" id="clear-current-page-btn" onclick="clearCurrentPageLocations()" title="{{ t('Clear current page language location information') }}">
                            <i class="bi bi-translate"></i> <i class="bi bi-geo-alt"></i> <i class="bi bi-x"></i>
                        </button>
                    @endif
                </div>

                {{-- Mobile: Hamburger Button --}}
                <button class="hamburger-btn d-lg-none" id="mobile-menu-toggle" type="button" aria-label="Toggle menu">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </nav>

            {{-- Mobile: Slide Menu --}}
            <div class="mobile-menu-overlay d-lg-none" id="mobile-menu-overlay"></div>

            <div class="mobile-menu-slide d-lg-none" id="mobile-menu-slide">
                <div class="mobile-menu-header">
                    <div class="d-flex align-items-center gap-2 mt-1">
                        {{-- <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="logo" style="height: 40px;"> --}}
                        <span class="fw-bold">{{ auth()->user()->name }}</span>
                    </div>
                </div>
                
                <ul class="mobile-menu-list">
                    <li>
                        <a @class(['active' => request()->routeIs('sitemanager.menus.*')]) 
                            href="{{ route('sitemanager.menus.index') }}">
                            <i class="bi bi-list"></i>
                            {{ t('Menus') }}
                        </a>
                    </li>
                    
                    <li class="mobile-menu-dropdown">
                        <a @class(['active' => request()->routeIs('sitemanager.members.*') || request()->routeIs('sitemanager.groups.*')]) 
                            href="#" data-bs-toggle="collapse" data-bs-target="#mobile-members-collapse">
                            <i class="bi bi-people"></i>
                            {{ t('Members') }}
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse" id="mobile-members-collapse">
                            <ul class="mobile-submenu">
                                <li>
                                    <a @class(['active' => request()->routeIs('sitemanager.members.*')]) 
                                        href="{{ route('sitemanager.members.index') }}">
                                        <i class="bi bi-people"></i>
                                        {{ t('Members') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['active' => request()->routeIs('sitemanager.groups.*')]) 
                                        href="{{ route('sitemanager.groups.index') }}">
                                        <i class="bi bi-collection"></i>
                                        {{ t('Groups') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    <li class="mobile-menu-dropdown">
                        <a @class(['active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*') || request()->routeIs('sitemanager.files.*')]) 
                            href="#" data-bs-toggle="collapse" data-bs-target="#mobile-boards-collapse">
                            <i class="bi bi-journal-text"></i>
                            {{ t('Boards') }}
                            <i class="bi bi-chevron-down ms-auto"></i>
                        </a>
                        <div class="collapse" id="mobile-boards-collapse">
                            <ul class="mobile-submenu">
                                <li>
                                    <a @class(['active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*')]) 
                                        href="{{ route('sitemanager.boards.index') }}">
                                        <i class="bi bi-journal-text"></i>
                                        {{ t('Boards') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['active' => request()->routeIs('sitemanager.files.editor-images')]) 
                                        href="{{ route('sitemanager.files.editor-images') }}">
                                        <i class="bi bi-image"></i>
                                        {{ t('Editor Images') }}
                                    </a>
                                </li>
                                <li>
                                    <a @class(['active' => request()->routeIs('sitemanager.files.board-attachments')]) 
                                        href="{{ route('sitemanager.files.board-attachments') }}">
                                        <i class="bi bi-paperclip"></i>
                                        {{ t('Board Attachments') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                    
                    {{-- Extension Menus (Mobile) --}}
                    @if(isset($extensionMenuItems) && count($extensionMenuItems) > 0)
                        @foreach($extensionMenuItems as $ext)
                            @php
                                $hasChildren = isset($ext['children']) && is_array($ext['children']) && count($ext['children']) > 0;
                                
                                if ($hasChildren) {
                                    $isActive = false;
                                    foreach ($ext['children'] as $child) {
                                        $childRouteBase = Str::beforeLast($child['route'], '.');
                                        if (request()->routeIs($childRouteBase . '.*')) {
                                            $isActive = true;
                                            break;
                                        }
                                    }
                                    $mobileDropdownId = 'mobile-extension-' . $ext['key'] . '-collapse';
                                } else {
                                    $routeBase = Str::beforeLast($ext['route'], '.');
                                    $isActive = request()->routeIs($routeBase . '.*');
                                }
                            @endphp
                            
                            @if($hasChildren)
                                <li class="mobile-menu-dropdown">
                                    <a @class(['active' => $isActive])
                                        href="#" data-bs-toggle="collapse" data-bs-target="#{{ $mobileDropdownId }}">
                                        <i class="{{ $ext['icon'] }}"></i>
                                        {{ t($ext['name']) }}
                                        <i class="bi bi-chevron-down ms-auto"></i>
                                    </a>
                                    <div class="collapse" id="{{ $mobileDropdownId }}">
                                        <ul class="mobile-submenu">
                                            @foreach($ext['children'] as $child)
                                                @php
                                                    $childRouteBase = Str::beforeLast($child['route'], '.');
                                                    $childActive = request()->routeIs($childRouteBase . '.*');
                                                @endphp
                                                <li>
                                                    <a @class(['active' => $childActive])
                                                        href="{{ route($child['route']) }}">
                                                        <i class="{{ $child['icon'] }}"></i>
                                                        {{ t($child['name']) }}
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </li>
                            @else
                                <li>
                                    <a @class(['active' => $isActive])
                                        href="{{ route($ext['route']) }}">
                                        <i class="{{ $ext['icon'] }}"></i>
                                        {{ t($ext['name']) }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    @endif

                    <li class="mobile-menu-divider"></li>

                    <li>
                        <a href="{{ route('sitemanager.members.edit', auth()->user()->id) }}">
                            <i class="bi bi-person"></i>
                            {{ t('Profile') }}
                        </a>
                    </li>
                    <li>
                        <a @class(['active' => request()->routeIs('sitemanager.languages.*')])
                            href="{{ route('sitemanager.languages.index') }}">
                            <i class="bi bi-translate"></i>
                            {{ t('Languages') }}
                        </a>
                    </li>
                    <li>
                        <a @class(['active' => request()->routeIs('sitemanager.settings.*')])
                            href="{{ route('sitemanager.settings') }}">
                            <i class="bi bi-gear"></i>
                            {{ t('System Settings') }}
                        </a>
                    </li>
                    <li>
                        <a href="/">
                            <i class="bi bi-house-door"></i>
                            {{ t('Home') }}
                        </a>
                    </li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="mobile-menu-logout">
                                <i class="bi bi-box-arrow-right"></i>
                                {{ t('Logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    
    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>

    <footer>
        © {{ date('Y') }} <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="footer-logo"> All rights reserved.
    </footer>

    <script>
        // SweetAlert2 notifications
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: '{{ t("Success") }}!',
                html: {!! json_encode(session('success')) !!},
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        @endif

        @if(session('error'))
            Swal.fire({
                icon: 'error',
                title: '{{ t("Error") }}!',
                html: {!! json_encode(session('error')) !!},
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: true,
                toast: true,
                position: 'top-end'
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: '{{ t("Input Error") }}!',
                html: `
                    <ul style="text-align: left; margin: 0; padding-left: 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                `,
                timer: 5000,
                timerProgressBar: true,
                showConfirmButton: true,
                toast: true,
                position: 'top-end'
            });
        @endif
    </script>

    @if(auth()->check() && auth()->user()->level === 255)
    <script>
        function clearCurrentPageLocations() {
            // 현재 페이지 위치 정보 가져오기
            let currentLocation = 'unknown';
            
            try {
                // 1. 현재 라우트 이름 시도
                const routeName = '{{ request()->route()?->getName() }}';
                if (routeName) {
                    currentLocation = routeName;
                } else {
                    // 2. URL 경로에서 추출
                    const path = window.location.pathname;
                    currentLocation = path.replace(/^\//, '').replace(/\//g, '.');
                }
            } catch (error) {
                console.error('Error getting current location:', error);
            }

            if (!currentLocation || currentLocation === 'unknown') {
                Swal.fire({
                    icon: 'warning',
                    title: '{{ t("Cannot determine current page location") }}',
                    text: '{{ t("Unable to identify the current page location") }}',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
                return;
            }

            Swal.fire({
                title: '{{ t("Clear Current Page Location") }}',
                text: `{{ t("Are you sure you want to clear location information for") }} "${currentLocation}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: '{{ t("Yes, clear it") }}',
                cancelButtonText: '{{ t("Cancel") }}'
            }).then((result) => {
                if (result.isConfirmed) {
                    // 로딩 상태 표시
                    const btn = document.getElementById('clear-current-page-btn');
                    const originalContent = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="animation: spin 1s linear infinite;"></i>';
                    btn.disabled = true;

                    fetch('{{ route("sitemanager.languages.clear-current-page-locations") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            location: currentLocation
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '{{ t("Location Cleared") }}',
                                text: data.message,
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '{{ t("Error") }}',
                                text: data.message || '{{ t("Failed to clear location") }}',
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: '{{ t("Error") }}',
                            text: '{{ t("An error occurred while clearing location") }}',
                            toast: true,
                            position: 'top-end',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    })
                    .finally(() => {
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                    });
                }
            });
        }

        // CSS for spin animation
        if (!document.querySelector('#spin-style')) {
            const style = document.createElement('style');
            style.id = 'spin-style';
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }
    </script>
    @endif

    @stack('scripts')
</body>
</html>
