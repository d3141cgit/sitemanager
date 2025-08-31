<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- SEO Meta Tags - @yield has priority over auto-generated seoData --}}
    <title>@yield('title', $seoData['title'] ?? config_get('SITE_NAME'))</title>
    <meta name="description" content="@yield('meta_description', $seoData['description'] ?? config_get('SITE_DESCRIPTION'))">
    <meta name="keywords" content="@yield('meta_keywords', $seoData['keywords'] ?? config_get('SITE_KEYWORDS'))">
    <meta name="author" content="{{ config_get('SITE_AUTHOR') }}">
    
    {{-- Canonical URL --}}
    @if(isset($seoData['canonical_url']))
    <link rel="canonical" href="{{ $seoData['canonical_url'] }}">
    @endif
    
    {{-- Open Graph Meta Tags - @yield has priority --}}
    <meta property="og:title" content="@yield('og_title', $seoData['og_title'] ?? $seoData['title'] ?? config_get('SITE_NAME'))">
    <meta property="og:description" content="@yield('og_description', $seoData['og_description'] ?? $seoData['description'] ?? config_get('SITE_DESCRIPTION'))">
    <meta property="og:url" content="{{ $seoData['og_url'] ?? request()->url() }}">
    <meta property="og:image" content="{{ $seoData['og_image'] ?? asset('images/logo.svg') }}">
    <meta property="og:type" content="{{ $seoData['og_type'] ?? 'website' }}">
    <meta property="og:site_name" content="{{ config_get('SITE_NAME') }}">
    <meta property="og:locale" content="{{ app()->getLocale() }}">
    
    {{-- Twitter Card Meta Tags - @yield has priority --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $seoData['og_title'] ?? $seoData['title'] ?? config_get('SITE_NAME'))">
    <meta name="twitter:description" content="@yield('og_description', $seoData['og_description'] ?? $seoData['description'] ?? config_get('SITE_DESCRIPTION'))">
    <meta name="twitter:image" content="{{ $seoData['og_image'] ?? asset('images/logo.svg') }}">
    
    {{-- 공통 SEO 컴포넌트 (컨트롤러에서 생성된 seoData 사용) --}}
    @include('sitemanager::components.seo')
    
    {{-- 페이지별 추가 메타태그 및 스크립트 --}}
    @stack('head')

    {!! setResources(['bootstrap', 'jquery']) !!}
    {!! resource('sitemanager::css/app.css') !!}
</head>

<body class="app-grid-layout">
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <!-- Hamburger Menu Button (Mobile) -->
            <button class="hamburger-btn me-3" type="button" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <!-- Brand -->
            <a class="navbar-brand" href="{{ url('/') }}">
                <div class="brand-logo">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                    <span>{{ config_get('SITE_NAME', 'SiteManager') }}</span>
                </div>
            </a>

            <!-- Mobile Toggle -->
            {{-- <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <i class="bi bi-three-dots"></i>
            </button> --}}

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
                                        <a class="dropdown-item admin-link" href="{{ route('sitemanager.dashboard') }}" target="admin">
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

    <aside>
        <nav class="sidebar-nav">
            @if(isset($navigationMenus) && count($navigationMenus) > 0)
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
            @endif
        </nav>

        <nav class="user-nav">
            <ul>
            @auth
                <li>
                    <a href="#">
                        <i class="bi bi-person-circle"></i>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="bi bi-person"></i>
                        <span>프로필</span>
                    </a>
                </li>
                <li>
                    <a href="#">
                        <i class="bi bi-gear"></i>
                        <span>설정</span>
                    </a>
                </li>
                        
                @if(Auth::user()->level >= config('member.admin_level', 200))
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="{{ route('sitemanager.dashboard') }}" target="admin">
                            <i class="bi bi-shield-check"></i>
                            <span>관리자</span>
                        </a>
                    </li>
                @endif
                
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>로그아웃</span>
                        </button>
                    </form>
                </li>
            @else
                <li>
                    <a href="{{ route('login') }}">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>로그인</span>
                    </a>
                </li>
            @endauth
            </ul>
        </nav>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main>
        @yield('content')
    </main>

    @stack('scripts')
    
    <!-- Mobile Sidebar Toggle Script -->
    @if(isset($navigationMenus) && count($navigationMenus) > 0)
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('aside');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebarToggle && sidebar && overlay) {
                sidebarToggle.addEventListener('click', function() {
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
