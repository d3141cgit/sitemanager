<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', config_get('SITE_DESCRIPTION'))">
    <meta name="keywords" content="@yield('meta_keywords', config_get('SITE_KEYWORDS'))">
    <meta name="author" content="{{ config_get('SITE_AUTHOR') }}">

    <title>@yield('title', config_get('SITE_NAME'))</title>

    {!! setResources(['bootstrap', 'jquery']) !!}
    {{-- Bootstrap Icons CDN --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    {!! resource('css/app.css') !!}

    @stack('head')
</head>

<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-light">
            <div class="container-fluid">
                <!-- 사이드바 토글 버튼 (모바일) -->
                @if(isset($navigationMenus) && count($navigationMenus) > 0)
                    <button class="btn btn-outline-secondary d-lg-none me-2" type="button" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                @endif

                <!-- 브랜드/로고 -->
                <a class="navbar-brand" href="{{ url('/') }}">
                    {{ config_get('SITE_NAME', 'Site Manager') }}
                </a>

                <!-- 모바일 토글 버튼 -->
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- 사용자 메뉴 -->
                    <ul class="navbar-nav">
                        @auth
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle me-1"></i>
                                    {{ Auth::user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="{{ route('user.profile') }}">
                                            <i class="bi bi-person me-2"></i>프로필
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('user.dashboard') }}">
                                            <i class="bi bi-speedometer2 me-2"></i>대시보드
                                        </a>
                                    </li>
                                    
                                    @if(Auth::user()->level >= config('member.admin_level', 200))
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-primary" href="{{ route('admin.dashboard') }}">
                                                <i class="bi bi-gear me-2"></i>관리자
                                            </a>
                                        </li>
                                    @endif
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-box-arrow-right me-2"></i>로그아웃
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>로그인
                                </a>
                            </li>
                        @endauth
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    <main class="d-flex">
        @if(isset($navigationMenus) && count($navigationMenus) > 0)
            <aside class="sidebar">
                <nav class="sidebar-nav">
                    @php
                        // Minimal recursive renderer: ul > li; route/url types render as links, text types as plain text.
                        $renderMenuTree = function($menus) use (&$renderMenuTree) {
                            echo "<ul class='nav flex-column'>";
                            foreach ($menus as $menu) {
                                // Respect hidden flag and Index permission (bit 1)
                                if (!empty($menu['hidden'])) {
                                    continue;
                                }
                                $userPerm = isset($menu['user_permission']) ? (int)$menu['user_permission'] : 0;
                                if (($userPerm & 1) !== 1) {
                                    continue;
                                }

                                echo "<li class='nav-item'>";

                                $type = $menu['type'] ?? 'text';

                                // Only render as a link when it's a linkable type AND a target exists
                                // and get_menu_url returns a real URL (not '#')
                                $linkableTypes = \SiteManager\Models\Menu::getLinkableTypes();
                                if (in_array($type, $linkableTypes) && !empty($menu['target'])) {
                                    $rawUrl = get_menu_url($menu);
                                    $url = e($rawUrl);
                                    $attrs = get_menu_attributes($menu) ?: '';

                                    // If get_menu_url returned a placeholder like '#', fall back to plain text
                                    if (!empty($rawUrl) && $rawUrl !== '#') {
                                        // format_menu_title may contain HTML/icons so output raw
                                        echo '<a href="' . $url . '" class="nav-link" ' . $attrs . '>' . format_menu_title($menu) . '</a>';
                                    } else {
                                        echo '<span class="nav-link disabled">' . e($menu['title'] ?? '') . '</span>';
                                    }
                                } else {
                                    echo '<span class="nav-link disabled">' . e($menu['title'] ?? '') . '</span>';
                                }

                                if (!empty($menu['children']) && is_array($menu['children'])) {
                                    $renderMenuTree($menu['children']);
                                }

                                echo "</li>";
                            }
                            echo "</ul>";
                        };
                    @endphp

                    @foreach($navigationMenus as $sectionKey => $section)
                        @php
                            $roots = $section['menus'] ?? [];
                            $renderMenuTree($roots);
                        @endphp
                    @endforeach
                </nav>
            </aside>
        @endif

        <div class="content-wrapper flex-grow-1">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
    
    <!-- Sidebar Toggle Script -->
    @if(isset($navigationMenus) && count($navigationMenus) > 0)
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebarToggle && sidebar && overlay) {
                // Toggle sidebar
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
                
                // Close sidebar when clicking overlay
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
                
                // Close sidebar on window resize if screen becomes large
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 992) {
                        sidebar.classList.remove('show');
                        overlay.classList.remove('show');
                    }
                });
            }
        });
        </script>
    @endif
</body>
</html>
