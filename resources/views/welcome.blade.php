@extends('layouts.app')

@push('styles')
<style>
.welcome-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
}
.welcome-hero {
    background: radial-gradient(circle at top left, #1d4ed8, #0f172a 60%);
    color: #fff;
    border-radius: 32px;
    padding: clamp(2.5rem, 6vw, 4rem);
    position: relative;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(15, 23, 42, 0.25);
}
.welcome-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.12), transparent 55%);
    pointer-events: none;
}
.welcome-hero h1 {
    font-size: clamp(2.5rem, 6vw, 3.5rem);
    font-weight: 700;
}
.welcome-hero p {
    font-size: clamp(1rem, 2.4vw, 1.25rem);
    max-width: 640px;
}
.welcome-btn {
    border-radius: 999px;
    padding: 0.85rem 2.5rem;
    font-weight: 600;
}
.welcome-card {
    border: 1px solid rgba(15, 23, 42, 0.05);
    border-radius: 28px;
    padding: 2rem;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    height: 100%;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.welcome-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 50px rgba(15, 23, 42, 0.12);
}
.icon-pill {
    width: 72px;
    height: 72px;
    border-radius: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
}
.stats-grid {
    border-radius: 32px;
    background: #0f172a;
    color: #fff;
    padding: clamp(2rem, 5vw, 3.5rem);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
}
.stats-grid h3 {
    font-weight: 700;
}
.stat-item span {
    font-size: 0.95rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.65);
}
.stat-item strong {
    font-size: clamp(1.5rem, 3vw, 2.25rem);
    display: block;
}
.trusted-logos img {
    opacity: 0.65;
    transition: opacity 0.2s ease;
}
.trusted-logos img:hover { opacity: 1; }
@media (max-width: 575.98px) {
    .welcome-shell { padding-left: 1.25rem; padding-right: 1.25rem; }
    .welcome-card { padding: 1.5rem; }
}
</style>
@endpush

@section('content')
<section class="welcome-shell" id="hero">
    <div class="welcome-hero mb-5">
        <div class="position-relative" style="z-index: 2;">
            <div class="d-inline-flex align-items-center gap-2 small text-uppercase mb-3" style="letter-spacing: 0.25em;">
                <span class="badge bg-white text-dark text-uppercase">Learning Journeys</span>
                <span>Explore • Grow • Repeat</span>
            </div>
            <h1 class="mb-3">Personalized paths for every curious mind.</h1>
            <p class="text-white-50 mb-4">Crafted with the same premium experience as our Explore Journeys hub, this space helps you start smarter, stay motivated, and celebrate every milestone.</p>
            <div class="d-flex flex-column flex-sm-row gap-3">
                <a href="{{ route('login') }}" class="btn btn-light welcome-btn shadow-sm">Login to continue</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn btn-outline-light welcome-btn">Create free account</a>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4 mb-5" id="features">
        <div class="col-md-4">
            <article class="welcome-card h-100">
                <div class="icon-pill bg-primary bg-opacity-10 text-primary mb-3">
                    <i class="bi bi-stars"></i>
                </div>
                <h4 class="fw-bold mb-2">Adaptive Journeys</h4>
                <p class="text-muted">Each learning path responds to your pace with concise steps, immersive prompts, and token-aware progress tracking.</p>
            </article>
        </div>
        <div class="col-md-4">
            <article class="welcome-card h-100">
                <div class="icon-pill bg-success bg-opacity-10 text-success mb-3">
                    <i class="bi bi-magic"></i>
                </div>
                <h4 class="fw-bold mb-2">Editor-grade tools</h4>
                <p class="text-muted">Editors and institutions collaborate with the same polished UI—batch manage collections, tokens, and reports effortlessly.</p>
            </article>
        </div>
        <div class="col-md-4">
            <article class="welcome-card h-100">
                <div class="icon-pill bg-warning bg-opacity-10 text-warning mb-3">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h4 class="fw-bold mb-2">Signals that matter</h4>
                <p class="text-muted">Rich analytics, active-attempt reminders, and token alerts keep momentum high for learners, coaches, and admins.</p>
            </article>
        </div>
    </div>

    <div class="stats-grid mb-5" id="stats">
        <div class="row g-4 align-items-center">
            <div class="col-lg-4">
                <h3 class="mb-3">Built with the Explore Journeys DNA.</h3>
                <p class="mb-0 text-white-50">The same card system, pill filters, and Alpine-powered responsiveness now welcome every visitor.</p>
            </div>
            <div class="col-lg-8">
                <div class="row g-4">
                    <div class="col-6 col-md-3 stat-item text-center text-md-start">
                        <span>Journeys</span>
                        <strong>1,200+</strong>
                    </div>
                    <div class="col-6 col-md-3 stat-item text-center text-md-start">
                        <span>Learners</span>
                        <strong>35k</strong>
                    </div>
                    <div class="col-6 col-md-3 stat-item text-center text-md-start">
                        <span>Editors</span>
                        <strong>240</strong>
                    </div>
                    <div class="col-6 col-md-3 stat-item text-center text-md-start">
                        <span>Institutions</span>
                        <strong>80</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 rounded-5 shadow-sm p-4 p-md-5" id="cta">
        <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-4">
            <div>
                <p class="text-uppercase small text-muted mb-1">What’s inside</p>
                <h4 class="fw-bold mb-2">Token-led journeys, collaborative editors, and voice-ready attempts.</h4>
                <p class="text-muted mb-0">Already part of a cohort? Head to Journeys › Explore by difficulty, collection, or tags. New here? Sign in and let the system recommend your first path.</p>
            </div>
            <div class="d-flex gap-3 flex-wrap">
                <a href="{{ route('journeys.index') }}" class="btn btn-dark rounded-4 px-4 py-3">Browse Journeys</a>
                <a href="{{ route('tokens.index') }}" class="btn btn-outline-dark rounded-4 px-4 py-3">View Tokens</a>
            </div>
        </div>
    </div>
</section>
@endsection
