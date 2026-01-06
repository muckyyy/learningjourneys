@extends('layouts.app')

@push('styles')
<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.journey-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.05) !important;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
    padding: clamp(1.25rem, 2vw, 2rem);
}
.journey-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0,0,0,0.08) !important;
}
.journeys-stack {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.journey-line {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    align-items: center;
}
.journey-line-top {
    justify-content: space-between;
    gap: 1rem;
}
.journey-line-bottom {
    border-top: 1px dashed rgba(0, 0, 0, 0.08);
    padding-top: 0.75rem;
    justify-content: space-between;
    gap: 1rem;
}
.journey-title-wrap {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
}
.journey-title-wrap h4 {
    font-size: 1.1rem;
    margin-bottom: 0;
}
.journey-token-pill {
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 999px;
    padding: 0.45rem 1.1rem;
    font-weight: 600;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.journey-status-chip {
    border-radius: 16px;
    padding: 0.5rem 1.1rem;
    min-width: 170px;
    display: inline-flex;
    flex-direction: column;
    gap: 0.15rem;
    font-size: 0.85rem;
}
.journey-status-chip .status-label {
    font-weight: 600;
    letter-spacing: 0.02em;
}
.journey-status-chip .status-meta {
    font-size: 0.78rem;
    opacity: 0.85;
}
.journey-status-chip.status-complete {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.journey-status-chip.status-progress {
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
}
.journey-status-chip.status-idle {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.journey-description {
    font-size: 0.95rem;
    color: #4b5563;
    line-height: 1.5;
    margin-bottom: 0;
}
.journey-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    font-size: 0.9rem;
    color: #475569;
    flex: 1 1 auto;
}
.journey-card-meta i {
    color: #94a3b8;
}
.journey-card-actions {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    flex: 1 1 auto;
    min-width: 220px;
}
.journey-card-actions .btn {
    width: 100%;
}
.difficulty-beginner { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
.difficulty-intermediate { background: #fffbeb; color: #d97706; border: 1px solid #fef3c7; }
.difficulty-advanced { background: #fff1f2; color: #be123c; border: 1px solid #ffe4e6; }
.token-badge { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }

.search-control { box-shadow: inset 0 0 0 1px rgba(0,0,0,0.03); min-height: 64px; font-size: 1rem; }
.search-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
.search-icon {
    position: absolute;
    left: 1.5rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}
.filter-btn {
    width: 64px;
    height: 64px;
    border: 1px solid rgba(0,0,0,0.05);
    background-color: #fff;
    border-radius: 20px;
}
.search-filter-row { display: flex; align-items: center; gap: 1rem; }
.category-pill {
    border-width: 1px;
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: 0.92rem;
    padding: 0.4rem 1.35rem;
}
.category-pill.is-active {
    background-color: #0f172a;
    color: #fff;
    border-color: #0f172a;
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.15);
}
.category-pill.is-inactive {
    background-color: #fff;
    color: #495057;
    border-color: #dfe3ea;
}
.category-scroller { padding-bottom: 0.25rem; }
.journey-meta i { color: #adb5bd; }
.journey-meta span { font-size: 0.875rem; color: #6c757d; }
[x-cloak] { display: none !important; }
.explore-shell {
    width: min(1200px, 100%);
    max-width: 100%;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.journeys-hero {
    background: linear-gradient(135deg, #0f172a, #2563eb 70%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 5vw, 4rem);
    margin-bottom: 2.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
}
.journeys-hero h1 {
    font-size: clamp(2.1rem, 4.5vw, 3rem);
}
.journeys-hero p {
    color: rgba(255, 255, 255, 0.78);
    max-width: 520px;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    letter-spacing: 0.16em;
    font-size: 0.8rem;
    text-transform: uppercase;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
}
.meta-chip {
    background: rgba(255, 255, 255, 0.18);
    border-radius: 18px;
    padding: 0.85rem 1.25rem;
    min-width: 150px;
}
.meta-chip span {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.7);
}
.meta-chip strong {
    display: block;
    font-size: 1.35rem;
    font-weight: 700;
    color: #fff;
}
.hero-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-left: auto;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.9rem;
    font-weight: 600;
}
.hero-actions .btn-outline-light {
    border-width: 2px;
}
.hero-actions .btn-light {
    color: #0f172a;
}
.hero-kicker { letter-spacing: 0.18em; font-size: 0.85rem; }
.hero-title { font-size: clamp(2rem, 5.6vw, 3rem); }
.hero-subtitle { font-size: clamp(1rem, 3.2vw, 1.25rem); max-width: 520px; }
@media (max-width: 575.98px) {
    .filter-btn { width: 56px; height: 56px; }
    .search-filter-row { gap: 0.75rem; }
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
}
@media (max-width: 991.98px) {
    main.main-content {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden;
    }
    main.main-content > .container-fluid {
        padding-left: 0;
        padding-right: 0;
    }
}
@media (min-width: 992px) {
    .journey-card-actions {
        flex-direction: row;
        justify-content: flex-end;
    }
    .journey-card-actions .btn {
        width: auto;
    }
}
</style>
@endpush

@section('content')
@php
    $defaultCategories = collect(['All', 'Philosophy', 'Logic', 'Science', 'History', 'Wellness', 'Creativity']);
    $journeyCategories = $journeys->map(function ($journey) {
        $raw = $journey->primary_category
            ?? optional($journey->collection)->name
            ?? collect(explode(',', (string) $journey->tags))->first()
            ?? 'General';

        return (string) \Illuminate\Support\Str::of($raw)->squish()->title();
    })->filter();
    $categories = $defaultCategories->merge($journeyCategories)->unique()->values();
    $categoryCounts = $categories->mapWithKeys(fn ($category) => [$category => 0]);
    $journeyCollection = $journeys instanceof \Illuminate\Contracts\Pagination\Paginator ? collect($journeys->items()) : \Illuminate\Support\Collection::wrap($journeys);
    foreach ($journeys as $journey) {
        $rawCategory = $journey->primary_category
            ?? optional($journey->collection)->name
            ?? collect(explode(',', (string) $journey->tags))->first()
            ?? 'General';
        $formattedCategory = (string) \Illuminate\Support\Str::of($rawCategory)->squish()->title();
        $categoryCounts[$formattedCategory] = ($categoryCounts[$formattedCategory] ?? 0) + 1;
    }
    $categoryCounts['All'] = $journeys->count();
    $totalJourneys = method_exists($journeys, 'total') ? $journeys->total() : $journeys->count();
    $publishedJourneys = $journeyCollection->where('is_published', true)->count();
    $journeyProgress = $journeyProgress ?? collect();
@endphp

<div class="explore-shell"
     x-data='{
        activeCategory: @json(request("category", "All")),
        counts: @json($categoryCounts),
        get hasMatches() {
            const key = this.activeCategory;
            const normalizedKey = key in this.counts ? key : "All";
            return (this.counts[normalizedKey] ?? 0) > 0;
        },
        setCategory(value) { this.activeCategory = value; }
     }'>

    <div class="journeys-hero">
        <div class="flex-grow-1">
            <div class="hero-pill mb-3"><i class="bi bi-compass"></i> Library</div>
            <h1 class="hero-title fw-bold mb-2">Explore Journeys</h1>
            <p class="hero-subtitle mb-0">Curated, mobile-first learning paths inspired by world-class apps.</p>
            <div class="hero-meta">
                <div class="meta-chip">
                    <span>Journeys</span>
                    <strong>{{ number_format($totalJourneys) }}</strong>
                </div>
                <div class="meta-chip">
                    <span>Categories</span>
                    <strong>{{ number_format($categories->count()) }}</strong>
                </div>
                <div class="meta-chip">
                    <span>Published</span>
                    <strong>{{ number_format($publishedJourneys) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            @can('create', App\Models\Journey::class)
                <a href="{{ route('journeys.create') }}" class="btn btn-light text-dark"><i class="bi bi-plus-circle me-2"></i>New Journey</a>
            @endcan
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light"><i class="bi bi-speedometer"></i>Dashboard</a>
        </div>
    </div>

    @if(Auth::user()->role === 'regular' && isset($activeAttempt) && $activeAttempt)
        <div class="alert alert-warning rounded-4 border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                <div>
                    <h5 class="alert-heading d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-exclamation-triangle"></i> Active Journey in Progress
                    </h5>
                    <p class="mb-1">You are currently exploring <strong>{{ $activeAttempt->journey->title }}</strong>.</p>
                    <p class="mb-0">Complete or abandon this path before starting a new one.</p>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-sm-auto">
                    <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-dark flex-grow-1">
                        <i class="bi bi-arrow-right-circle"></i> Continue
                    </a>
                    <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="flex-shrink-0 align-self-start">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger px-4 rounded-4"
                            onclick="return confirm('Are you sure you want to abandon your current journey? Your progress will be lost.')">
                            <i class="bi bi-x-circle"></i> Abandon
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="search-filter-row mb-4">
        <form method="GET" action="{{ route('journeys.index') }}" class="flex-grow-1 position-relative">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control form-control-lg rounded-pill border-0 bg-light ps-5 py-3 search-control"
                   name="search" value="{{ request('search') }}" placeholder="Search journeys, topics, mentors...">
            <input type="hidden" name="category" :value="activeCategory">
        </form>
        <button class="filter-btn shadow-sm d-flex align-items-center justify-content-center flex-shrink-0" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#journeyFilters" aria-controls="journeyFilters">
            <i class="bi bi-sliders text-dark fs-4"></i>
        </button>
    </div>

    <div class="category-scroller overflow-auto scrollbar-hide mb-4" role="tablist" aria-label="Journey categories">
        <div class="d-flex flex-nowrap gap-2">
            @foreach($categories as $category)
                <button type="button"
                        class="btn category-pill rounded-pill"
                        :class='activeCategory === @json($category) ? "is-active" : "is-inactive"'
                        @click='setCategory(@json($category))'>
                    {{ $category }}
                </button>
            @endforeach
        </div>
    </div>

    @if($journeys->count() > 0)
        <div x-show="hasMatches" x-cloak>
            <div class="journeys-stack">
                @foreach($journeys as $journey)
                @php
                    $rawCategory = $journey->primary_category
                        ?? optional($journey->collection)->name
                        ?? collect(explode(',', (string) $journey->tags))->first()
                        ?? 'General';
                    $journeyCategory = (string) \Illuminate\Support\Str::of($rawCategory)->squish()->title();
                    $difficultyClass = match($journey->difficulty_level) {
                        'beginner' => 'difficulty-beginner',
                        'intermediate' => 'difficulty-intermediate',
                        default => 'difficulty-advanced'
                    };
                    $difficultyLabel = \Illuminate\Support\Str::of($journey->difficulty_level ?? 'beginner')->title();
                    $stepsCount = $journey->steps->count();
                    $tokenCopy = $journey->token_cost > 0 ? number_format($journey->token_cost) . ' tokens' : 'Free';
                    $progress = $journeyProgress->get($journey->id);
                    $isCompleted = (bool) ($progress['completed'] ?? false);
                    $latestStatus = $progress['latest_status'] ?? null;
                    $statusClass = $isCompleted ? 'status-complete' : ($latestStatus === 'in_progress' ? 'status-progress' : 'status-idle');
                    if ($isCompleted) {
                        $statusLabel = 'Completed';
                        $statusSubline = !empty($progress['completed_at'])
                            ? 'Finished ' . $progress['completed_at']->format('M j, Y')
                            : 'Finished this journey';
                    } elseif ($latestStatus === 'in_progress') {
                        $statusLabel = 'In progress';
                        $statusSubline = 'Resume where you left off';
                    } else {
                        $statusLabel = 'Not started';
                        $statusSubline = 'You have not started yet';
                    }
                @endphp
                <article class="journey-card rounded-5 bg-white shadow-sm" x-show='activeCategory === "All" || activeCategory === @json($journeyCategory)' x-cloak>
                    <div class="journey-line journey-line-top">
                        <div class="journey-title-wrap">
                            <span class="badge rounded-pill px-3 py-2 {{ $difficultyClass }}">{{ $difficultyLabel }}</span>
                            <h4 class="fw-semibold mb-0">{{ $journey->title }}</h4>
                        </div>
                        <div class="d-flex align-items-center gap-3 ms-lg-auto">
                            <span class="journey-token-pill"><i class="bi bi-coin"></i>{{ $tokenCopy }}</span>
                            <div class="journey-status-chip {{ $statusClass }}">
                                <span class="status-label">{{ $statusLabel }}</span>
                                <span class="status-meta">{{ $statusSubline }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="journey-description">
                        {{ \Illuminate\Support\Str::limit($journey->description, 160) }}
                    </p>
                    <div class="journey-line journey-line-bottom">
                        <div class="journey-card-meta">
                            <span class="d-inline-flex align-items-center gap-2">
                                <i class="bi bi-clock"></i>{{ $journey->estimated_duration ? $journey->estimated_duration . ' min' : 'Flexible' }}
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <i class="bi bi-tag"></i>{{ $journeyCategory }}
                            </span>
                            <span class="d-inline-flex align-items-center gap-2">
                                <i class="bi bi-stars"></i>{{ $stepsCount }} steps
                            </span>
                        </div>
        
                        <div class="journey-card-actions">
                            @can('update', $journey)
                                <div class="d-flex flex-column flex-sm-row gap-2 w-100">
                                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-dark rounded-4 py-2">Preview</a>
                                    <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-dark rounded-4 py-2">Edit</a>
                                </div>
                            @else
                                @if($journey->is_published && $stepsCount > 0 && !$activeAttempt)
                                    <button type="button" class="btn btn-dark rounded-4 py-2"
                                            onclick="window.JourneyStartModal.showStartJourneyModal({{ $journey->id }}, '{{ addslashes($journey->title) }}', 'voice', {{ (int) $journey->token_cost }})">
                                        {{ $isCompleted ? 'Restart Journey' : 'Start Journey' }}
                                    </button>
                                @elseif($activeAttempt && $activeAttempt->journey_id === $journey->id)
                                    <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-warning rounded-4 py-2">
                                        Continue Journey
                                    </a>
                                @elseif($activeAttempt)
                                    <div class="alert alert-light border-0 text-muted small mb-0">
                                        <i class="bi bi-info-circle"></i> Finish your active journey first.
                                    </div>
                                @else
                                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-dark rounded-4 py-2">
                                        View Details
                                    </a>
                                @endif
                            @endcan
                        </div>
                    </div>
                </article>
                @endforeach
            </div>

            <div class="d-flex justify-content-center mt-4">
                {{ $journeys->links() }}
            </div>
        </div>

        <div class="text-center py-5" x-show="!hasMatches" x-cloak>
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-search text-muted fs-4"></i>
            </div>
            <h4 class="fw-bold mb-2">No journeys found</h4>
            <p class="text-muted mb-4">We couldn't find any journeys under <span class="fw-semibold" x-text="activeCategory"></span>. Try another category or reset filters.</p>
            <button type="button" class="btn btn-outline-dark rounded-4 px-4" @click="setCategory('All')">Clear Filters</button>
        </div>
    @else
        <div class="text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 96px; height: 96px;">
                <i class="bi bi-map text-muted fs-2"></i>
            </div>
            <h3 class="fw-bold">No journeys yet</h3>
            <p class="text-muted mb-4">When new paths are published, they will appear here with fresh insights.</p>
            @can('create', App\Models\Journey::class)
                <a href="{{ route('journeys.create') }}" class="btn btn-dark rounded-4 px-4 py-3">
                    <i class="bi bi-plus-circle me-2"></i>Create the first journey
                </a>
            @endcan
        </div>
    @endif
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="journeyFilters" aria-labelledby="journeyFiltersLabel">
    <div class="offcanvas-header">
        <h5 id="journeyFiltersLabel">Filters</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <form method="GET" action="{{ route('journeys.index') }}" class="d-flex flex-column gap-4">
            <div>
                <label class="form-label fw-semibold">Difficulty</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="difficulty[]" value="{{ $value }}" id="difficulty-{{ $value }}"
                                   {{ collect(request('difficulty', []))->contains($value) ? 'checked' : '' }}>
                            <label class="form-check-label" for="difficulty-{{ $value }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
            <div>
                <label class="form-label fw-semibold">Token Cost</label>
                <select class="form-select" name="token_range">
                    <option value="">Any</option>
                    <option value="free" {{ request('token_range') === 'free' ? 'selected' : '' }}>Free only</option>
                    <option value="under-25" {{ request('token_range') === 'under-25' ? 'selected' : '' }}>Under 25 tokens</option>
                    <option value="premium" {{ request('token_range') === 'premium' ? 'selected' : '' }}>25+ tokens</option>
                </select>
            </div>
            <div>
                <label class="form-label fw-semibold">Category</label>
                <select class="form-select" name="category">
                    @foreach($categories as $category)
                        <option value="{{ $category }}" {{ request('category', 'All') === $category ? 'selected' : '' }}>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-dark rounded-4 py-3">Apply Filters</button>
        </form>
    </div>
</div>

<!-- Start Journey Confirmation Modal -->
<div class="modal fade" id="startJourneyModal" tabindex="-1" aria-labelledby="startJourneyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startJourneyModalLabel">Start Learning Journey</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to start <strong id="journeyTypeText">chat</strong> journey for:</p>
                <h6 id="journeyTitleText">Journey Title</h6>
                <p class="text-primary mb-2">
                    <i class="bi bi-coin"></i>
                    Cost: <span id="journeyCostText">0 tokens (Free)</span>
                </p>
                <p class="text-muted mb-0">This will create a new learning session and you can track your progress.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStartJourney">
                    <span class="spinner-border spinner-border-sm d-none" id="startJourneySpinner" role="status" aria-hidden="true"></span>
                    <span id="startJourneyText">Yes, Start Journey</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
