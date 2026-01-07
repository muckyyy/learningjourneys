<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'The Thinking Course') }} - Grow every day</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Compiled CSS (includes Bootstrap + Bootstrap Icons) -->
    <link href="{{ mix('css/app.css') }}" rel="stylesheet">
    @stack('styles')
    <style>
        .guest-nav {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        .guest-nav .navbar-brand {
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            letter-spacing: 0.04em;
        }
        .guest-nav .navbar-brand img {
            height: 56px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .sidebar-logo {
            
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .sidebar-inner {
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(15, 23, 42, 0.3) transparent;
            padding-bottom: calc(2rem + 76px);
        }
        .sidebar-inner::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-inner::-webkit-scrollbar-thumb {
            background: rgba(15, 23, 42, 0.3);
            border-radius: 999px;
        }
        .sidebar-inner::-webkit-scrollbar-track {
            background: transparent;
        }
        .guest-nav .brand-accent {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.15);
            color: #2563eb;
        }
        .guest-nav .nav-link {
            font-weight: 500;
            color: #1f2937;
            padding: 0.5rem 0.75rem;
        }
        .guest-nav .nav-link:hover,
        .guest-nav .nav-link:focus {
            color: #111827;
        }
        .guest-nav .nav-cta .btn {
            border-radius: 999px;
            font-weight: 600;
            padding: 0.55rem 1.35rem;
        }
        .guest-nav .btn-ghost {
            background: transparent;
            border: 1px solid rgba(15, 23, 42, 0.15);
            color: #0f172a;
        }
        .guest-nav .btn-ghost:hover {
            border-color: #0f172a;
            color: #0f172a;
        }
        .guest-nav .btn-solid {
            background: #0f172a;
            color: #fff;
        }
        .guest-nav .btn-solid:hover {
            background: #111b39;
            color: #fff;
        }

        @media (min-width: 992px) {
            body.has-sidebar .app-shell {
                padding-left: var(--sidebar-width);
            }

            body.has-sidebar .main-content {
                
            }
        }

        @media (min-width: 1400px) {
            body.has-sidebar .app-shell {
                padding-left: calc(var(--sidebar-width) + 1.5rem);
            }
        }

        @media (max-width: 991.98px) {
            body.sidebar-open .mobile-bottom-nav {
                opacity: 0;
                pointer-events: none;
                visibility: hidden;
                transition: opacity 0.2s ease;
            }
            .mobile-bottom-nav {
                opacity: 1;
                visibility: visible;
                transition: opacity 0.2s ease;
            }
        }
    </style>
    
    
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
                        <a class="sidebar-brand d-flex align-items-center mb-4 text-decoration-none" href="{{ route('home') }}">
                            <img src="{{ asset('logo/logo.png') }}" alt="{{ config('app.name', 'The Thinking Course') }} Logo" class="d-inline-block align-text-top sidebar-logo">
                           
                        </a>
                    </div>
                    <!--
                    <div class="mb-3 p-3 bg-light rounded-4 shadow-sm">
                        <div class="fw-bold text-dark">{{ Auth::user()->name }}</div>
                        <span class="badge bg-primary role-badge">{{ ucfirst(Auth::user()->role) }}</span>
                    </div>-->
                    <!--
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
                    </div>-->

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

                        @if(Auth::user()->canPerform('journey_collection.manage') || Auth::user()->role === 'regular')
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
                <header class="mobile-topbar glass-header d-lg-none" x-data="soundToggle()">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <button class="btn btn-outline-secondary rounded-circle js-sidebar-toggle" type="button" aria-controls="appSidebar" aria-label="Toggle navigation">
                                <i class="bi bi-list"></i>
                            </button>
                            <a class="navbar-brand fw-semibold text-decoration-none text-dark" href="{{ route('home') }}">
                                <i class="bi bi-mortarboard me-1 text-primary"></i>
                                {{ config('app.name', 'The Thinking Course') }}
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="token-balance-pill">
                                <i class="bi bi-coin me-1"></i>{{ number_format($tokenTotal) }}
                            </div>
                            <!--
                            <button type="button" class="btn btn-sm sound-toggle-btn" :class="soundEnabled ? 'btn-primary text-white' : 'btn-outline-secondary'" @click="toggleSound()">
                                <i class="bi" :class="soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'"></i>
                            </button>-->
                        </div>
                    </div>
                </header>
            @endauth

            @guest
                <nav class="guest-nav navbar navbar-expand-lg navbar-light shadow-sm">
                    <div class="container">
                        <a class="navbar-brand" href="{{ url('/') }}">
                            <img src="{{ asset('logo/logo.png') }}" alt="{{ config('app.name', 'The Thinking Course') }} Logo" class="d-inline-block align-text-top">
                        </a>
                        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#guestNav" aria-controls="guestNav" aria-expanded="false" aria-label="Toggle navigation">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="collapse navbar-collapse" id="guestNav">
                            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                                <li class="nav-item"><a class="nav-link" href="{{ url('/#hero') }}">Overview</a></li>
                                <li class="nav-item"><a class="nav-link" href="{{ url('/#features') }}">Features</a></li>
                                <li class="nav-item"><a class="nav-link" href="{{ url('/#stats') }}">Stats</a></li>
                                <li class="nav-item"><a class="nav-link" href="{{ url('/#cta') }}">Get Started</a></li>
                            </ul>
                            <div class="nav-cta d-flex flex-column flex-lg-row gap-2 ms-lg-4 mt-3 mt-lg-0">
                                @if (Route::has('login'))
                                    <a class="btn btn-ghost" href="{{ route('login') }}">Login</a>
                                @endif
                                @if (Route::has('register'))
                                    <a class="btn btn-solid" href="{{ route('register') }}">Create account</a>
                                @endif
                            </div>
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
                <div class="mobile-bottom-nav-shell position-relative pt-3 pb-2 w-100">
                    <div class="mobile-bottom-links d-flex align-items-center px-3 w-100">
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
                        <a class="nav-link {{ request()->routeIs('profile.show') ? 'active' : '' }}" href="{{ route('profile.show') }}">
                            <i class="bi bi-person-circle"></i>
                            <span class="small">Profile</span>
                        </a>
                    </div>
                    <div class="mobile-fab-slot">
                        <a href="{{ route('dashboard') }}" class="nav-fab text-white">
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
