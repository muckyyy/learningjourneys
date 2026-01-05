@extends('layouts.app')

@push('styles')
<style>
.home-shell {
    width: min(1200px, 100%);
    max-width: 100%;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.home-hero {
    background: linear-gradient(135deg, #0f172a, #1d4ed8 70%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 6vw, 4rem);
    margin-bottom: 2.5rem;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
}
.home-hero h1 {
    font-size: clamp(2.1rem, 4vw, 3rem);
    font-weight: 700;
}
.home-hero p {
    color: rgba(255, 255, 255, 0.72);
    max-width: 520px;
}
.hero-meta-pill {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1.4rem;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.9rem;
    font-weight: 600;
}
.status-card {
    border-radius: 32px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
    padding: clamp(1.75rem, 4vw, 2.75rem);
}
.status-heading {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    margin-bottom: 1.5rem;
}
.status-heading span {
    font-weight: 600;
    letter-spacing: 0.24em;
    color: #94a3b8;
}
.active-banner,
.empty-banner {
    border-radius: 24px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}
.active-banner {
    background: #ecfccb;
    border: 1px solid rgba(132, 204, 22, 0.45);
}
.empty-banner {
    background: #fef3c7;
    border: 1px solid rgba(251, 191, 36, 0.45);
}
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}
.chip-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    margin-top: 2rem;
}
.chip-links a {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.12);
    padding: 0.55rem 1.2rem;
    text-decoration: none;
    color: #0f172a;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.chip-links a:hover {
    border-color: #0f172a;
}
.status-alert {
    border-radius: 18px;
    padding: 0.9rem 1.2rem;
    background: rgba(34, 197, 94, 0.12);
    color: #15803d;
    font-weight: 600;
    margin-bottom: 1.5rem;
}
@media (max-width: 575.98px) {
    .hero-actions .btn {
        width: 100%;
    }
    .status-heading {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
@endpush

@section('content')
@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
    $firstName = $firstName->isNotEmpty() ? $firstName : auth()->user()->name;
@endphp

<section class="home-shell">
    <div class="home-hero">
        <div class="hero-meta-pill mb-3">
            <i class="bi bi-stars"></i> Home
        </div>
        <h1 class="mb-3">Hey {{ $firstName }}, ready to learn?</h1>
        <p class="mb-4">Pick up right where you left off or jump into a fresh learning journey crafted just for you.</p>
        <div class="hero-actions">
            <a href="{{ route('journeys.index') }}" class="btn btn-light text-dark">
                <i class="bi bi-compass"></i> Browse Journeys
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light">
                <i class="bi bi-speedometer"></i> View Dashboard
            </a>
        </div>
    </div>

    <div class="status-card">
        <div class="status-heading">
            <i class="bi bi-compass text-primary fs-4"></i>
            <div>
                <span>Journey status</span>
                <h3 class="mb-0">Your current progress</h3>
            </div>
        </div>

        @if (session('status'))
            <div class="status-alert">
                <i class="bi bi-check-circle"></i> {{ session('status') }}
            </div>
        @endif

        @if($activeAttempt)
            <div class="active-banner d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <p class="text-uppercase small text-success mb-1" style="letter-spacing: 0.2em;">Active journey</p>
                    <h4 class="mb-0">{{ $activeAttempt->journey->title }}</h4>
                    <p class="text-muted mb-0">Pick up where you left off and keep momentum going.</p>
                </div>
                <div class="text-md-end">
                    <small class="text-muted d-block">Mode: {{ ucfirst($activeAttempt->type) }}</small>
                    <small class="text-muted">Started {{ optional($activeAttempt->created_at)->diffForHumans() }}</small>
                </div>
            </div>
            <div class="action-grid">
                <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-dark btn-lg">
                    <i class="bi bi-play-circle"></i> Continue journey
                </a>
                <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-lg w-100"
                        onclick="return confirm('Abandon this journey and lose current progress?')">
                        <i class="bi bi-x-circle"></i> Abandon journey
                    </button>
                </form>
            </div>
        @else
            <div class="empty-banner d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <p class="text-uppercase small text-warning mb-1" style="letter-spacing: 0.2em;">No journey running</p>
                    <h4 class="mb-1">You are between journeys.</h4>
                    <p class="text-muted mb-0">Browse curated tracks and start a new session when you are ready.</p>
                </div>
                <div class="align-self-center text-md-end">
                    <i class="bi bi-exclamation-triangle text-warning fs-2"></i>
                </div>
            </div>
            <div class="action-grid">
                <a href="{{ route('journeys.index') }}" class="btn btn-dark btn-lg">
                    <i class="bi bi-list"></i> Explore journeys
                </a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-dark btn-lg">
                    <i class="bi bi-graph-up"></i> View insights
                </a>
            </div>
        @endif

        <div class="chip-links">
            <a href="{{ route('profile.show') }}"><i class="bi bi-person"></i> Profile</a>
            <a href="{{ route('journeys.index') }}#recommended"><i class="bi bi-lightning-charge"></i> Recommendations</a>
            <a href="{{ route('tokens.index') }}"><i class="bi bi-coin"></i> Token balance</a>
            <a href="{{ route('dashboard') }}#activity"><i class="bi bi-clock"></i> Recent activity</a>
        </div>
    </div>
</section>
@endsection
