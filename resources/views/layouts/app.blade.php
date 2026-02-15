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

    
</head>
@php
    $tokenSummary = auth()->check() ? app(\App\Services\TokenLedger::class)->balance(Auth::user()) : null;
    $tokenTotal = $tokenSummary['total'] ?? 0;
@endphp
<body 
    class="app-body {{ auth()->check() ? 'has-sidebar' : 'guest-only' }}"
    @auth 
        data-user-id="{{ Auth::id() }}" 
        data-user-name="{{ addslashes(Auth::user()->name) }}" 
        data-user-email="{{ addslashes(Auth::user()->email) }}"
    @endauth
>

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

                    <ul class="nav nav-pills flex-column gap-1">
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
                                <i class="bi bi-house"></i> Home
                            </a>
                        </li>
                        
                        @if(Auth::user()->canPerform('journey.view') || Auth::user()->hasActiveMembership())
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

                        @if(Auth::user()->canPerform('certificate.manage'))
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('admin.certificates.*') ? 'active' : '' }}" href="{{ route('admin.certificates.index') }}">
                                    <i class="bi bi-award"></i> Certificates
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
                            <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" href="{{ route('profile.show') }}">
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
                                <img src="{{ asset('logo/logo.png') }}" alt="{{ config('app.name', 'The Thinking Course') }} Logo" class="align-text-top logo-brand-small">
                            </a>
                        </div>
                        
                    </div>
                </header>

                @php
                    $availableInstitutions = Auth::user()->institutions()->wherePivot('is_active', true)->get();
                    $activeInstitution = Auth::user()->activeInstitution;
                    $canSwitchInstitutions = $availableInstitutions->count() > 1;
                @endphp

                @if(Auth::user()->isImpersonated())
                    <div class="alert alert-warning d-flex align-items-center justify-content-between rounded-4 shadow-sm mt-3 mx-3">
                        <div>
                            <strong>Impersonating:</strong> {{ Auth::user()->name }}
                            <span class="text-muted ms-2">You are viewing the platform as this user.</span>
                        </div>
                        <form action="{{ route('impersonation.leave') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-outline-dark rounded-pill" type="submit">
                                <i class="bi bi-box-arrow-left"></i> Return to my account
                            </button>
                        </form>
                    </div>
                @endif

                @if(!Auth::user()->isAdministrator())
                    @if($availableInstitutions->isEmpty())
                        <!--
                        <div class="alert alert-danger rounded-4 shadow-sm mt-3 mx-3">
                            <strong>No active institution membership.</strong>
                            <span class="ms-1">Please contact support to be added to an institution.</span>
                        </div>-->
                    @else
                        <!--
                        <div class="card border-0 shadow-sm rounded-4 mt-3 mx-3">
                            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                                <div>
                                    <small class="text-muted text-uppercase">Active Institution</small>
                                    <div class="fw-semibold">{{ $activeInstitution?->name ?? 'Select an institution' }}</div>
                                </div>
                                @if($canSwitchInstitutions)
                                    <form action="{{ route('active-institution.update') }}" method="POST" class="ms-auto d-flex gap-2 align-items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="institution_id" class="form-select form-select-sm rounded-pill" required>
                                            @foreach($availableInstitutions as $institution)
                                                <option value="{{ $institution->id }}" {{ optional($activeInstitution)->id === $institution->id ? 'selected' : '' }}>
                                                    {{ $institution->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill">
                                            <i class="bi bi-arrow-repeat"></i> Switch
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>-->
                    @endif
                @endif
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
