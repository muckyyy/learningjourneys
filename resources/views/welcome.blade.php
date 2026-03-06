@extends('layouts.app')

@section('content')
<section class="shell">

    {{-- ══════════════════════════════════════
         HERO
         ══════════════════════════════════════ --}}
    <div id="hero" class="hero pink mb-5" style="flex-direction:column; align-items:center; text-align:center; padding:clamp(2.5rem,6vw,4.5rem) clamp(1.5rem,4vw,3rem);">
        <span class="badge bg-white text-dark text-uppercase mb-3" style="letter-spacing:.2em; font-size:.7rem;">TheThinkingCourse.com</span>
        <h1 style="max-width:720px;">Structured Mastery.<br>Real Achievement.</h1>
        <p class="mb-4" style="max-width:560px;">Our catalogue is organised into curated Collections of Learning Journeys. Complete a collection and earn a certificate detailing exactly which journeys you've completed and mastered.</p>
        <div class="d-flex flex-column flex-sm-row gap-3">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-light welcome-btn shadow-sm">Register free — get 20 tokens</a>
            @endif
            <a href="{{ route('login') }}" class="btn btn-outline-light welcome-btn" style="border-color:rgba(255,255,255,.7); color:#fff; border-width:2px;">Login</a>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         CRITICAL THINKING LEVELS 1-10
         ══════════════════════════════════════ --}}
    <div id="journeys" class="text-center mb-4" style="scroll-margin-top:5rem;">
        <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">Over 50 powerful titles</p>
        <h2 class="fw-bold mb-2">Critical Thinking Levels&nbsp;1–10</h2>
        <p class="text-muted mx-auto" style="max-width:520px;">From entry level to advanced mastery. More than <strong>100 Learning Journeys</strong>. Structured. Progressive. Transformational.</p>
    </div>

    <div class="row g-3 mb-4">
        @php
            $titles = [
                ['Critical Thinking is Your Superpower', 'bi-lightning-charge', 'primary'],
                ['Fact or Opinion',                      'bi-patch-question',    'success'],
                ['The Assumption Trap',                  'bi-exclamation-triangle','warning'],
                ['Logic 101',                            'bi-diagram-3',         'info'],
                ['Countering the Strawman',              'bi-shield-exclamation','danger'],
                ['Spotting Fake News in 30&nbsp;Seconds','bi-newspaper',         'secondary'],
            ];
        @endphp
        @foreach ($titles as [$name, $icon, $color])
        <div class="col-6 col-md-4">
            <article class="welcome-card h-100 d-flex align-items-center gap-3" style="padding:1.25rem 1.5rem;">
                <div class="icon-pill bg-{{ $color }} bg-opacity-10 text-{{ $color }}" style="width:52px;height:52px;min-width:52px;border-radius:16px;font-size:1.25rem;">
                    <i class="bi {{ $icon }}"></i>
                </div>
                <h6 class="fw-bold mb-0">{!! $name !!}</h6>
            </article>
        </div>
        @endforeach
    </div>

    <div class="text-center mb-5">
        <p class="text-muted mb-3"><em>…and many more</em></p>
        <a href="{{ route('journeys.index') }}" class="btn btn-dark welcome-btn">Choose your level</a>
    </div>

    {{-- ══════════════════════════════════════
         HOW TO BE HUMAN  (dark panel)
         ══════════════════════════════════════ --}}
    <div id="human" style="scroll-margin-top:5rem; border-radius:32px; background:linear-gradient(135deg, #f0fdf4, #ecfdf5 40%, #e0f2fe); color:#1e293b; padding:clamp(2rem,5vw,3rem); margin-bottom:2.5rem; box-shadow:0 20px 40px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">

        {{-- Intro --}}
        <div class="d-flex flex-column flex-lg-row gap-4 align-items-lg-center mb-4">
            <div class="flex-grow-1">
                <p class="text-uppercase small mb-2" style="letter-spacing:.15em; color:#64748b;">Go Deeper</p>
                <h3 class="fw-bold mb-2" style="color:#0f172a;">How to Be Human</h3>
                <p style="color:#475569;" class="mb-0">Critical thinking isn't just about arguments. It's about understanding yourself. Our 4-collection series explores how our ancient survival wiring still shapes everything we do.</p>
            </div>
        </div>

        {{-- 4 topic cards in a row --}}
        <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:.75rem;" class="mb-4">
            @foreach (['How we react' => 'bi-emoji-angry', 'How we fear' => 'bi-shield-lock', 'How we compete' => 'bi-trophy', 'How we succeed' => 'bi-rocket-takeoff'] as $topic => $tIcon)
            <div class="text-center" style="padding:clamp(1rem,2vw,1.5rem); border-radius:16px; background:rgba(255,255,255,.7); border:1px solid rgba(15,23,42,.08); backdrop-filter:blur(4px);">
                <i class="bi {{ $tIcon }} d-block mb-2" style="font-size:1.5rem; color:#0d9488;"></i>
                <strong style="font-size:.95rem; color:#0f172a;">{{ $topic }}</strong>
            </div>
            @endforeach
        </div>

        {{-- Learn how to --}}
        <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end" style="border-top:1px solid rgba(15,23,42,.08); padding-top:1.25rem;">
            <div>
                <h6 class="fw-bold mb-2" style="color:#0f172a;">You'll learn how to:</h6>
                <ul class="list-unstyled mb-0" style="color:#334155;">
                    <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Recognise destructive instincts</li>
                    <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Override outdated impulses</li>
                    <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i>Strengthen traits that drive success</li>
                </ul>
            </div>
            <div class="ms-md-auto text-md-end">
                <p class="fw-semibold mb-1" style="color:#0f172a;">Understand your nature. Then master it.</p>
                <p class="small mb-0" style="color:#64748b;">Start your first Human Collection and see yourself differently.</p>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         TOKEN MODEL — 3 cards
         ══════════════════════════════════════ --}}
    <div id="tokens" class="text-center mb-4" style="scroll-margin-top:5rem;">
        <h2 class="fw-bold mb-2">Flexible. Simple. On&nbsp;Your&nbsp;Terms.</h2>
        <p class="text-muted mx-auto" style="max-width:440px;">No subscriptions. No rigid schedules. No wasted content.</p>
    </div>

    <div class="row g-4 mb-5">
        @php
            $tokenCards = [
                ['Purchase tokens',  'bi-coin',       'primary', 'Use them to unlock Learning Journeys — pay only for what you actually use.'],
                ['20 free tokens',   'bi-gift',       'success', 'When you register you receive 20 free tokens immediately. Start thinking sharper within minutes.'],
                ['No expiry stress', 'bi-calendar-x', 'warning', 'Learn at the pace and schedule that suits you. Your tokens stay until you spend them.'],
            ];
        @endphp
        @foreach ($tokenCards as [$heading, $icon, $color, $desc])
        <div class="col-md-4">
            <article class="welcome-card h-100 text-center">
                <div class="icon-pill bg-{{ $color }} bg-opacity-10 text-{{ $color }} mb-3 mx-auto"><i class="bi {{ $icon }}"></i></div>
                <h4 class="fw-bold mb-2">{{ $heading }}</h4>
                <p class="text-muted mb-0">{{ $desc }}</p>
            </article>
        </div>
        @endforeach
    </div>

    {{-- ══════════════════════════════════════
         BENEFITS STRIP
         ══════════════════════════════════════ --}}
    <div style="border-radius:32px; background:#fff; padding:clamp(2rem,4vw,3rem); margin-bottom:2.5rem; box-shadow:0 25px 60px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">
        <div class="text-center mb-4">
            <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">The Future Belongs to Clear Thinkers</p>
            <h3 class="fw-bold">Sharper thinking improves</h3>
        </div>
        <div style="display:grid; grid-template-columns:repeat(6, 1fr); gap:1rem;" class="mb-4">
            @foreach (['Decision-making' => 'bi-signpost-split', 'Leadership' => 'bi-people', 'Communication' => 'bi-chat-dots', 'Media literacy' => 'bi-tv', 'Confidence' => 'bi-star', 'Independence' => 'bi-compass'] as $benefit => $icon)
            <div class="text-center">
                <div class="icon-pill bg-primary bg-opacity-10 text-primary mb-2 mx-auto"><i class="bi {{ $icon }}"></i></div>
                <p class="fw-semibold small mb-0">{{ $benefit }}</p>
            </div>
            @endforeach
        </div>
        <p class="text-center text-muted mb-0">The ability to think critically will define the next generation of leaders.<br class="d-none d-md-inline"><strong>The only question is — will you train it?</strong></p>
    </div>

    {{-- ══════════════════════════════════════
         FINAL CTA
         ══════════════════════════════════════ --}}
    <div id="cta" style="scroll-margin-top:5rem; border-radius:32px; background:linear-gradient(135deg, var(--lj-brand-rich), #0f766e 70%); color:#fff; text-align:center; padding:clamp(2.5rem,6vw,4rem) clamp(1.5rem,4vw,3rem); box-shadow:0 30px 70px rgba(7,25,23,.4);">
        <h2 class="fw-bold mb-2" style="max-width:580px; margin:0 auto;">Start Your First Learning Journey Today</h2>
        <p class="mb-1 mx-auto" style="max-width:500px; color:rgba(255,255,255,.85);">Claim your 20 free tokens. Meet your AI tutor. Unlock your thinking advantage.</p>
        <p class="mb-3 mx-auto" style="color:rgba(255,255,255,.55); max-width:460px;">Register now. Begin your journey. Build your superpower.</p>
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mb-3">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-light welcome-btn shadow-sm">Register now — it's free</a>
            @endif
            <a href="{{ route('journeys.index') }}" class="btn btn-outline-light welcome-btn" style="border-color:rgba(255,255,255,.6); color:#fff;">Browse Journeys</a>
        </div>
        <p class="mb-0 small" style="color:rgba(255,255,255,.35);">Welcome to TheThinkingCourse.com</p>
    </div>

</section>
@endsection
