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
    
    {{-- Sidebar Overlay --}}
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
    
    {{-- Sidebar Toggle Button (Bookmark style) - Outside sidebar so it stays visible --}}
    <button class="sidebar-toggle-btn" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
        <i class="bi bi-chevron-right"></i>
        <i class="bi bi-chevron-left"></i>
    </button>
    
    {{-- Sidebar --}}
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="{{ route('sitemanager.dashboard') }}" class="sidebar-logo">
                <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="logo">
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="sidebar-menu">
                <li>
                    <a @class(['sidebar-menu-item', 'active' => request()->routeIs('sitemanager.menus.*')]) 
                        href="{{ route('sitemanager.menus.index') }}">
                        <i class="bi bi-list"></i>
                        <span>{{ t('Menus') }}</span>
                    </a>
                </li>

                <li class="sidebar-menu-dropdown">
                    <a @class(['sidebar-menu-item', 'active' => request()->routeIs('sitemanager.members.*') || request()->routeIs('sitemanager.groups.*')]) 
                        href="#" data-bs-toggle="collapse" data-bs-target="#sidebar-members-collapse">
                        <i class="bi bi-people"></i>
                        <span>{{ t('Members') }}</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="sidebar-members-collapse">
                        <ul class="sidebar-submenu">
                            <li>
                                <a @class(['sidebar-submenu-item', 'active' => request()->routeIs('sitemanager.members.*')]) 
                                    href="{{ route('sitemanager.members.index') }}">
                                    <i class="bi bi-people"></i>
                                    <span>{{ t('Members') }}</span>
                                </a>
                            </li>
                            <li>
                                <a @class(['sidebar-submenu-item', 'active' => request()->routeIs('sitemanager.groups.*')]) 
                                    href="{{ route('sitemanager.groups.index') }}">
                                    <i class="bi bi-collection"></i>
                                    <span>{{ t('Groups') }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="sidebar-menu-dropdown">
                    <a @class(['sidebar-menu-item', 'active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*') || request()->routeIs('sitemanager.files.*')]) 
                        href="#" data-bs-toggle="collapse" data-bs-target="#sidebar-boards-collapse">
                        <i class="bi bi-journal-text"></i>
                        <span>{{ t('Boards') }}</span>
                        <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="sidebar-boards-collapse">
                        <ul class="sidebar-submenu">
                            <li>
                                <a @class(['sidebar-submenu-item', 'active' => request()->routeIs('sitemanager.boards.*') || request()->routeIs('sitemanager.comments.*')]) 
                                    href="{{ route('sitemanager.boards.index') }}">
                                    <i class="bi bi-journal-text"></i>
                                    <span>{{ t('Boards') }}</span>
                                </a>
                            </li>
                            <li>
                                <a @class(['sidebar-submenu-item', 'active' => request()->routeIs('sitemanager.files.editor-images')]) 
                                    href="{{ route('sitemanager.files.editor-images') }}">
                                    <i class="bi bi-image"></i>
                                    <span>{{ t('Editor Images') }}</span>
                                </a>
                            </li>
                            <li>
                                <a @class(['sidebar-submenu-item', 'active' => request()->routeIs('sitemanager.files.board-attachments')]) 
                                    href="{{ route('sitemanager.files.board-attachments') }}">
                                    <i class="bi bi-paperclip"></i>
                                    <span>{{ t('Board Attachments') }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                {{-- Extension Menus --}}
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
                                $sidebarDropdownId = 'sidebar-extension-' . $ext['key'] . '-collapse';
                            } else {
                                $routeBase = Str::beforeLast($ext['route'], '.');
                                $isActive = request()->routeIs($routeBase . '.*');
                            }
                        @endphp
                        
                        @if($hasChildren)
                            <li class="sidebar-menu-dropdown">
                                <a @class(['sidebar-menu-item', 'active' => $isActive])
                                    href="#" data-bs-toggle="collapse" data-bs-target="#{{ $sidebarDropdownId }}">
                                    <i class="{{ $ext['icon'] }}"></i>
                                    <span>{{ t($ext['name']) }}</span>
                                    <i class="bi bi-chevron-down ms-auto"></i>
                                </a>
                                <div class="collapse" id="{{ $sidebarDropdownId }}">
                                    <ul class="sidebar-submenu">
                                        @foreach($ext['children'] as $child)
                                            @php
                                                $childRouteBase = Str::beforeLast($child['route'], '.');
                                                $childActive = request()->routeIs($childRouteBase . '.*');
                                            @endphp
                                            <li>
                                                <a @class(['sidebar-submenu-item', 'active' => $childActive])
                                                    href="{{ route($child['route']) }}">
                                                    <i class="{{ $child['icon'] }}"></i>
                                                    <span>{{ t($child['name']) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </li>
                        @else
                            <li>
                                <a @class(['sidebar-menu-item', 'active' => $isActive])
                                    href="{{ route($ext['route']) }}">
                                    <i class="{{ $ext['icon'] }}"></i>
                                    <span>{{ t($ext['name']) }}</span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                @endif
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <ul class="sidebar-menu">
                <li>
                    <a class="sidebar-menu-item" href="{{ route('sitemanager.members.edit', auth()->user()->id) }}">
                        <i class="bi bi-person"></i>
                        <span>{{ auth()->user()->name }}</span>
                    </a>
                </li>
                <li>
                    <a @class(['sidebar-menu-item', 'active' => request()->routeIs('sitemanager.languages.*')])
                        href="{{ route('sitemanager.languages.index') }}">
                        <i class="bi bi-translate"></i>
                        <span>{{ t('Languages') }}</span>
                    </a>
                </li>
                <li>
                    <a @class(['sidebar-menu-item', 'active' => request()->routeIs('sitemanager.settings.*')])
                        href="{{ route('sitemanager.settings') }}">
                        <i class="bi bi-gear"></i>
                        <span>{{ t('System Settings') }}</span>
                    </a>
                </li>
                @if(auth()->check() && auth()->user()->level === 255 && config('sitemanager.language.trace_enabled', false))
                    <li>
                        <button type="button" class="sidebar-menu-item sidebar-trace-btn" id="clear-current-page-btn" onclick="clearCurrentPageLocations()" title="{{ t('Clear current page language location information') }}">
                            <i class="bi bi-translate"></i>
                            <span>{{ t('Clear Location') }}</span>
                        </button>
                    </li>
                @endif
                <li>
                    <a class="sidebar-menu-item" href="/">
                        <i class="bi bi-house-door"></i>
                        <span>{{ t('Home') }}</span>
                    </a>
                </li>
                <li>
                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="sidebar-menu-item sidebar-logout">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>{{ t('Logout') }}</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>
    
    {{-- Main Content Wrapper --}}
    <div class="main-wrapper">
        <main class="main-content">
            <div class="container-fluid">
                @yield('content')
            </div>
        </main>
        
        <footer>
            © {{ date('Y') }} <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="footer-logo"> All rights reserved.
        </footer>
    </div>

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
