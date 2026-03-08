@extends('layouts.app')

@section('content')
<section class="shell">

    {{-- ══════════════════════════════════════
         HERO
         ══════════════════════════════════════ --}}
    <div id="hero" class="hero pink mb-5" style="flex-direction:column; align-items:center; text-align:center; padding:clamp(2.5rem,6vw,4.5rem) clamp(1.5rem,4vw,3rem);">
        <span class="badge bg-white text-dark text-uppercase mb-3" style="letter-spacing:.2em; font-size:.7rem;">TheThinkingCourse.com</span>
        <h1 style="max-width:760px;">Think Better. Decide Smarter.<br>Lead With Clarity.</h1>
        <p class="mb-2" style="max-width:600px; font-size:1.1rem;">Welcome to TheThinkingCourse.com</p>
        <p class="mb-2" style="max-width:600px;"><strong>Critical thinking isn't optional anymore.</strong><br>It's your competitive edge.</p>
        <p class="mb-4" style="max-width:600px; color:rgba(255,255,255,.8);">In a world of AI, misinformation, deepfakes and constant noise, the people who thrive are the ones who can analyse clearly, question confidently, and decide wisely.</p>
        <p class="fw-semibold mb-4">Start building that edge today.</p>
        <div class="d-flex flex-column flex-sm-row gap-3">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-light welcome-btn shadow-sm">Register free — get 20 tokens</a>
            @endif
            <a href="{{ route('login') }}" class="btn btn-outline-light welcome-btn" style="border-color:rgba(255,255,255,.7); color:#fff; border-width:2px;">Login</a>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         EXPERIENCE A LEARNING JOURNEY
         ══════════════════════════════════════ --}}
    <div id="journeys" class="mb-5" style="scroll-margin-top:5rem;">
        <div class="text-center mb-4">
            <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">This isn't passive learning</p>
            <h2 class="fw-bold mb-2">Experience a Learning Journey</h2>
            <p class="text-muted mx-auto" style="max-width:580px;">Every course is a conversational Learning Journey where you engage directly with your own AI tutor.</p>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <article class="welcome-card h-100">
                    <h5 class="fw-bold mb-3"><i class="bi bi-robot text-primary me-2"></i>Your AI tutor:</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Adapts to your life stage</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Understands where you live</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i>Connects with your interests</li>
                        <li class="mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i>Challenges your reasoning in real time</li>
                    </ul>
                </article>
            </div>
            <div class="col-lg-6">
                <article class="welcome-card h-100">
                    <p class="fw-bold mb-3">You don't just watch.<br>You respond. You think. You improve.</p>
                    <p class="text-muted mb-3">At the end of each journey, you receive a personalised <strong>Thinking Report</strong> outlining:</p>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Your strengths</li>
                        <li class="mb-2"><i class="bi bi-eye-fill text-warning me-2"></i>Your blind spots</li>
                        <li class="mb-0"><i class="bi bi-signpost-split-fill text-info me-2"></i>Clear areas to develop next</li>
                    </ul>
                </article>
            </div>
        </div>

        <div class="text-center">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-dark welcome-btn">Claim your 20 free tokens</a>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════
         STRUCTURED MASTERY — CRITICAL THINKING LEVELS
         ══════════════════════════════════════ --}}
    <div style="border-radius:32px; background:#fff; padding:clamp(2rem,5vw,3rem); margin-bottom:2.5rem; box-shadow:0 25px 60px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">
        <div class="text-center mb-4">
            <h2 class="fw-bold mb-2">Structured Mastery. Real Achievement.</h2>
            <p class="text-muted mx-auto" style="max-width:560px;">Our catalogue is organised into curated Collections of Learning Journeys. Complete a collection and earn a certificate detailing exactly which journeys you've completed and mastered.</p>
        </div>

        <div class="text-center mb-3">
            <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">Over 50 powerful titles</p>
            <h3 class="fw-bold mb-2">Critical Thinking Levels&nbsp;1–10</h3>
            <p class="text-muted">From entry level to advanced mastery.</p>
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
                    ['The Mindful Pause',                    'bi-pause-circle',      'primary'],
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

        <div class="text-center">
            <p class="text-muted mb-3">More than <strong>100 Learning Journeys</strong>. Structured. Progressive. Transformational.</p>
            <a href="{{ route('journeys.index') }}" class="btn btn-dark welcome-btn">Browse Learning Journeys</a>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         HOW TO BE HUMAN
         ══════════════════════════════════════ --}}
    <div id="human" style="scroll-margin-top:5rem; border-radius:32px; background:linear-gradient(135deg, #f0fdf4, #ecfdf5 40%, #e0f2fe); color:#1e293b; padding:clamp(2rem,5vw,3rem); margin-bottom:2.5rem; box-shadow:0 20px 40px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">

        {{-- Intro --}}
        <div class="mb-4">
            <p class="text-uppercase small mb-2" style="letter-spacing:.15em; color:#64748b;">Go Deeper</p>
            <h3 class="fw-bold mb-2" style="color:#0f172a;">How to Be Human</h3>
            <p style="color:#475569;" class="mb-0">Critical thinking isn't just about arguments. It's about understanding yourself. Our 4-collection series, <strong>How to Be Human</strong>, explores how our ancient survival wiring still shapes:</p>
        </div>

        {{-- 4 topic cards --}}
        <div style="display:grid; grid-template-columns:repeat(2, 1fr); gap:.75rem;" class="mb-4 human-topic-grid">
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
         TOKEN MODEL
         ══════════════════════════════════════ --}}
    <div id="tokens" class="text-center mb-4" style="scroll-margin-top:5rem;">
        <h2 class="fw-bold mb-2">Flexible. Simple. On&nbsp;Your&nbsp;Terms.</h2>
        <p class="text-muted mx-auto" style="max-width:500px;">Purchase tokens. Use them to unlock Learning Journeys.<br>No subscriptions. No rigid schedules. No wasted content.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <article class="welcome-card h-100 text-center">
                <div class="icon-pill bg-success bg-opacity-10 text-success mb-3 mx-auto"><i class="bi bi-gift"></i></div>
                <h4 class="fw-bold mb-2">20 free tokens</h4>
                <p class="text-muted mb-0">When you register, you receive 20 free tokens immediately.</p>
            </article>
        </div>
        <div class="col-md-6">
            <article class="welcome-card h-100 text-center">
                <div class="icon-pill bg-primary bg-opacity-10 text-primary mb-3 mx-auto"><i class="bi bi-coin"></i></div>
                <h4 class="fw-bold mb-2">Pay for what you use</h4>
                <p class="text-muted mb-0">Purchase more tokens anytime. No pressure — learn at your own pace.</p>
            </article>
        </div>
    </div>

    <div class="text-center mb-5">
        @if (Route::has('register'))
            <a href="{{ route('register') }}" class="btn btn-dark welcome-btn">Register now — start thinking sharper</a>
        @endif
    </div>

    {{-- ══════════════════════════════════════
         BENEFITS STRIP
         ══════════════════════════════════════ --}}
    <div style="border-radius:32px; background:#fff; padding:clamp(2rem,4vw,3rem); margin-bottom:2.5rem; box-shadow:0 25px 60px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">
        <div class="text-center mb-4">
            <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">The Future Belongs to Clear Thinkers</p>
            <h3 class="fw-bold">Sharper thinking improves</h3>
        </div>
        <div class="mb-4 benefits-grid" style="display:grid; gap:1rem;">
            @foreach (['Decision-making' => 'bi-signpost-split', 'Leadership' => 'bi-people', 'Communication' => 'bi-chat-dots', 'Media literacy' => 'bi-tv', 'Confidence' => 'bi-star', 'Independence' => 'bi-compass'] as $benefit => $icon)
            <div class="text-center">
                <div class="icon-pill bg-primary bg-opacity-10 text-primary mb-2 mx-auto"><i class="bi {{ $icon }}"></i></div>
                <p class="fw-semibold small mb-0">{{ $benefit }}</p>
            </div>
            @endforeach
        </div>
        <p class="text-center text-muted mb-2">The ability to think critically will define the next generation of leaders.</p>
        <p class="text-center fw-bold mb-0">The only question is — will you train it?</p>
    </div>

    {{-- ══════════════════════════════════════
         FINAL CTA
         ══════════════════════════════════════ --}}
    <div id="cta" style="scroll-margin-top:5rem; border-radius:32px; background:linear-gradient(135deg, var(--lj-brand-rich), #0f766e 70%); color:#fff; text-align:center; padding:clamp(2.5rem,6vw,4rem) clamp(1.5rem,4vw,3rem); box-shadow:0 30px 70px rgba(7,25,23,.4);">
        <h2 class="fw-bold mb-2" style="max-width:580px; margin:0 auto;">Start Your First Learning Journey Today</h2>
        <p class="mb-1 mx-auto" style="max-width:500px; color:rgba(255,255,255,.85);">Claim your 20 free tokens.<br>Meet your AI tutor.<br>Unlock your thinking advantage.</p>
        <p class="mb-3 mx-auto" style="color:rgba(255,255,255,.55); max-width:460px;">Register now. Begin your journey. Build your superpower.</p>
        <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center mb-3">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn btn-light welcome-btn shadow-sm">Register now — it's free</a>
            @endif
            <a href="{{ route('journeys.index') }}" class="btn btn-outline-light welcome-btn" style="border-color:rgba(255,255,255,.6); color:#fff;">Browse Journeys</a>
        </div>
        <p class="mb-0 small" style="color:rgba(255,255,255,.35);">Welcome to TheThinkingCourse.com</p>
    </div>

    {{-- ══════════════════════════════════════
         CONTACT US
         ══════════════════════════════════════ --}}
    <div id="contact" style="scroll-margin-top:5rem; border-radius:32px; background:#fff; padding:clamp(2rem,5vw,3rem); margin-top:2.5rem; margin-bottom:2.5rem; box-shadow:0 25px 60px rgba(15,23,42,.08); border:1px solid rgba(15,23,42,.06);">
        <div class="text-center mb-4">
            <p class="text-uppercase small text-muted mb-1" style="letter-spacing:.15em;">Get In Touch</p>
            <h2 class="fw-bold mb-2">Contact Us</h2>
            <p class="text-muted mx-auto" style="max-width:500px;">Have a question, suggestion or just want to say hello? Drop us a message and we'll get back to you.</p>
        </div>

        @if(session('contact_success'))
            <div class="alert alert-success text-center mx-auto" style="max-width:560px;">
                <i class="bi bi-check-circle-fill me-2"></i>{{ session('contact_success') }}
            </div>
        @endif
        @if(session('contact_error'))
            <div class="alert alert-danger text-center mx-auto" style="max-width:560px;">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('contact_error') }}
            </div>
        @endif

        <form action="{{ route('contact.send') }}" method="POST" class="mx-auto" style="max-width:560px;">
            @csrf
            <div class="mb-3">
                <label for="contact_name" class="form-label fw-semibold">Name</label>
                <input type="text" class="form-control @error('contact_name') is-invalid @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name') }}" required maxlength="100" placeholder="Your name">
                @error('contact_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="contact_email" class="form-label fw-semibold">Email</label>
                <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" required maxlength="255" placeholder="you@example.com">
                @error('contact_email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="contact_message" class="form-label fw-semibold">Message</label>
                <textarea class="form-control @error('contact_message') is-invalid @enderror" id="contact_message" name="contact_message" rows="5" required maxlength="5000" placeholder="How can we help?">{{ old('contact_message') }}</textarea>
                @error('contact_message')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
                <div class="mb-3">
                    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                    @error('g-recaptcha-response')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            @endif
            <div class="text-center">
                <button type="submit" class="btn btn-dark welcome-btn">
                    <i class="bi bi-send me-2"></i>Send Message
                </button>
            </div>
        </form>
    </div>

</section>
@endsection

@if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
    @push('scripts')
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endpush
@endif
