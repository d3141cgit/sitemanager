<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'SiteManager Panel') - Site Manager</title>
    
    {!! setResources(['jquery', 'bootstrap', 'sweetalert']) !!}
    {!! resource('sitemanager::css/sitemanager/sitemanager.css') !!}
    {!! resource('sitemanager::js/sitemanager/sitemanager.js') !!}
    
    @stack('styles')
    @yield('head')
</head>

<body>
    <div id="page-loader" class="fade show">
        <span class="spinner"></span>
    </div>
    
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a href="{{ route('sitemanager.dashboard') }}">
                <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="navbar-logo">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <div id="nav-icon"><em aria-hidden="true"></em></div>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.dashboard')]) 
                            href="{{ route('sitemanager.dashboard') }}">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.members.*')]) 
                           href="{{ route('sitemanager.members.index') }}">
                            <i class="bi bi-people"></i>
                            Members
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.groups.*')]) 
                           href="{{ route('sitemanager.groups.index') }}">
                            <i class="bi bi-collection"></i>
                            Groups
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.menus.*')]) 
                           href="{{ route('sitemanager.menus.index') }}">
                            <i class="bi bi-list"></i>
                            Menus
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.boards.*')]) 
                           href="{{ route('sitemanager.boards.index') }}">
                            <i class="bi bi-journal-text"></i>
                            Boards
                        </a>
                    </li>
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('sitemanager.settings.*')]) 
                           href="{{ route('sitemanager.settings') }}">
                            <i class="bi bi-gear"></i>
                            Settings
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-user">                    
                    <!-- User Profile -->
                    <li class="dropdown">
                        <a class="d-flex align-items-center" href="#" id="userDropdown" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            @if(auth()->user()->profile_photo)
                                <img src="{{ auth()->user()->profile_photo_url }}" 
                                     alt="{{ auth()->user()->name }}'s profile photo" 
                                     class="admin-profile-photo">
                            @else
                                <i class="bi bi-person-circle me-2"></i>
                            @endif
                            {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="{{ route('sitemanager.members.edit', auth()->user()->id) }}">
                                    <i class="bi bi-person me-2"></i>Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
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
            </div>
        </div>
    </nav>
    
    <main class="content px-3 py-4">
        <div class="container-fluid">
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
                title: '성공!',
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
                title: '오류!',
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
                title: '입력 오류!',
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

    @stack('scripts')
</body>
</html>
