@extends('layouts.app')

@section('content')
@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
    $firstName = $firstName->isNotEmpty() ? $firstName : auth()->user()->name;
@endphp

<section class="shell">
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
                    <p class="text-uppercase small text-success mb-1 letter-spacing-default">Active journey</p>
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
                    <p class="text-uppercase small text-warning mb-1 letter-spacing-default">No journey running</p>
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
