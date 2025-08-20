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
    {!! resource('sitemanager::css/app.css') !!}
    
    @stack('head')
</head>

<body class="modern-layout">
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-modern">
        <div class="container-fluid">
            <!-- Brand -->
            <a class="navbar-brand" href="{{ url('/') }}">
                <div class="brand-logo">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span>{{ config_get('SITE_NAME', 'SiteManager') }}</span>
                </div>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Content -->
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav ms-auto">
                    @auth
                        <li class="nav-item dropdown user-dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <span class="user-name">{{ Auth::user()->name }}</span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <i class="bi bi-person"></i>
                                        <span>프로필</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#">
                                        <i class="bi bi-gear"></i>
                                        <span>설정</span>
                                    </a>
                                </li>
                                
                                @if(Auth::user()->level >= config('member.admin_level', 200))
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item admin-link" href="{{ route('admin.dashboard') }}" target="admin">
                                            <i class="bi bi-shield-check"></i>
                                            <span>관리자</span>
                                        </a>
                                    </li>
                                @endif
                                
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item logout-btn">
                                            <i class="bi bi-box-arrow-right"></i>
                                            <span>로그아웃</span>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link login-btn" href="{{ route('login') }}">
                                <i class="bi bi-box-arrow-in-right"></i>
                                <span>로그인</span>
                            </a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-wrapper{{ isset($navigationMenus) && count($navigationMenus) > 0 ? '' : ' no-sidebar' }}">
        @if(isset($navigationMenus) && count($navigationMenus) > 0)
            <!-- Sidebar -->
            <aside class="sidebar-modern">
                <div class="sidebar-header">
                    <h6>메뉴</h6>
                </div>
                <nav class="sidebar-nav">
                    @php
                        $renderMenuTree = function($menus) use (&$renderMenuTree) {
                            echo "<ul class='nav-list'>";
                            foreach ($menus as $menu) {
                                if (!empty($menu['hidden'])) continue;
                                
                                $userPerm = isset($menu['user_permission']) ? (int)$menu['user_permission'] : 0;
                                if (($userPerm & 1) !== 1) continue;

                                echo "<li class='nav-item'>";
                                
                                $type = $menu['type'] ?? 'text';
                                $linkableTypes = \SiteManager\Models\Menu::getLinkableTypes();
                                
                                if (in_array($type, $linkableTypes) && !empty($menu['target'])) {
                                    $rawUrl = get_menu_url($menu);
                                    $url = e($rawUrl);
                                    $attrs = get_menu_attributes($menu) ?: '';

                                    if (!empty($rawUrl) && $rawUrl !== '#') {
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

        <!-- Content Area -->
        <main class="content-area @if(!isset($navigationMenus) || count($navigationMenus) === 0) full-width @endif">
            @yield('content')
        </main>
    </div>

    @stack('scripts')
    
    <!-- Sidebar Toggle Script for Mobile -->
    @if(isset($navigationMenus) && count($navigationMenus) > 0)
        <div class="sidebar-overlay"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const sidebar = document.querySelector('.sidebar-modern');
            const overlay = document.querySelector('.sidebar-overlay');
            
            if (navbarToggler && sidebar && overlay) {
                navbarToggler.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                    overlay.classList.toggle('show');
                });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
                
                window.addEventListener('resize', function() {
                    if (window.innerWidth >= 768) {
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
