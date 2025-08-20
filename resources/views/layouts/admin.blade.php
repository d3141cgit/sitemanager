<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Panel') - Site Manager</title>
    
    {!! setResources(['jquery', 'bootstrap', 'sweetalert']) !!}
    {!! resource('css/admin/admin.css') !!}
    {!! resource('js/admin/admin.js') !!}
    
    @stack('styles')
    @yield('head')
</head>

<body>
    <div id="page-loader" class="fade show">
        <span class="spinner"></span>
    </div>
    
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a href="{{ route('admin.dashboard') }}">
                <img src="/images/sitemanager.svg" alt="Site Manager Logo" class="navbar-logo">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <div id="nav-icon"><em aria-hidden="true"></em></div>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">
                            <i class="bi bi-house-door"></i>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.dashboard')]) 
                            href="{{ route('admin.dashboard') }}">
                            <i class="bi bi-speedometer2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.members.*')]) 
                           href="{{ route('admin.members.index') }}">
                            <i class="bi bi-people"></i>
                            Members
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.groups.*')]) 
                           href="{{ route('admin.groups.index') }}">
                            <i class="bi bi-collection"></i>
                            Groups
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.menus.*')]) 
                           href="{{ route('admin.menus.index') }}">
                            <i class="bi bi-list"></i>
                            Menus
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.boards.*')]) 
                           href="{{ route('admin.boards.index') }}">
                            <i class="bi bi-journal-text"></i>
                            Boards
                        </a>
                    </li>
                    {{-- <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.statistics.*')]) 
                           href="{{ route('admin.statistics') }}">
                            <i class="bi bi-bar-chart"></i>
                            Statistics
                        </a>
                    </li> --}}
                    <li class="nav-item">
                        <a @class(['nav-link', 'active' => request()->routeIs('admin.settings.*')]) 
                           href="{{ route('admin.settings') }}">
                            <i class="bi bi-gear"></i>
                            Settings
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- Search -->
                    {{-- <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="searchDropdown" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 300px;" aria-labelledby="searchDropdown">
                            <form>
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="검색어를 입력하세요">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </li> --}}
                    
                    <!-- Notifications -->
                    {{-- <li class="nav-item dropdown">
                        <a class="nav-link" href="#" id="notificationDropdown" 
                           role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                            <span class="badge bg-danger rounded-pill">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                            <h6 class="dropdown-header">알림 (3)</h6>
                            <a href="#" class="dropdown-item">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-person-plus text-success"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1">새로운 회원 가입</h6>
                                        <p class="mb-1 small">홍길동님이 가입했습니다.</p>
                                        <small class="text-muted">5분 전</small>
                                    </div>
                                </div>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-center" href="#">모든 알림 보기</a>
                        </div>
                    </li> --}}
                    
                    <!-- User Profile -->
                    <li class="nav-item dropdown">
                        <a class="nav-link d-flex align-items-center" href="#" id="userDropdown" 
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
                                <a class="dropdown-item" href="{{ route('admin.members.edit', auth()->user()->id) }}">
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
