<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Learning Journeys') }}</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    
    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Nunito', sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #f8f9fa;
            box-shadow: 2px 0 5px rgba(0,0,0,.1);
        }
        .main-content {
            margin-left: 0;
        }
        @media (min-width: 768px) {
            .main-content.with-sidebar {
                margin-left: 250px;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 250px;
                z-index: 1000;
            }
        }
        .role-badge {
            font-size: 0.8em;
        }
        .navbar-brand {
            font-weight: bold;
            color: #495057;
        }
        .nav-link {
            border-radius: 0.375rem;
            margin-bottom: 0.25rem;
        }
        .nav-link:hover {
            background-color: #e9ecef;
        }
        .nav-link.active {
            background-color: #0d6efd;
            color: white !important;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .btn {
            border-radius: 0.375rem;
        }
        .form-control {
            border-radius: 0.375rem;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        @auth
            <!-- Sidebar -->
            <nav class="sidebar border-end d-none d-md-block">
                <div class="p-3">
                    <h4 class="mb-3 text-primary">
                        <i class="bi bi-mortarboard"></i> Learning Journeys
                    </h4>
                    
                    <div class="mb-3 p-2 bg-light rounded">
                        <div class="fw-bold text-dark">{{ Auth::user()->name }}</div>
                        <span class="badge bg-primary role-badge">{{ ucfirst(Auth::user()->role) }}</span>
                    </div>

                    <ul class="nav nav-pills flex-column">
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
        <main class="main-content{{ auth()->check() ? ' with-sidebar' : '' }} flex-grow-1">
            @guest
                <!-- Guest Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
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
            @else
                <!-- Authenticated Top Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom d-md-none">
                    <div class="container-fluid">
                        <a class="navbar-brand" href="{{ route('home') }}">
                            <i class="bi bi-mortarboard"></i> Learning Journeys
                        </a>
                        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                    </div>
                </nav>

                <!-- Mobile Sidebar -->
                <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="mobileSidebar">
                    <div class="offcanvas-header">
                        <h5 class="offcanvas-title">Learning Journeys</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
                    </div>
                    <div class="offcanvas-body">
                        <!-- Same navigation items as sidebar -->
                        <div class="mb-3">
                            <div class="fw-bold">{{ Auth::user()->name }}</div>
                            <span class="badge bg-primary role-badge">{{ ucfirst(Auth::user()->role) }}</span>
                        </div>
                        <!-- Add mobile navigation items here -->
                    </div>
                </div>
            @endguest

            <!-- Page Content -->
            <div class="container-fluid p-4">
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
    </div>

    <!-- Logout Form -->
    @auth
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    @endauth

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
