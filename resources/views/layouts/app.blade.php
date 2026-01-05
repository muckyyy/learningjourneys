<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Learning Journeys') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Compiled CSS (includes Bootstrap + Bootstrap Icons) -->
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    
    
</head>
<body 
    class="app-body {{ auth()->check() ? 'has-sidebar' : 'guest-only' }}"
    @auth 
        data-user-id="{{ Auth::id() }}" 
        data-user-name="{{ addslashes(Auth::user()->name) }}" 
        data-user-email="{{ addslashes(Auth::user()->email) }}"
    @endauth
>
    @php
        $tokenSummary = auth()->check() ? app(\App\Services\TokenLedger::class)->balance(Auth::user()) : null;
        $tokenTotal = $tokenSummary['total'] ?? 0;
    @endphp

    <div class="app-shell d-flex w-100">
        @auth
            <!-- Sidebar -->
            <nav id="appSidebar" class="sidebar sidebar-fixed border-end position-fixed top-0 start-0 vh-100">
                <div class="sidebar-inner d-flex flex-column h-100 p-3">
                    <div class="mb-3">
                        <h4 class="mb-1 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-mortarboard"></i> Learning Journeys
                        </h4>
                        <p class="text-muted small mb-0">Grow every day</p>
                    </div>
                    
                    <div class="mb-3 p-3 bg-light rounded-4 shadow-sm">
                        <div class="fw-bold text-dark">{{ Auth::user()->name }}</div>
                        <span class="badge bg-primary role-badge">{{ ucfirst(Auth::user()->role) }}</span>
                    </div>

                    <div class="sidebar-quick d-none d-md-block mb-4" x-data="soundToggle()">
                        <div class="d-flex flex-column gap-3">
                            <button type="button" class="btn btn-sm sound-toggle-btn" :class="soundEnabled ? 'btn-primary text-white' : 'btn-outline-secondary'" @click="toggleSound()">
                                <i class="bi" :class="soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'"></i>
                                <span x-text="soundEnabled ? 'Sound On' : 'Sound Off'"></span>
                            </button>
                            <div class="token-chip">
                                <i class="bi bi-coin"></i>
                                <span>{{ number_format($tokenTotal) }}</span>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-pills flex-column gap-1">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        
                        @if(Auth::user()->canPerform('journey.view'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('journeys.*') ? 'active' : '' }}" href="{{ route('journeys.index') }}">
                                    <i class="bi bi-map"></i> Journeys
                                </a>
                            </li>
                        @endif

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('tokens.*') ? 'active' : '' }}" href="{{ route('tokens.index') }}">
                                <i class="bi bi-coin"></i> Tokens
                            </a>
                        </li>

                        @if(Auth::user()->role === 'administrator')
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.token-management.*') ? 'active' : '' }}" href="{{ route('admin.token-management.index') }}">
                                    <i class="bi bi-sliders"></i> Token Admin
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->canPerform('journey_collection.manage'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('collections.*') ? 'active' : '' }}" href="{{ route('collections.index') }}">
                                    <i class="bi bi-collection"></i> Collections
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->canPerform('editor.manage'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('editors.*') ? 'active' : '' }}" href="{{ route('editors.index') }}">
                                    <i class="bi bi-people"></i> Editors
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->canPerform('institution.manage'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('institutions.*') ? 'active' : '' }}" href="{{ route('institutions.index') }}">
                                    <i class="bi bi-building"></i> Institutions
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->canPerform('user.manage'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                    <i class="bi bi-person-gear"></i> Users
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('profile-fields.*') ? 'active' : '' }}" href="{{ route('profile-fields.index') }}">
                                    <i class="bi bi-person-lines-fill"></i> Profile Fields
                                </a>
                            </li>
                        @endif

                        @if(Auth::user()->canPerform('reports.view'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                    <i class="bi bi-graph-up"></i> Reports
                                </a>
                            </li>
                        @endif

                        <li class="nav-item mt-3">
                            <a class="nav-link" href="{{ route('profile.show') }}">
                                <i class="bi bi-person-circle"></i> Profile
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link text-danger" href="{{ route('logout') }}"
                               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
        @endauth

        <!-- Main Content -->
        <main class="main-content flex-grow-1 d-flex flex-column">
            @auth
               
                <!-- Mobile top bar -->
                <header class="mobile-topbar glass-header d-md-none sticky-top" x-data="soundToggle()">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary rounded-circle js-sidebar-toggle" type="button" aria-controls="appSidebar" aria-label="Toggle navigation">
                                <i class="bi bi-list"></i>
                            </button>
                            <a class="navbar-brand fw-semibold text-decoration-none text-dark" href="{{ route('home') }}">
                                <i class="bi bi-mortarboard me-1 text-primary"></i>
                                {{ config('app.name', 'Learning Journeys') }}
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="token-balance-pill">
                                <i class="bi bi-coin me-1"></i>{{ number_format($tokenTotal) }}
                            </div>
                            <button type="button" class="btn btn-sm sound-toggle-btn" :class="soundEnabled ? 'btn-primary text-white' : 'btn-outline-secondary'" @click="toggleSound()">
                                <i class="bi" :class="soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'"></i>
                            </button>
                        </div>
                    </div>
                </header>
            @endauth

            @guest
                <!-- Guest Navigation -->
                <nav class="navbar navbar-expand-lg glass-header sticky-top shadow-sm bg-white">
                    <div class="container">
                        <a class="navbar-brand" href="{{ url('/') }}">
                            <i class="bi bi-mortarboard"></i> {{ config('app.name', 'Learning Journeys') }}
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="navbarNav">
                            <ul class="navbar-nav ms-auto">
                                @if (Route::has('login'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                    </li>
                                @endif
                                @if (Route::has('register'))
                                    <li class="nav-item">
                                        <a class="nav-link" href="{{ route('register') }}">{{ __('Register') }}</a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </nav>
            @endguest

            <!-- Page Content -->
            <div class="container-fluid g-0 pb-5 pb-md-4">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>

        @auth
            <nav class="mobile-bottom-nav navbar fixed-bottom d-md-none">
                <div class="container position-relative pt-3 pb-2">
                    <div class="d-flex justify-content-between align-items-center px-3">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                            <i class="bi bi-house-door-fill"></i>
                            <span class="small">Home</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('journeys.*') ? 'active' : '' }}" href="{{ route('journeys.index') }}">
                            <i class="bi bi-map"></i>
                            <span class="small">Journeys</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('tokens.*') ? 'active' : '' }}" href="{{ route('tokens.index') }}">
                            <i class="bi bi-coin"></i>
                            <span class="small">Tokens</span>
                        </a>
                    </div>
                    <div class="mobile-fab-slot">
                        <a href="{{ route('journeys.index') }}" class="nav-fab text-white">
                            <i class="bi bi-stars"></i>
                        </a>
                    </div>
                </div>
            </nav>
        @endauth
    </div>

    <!-- Logout Form -->
    @auth
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    @endauth
    
    <!-- Sidebar overlay for click-away close -->
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    
    <!-- Compiled JavaScript (includes Bootstrap + Laravel Echo) -->
    <script src="{{ mix('js/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
