@extends('layouts.app')

@section('content')
@php
    $defaultCategories = collect(['All']);
    $journeyCategories = $journeys->map(function ($journey) {
        $raw = $journey->primary_category
            ?? optional($journey->collection)->name
            ?? collect(explode(',', (string) $journey->tags))->first()
            ?? 'General';

        return (string) \Illuminate\Support\Str::of($raw)->squish()->title();
    })->filter();
    $collectionCategories = $collections->pluck('name')
        ->filter()
        ->map(fn ($name) => (string) \Illuminate\Support\Str::of($name)->squish()->title());
    $categories = $defaultCategories
        ->merge($journeyCategories)
        ->merge($collectionCategories)
        ->unique()
        ->values();
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
    $collections = $collections ?? collect();
    $searchTerm = trim($searchTerm ?? request('search', ''));
    $highlightTerms = collect(preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY))->unique();
    $highlightPattern = $highlightTerms->isNotEmpty()
        ? '/(' . $highlightTerms->map(fn ($term) => preg_quote($term, '/'))->implode('|') . ')/i'
        : null;
    $highlightText = function ($text) use ($highlightPattern) {
        $safe = e($text);
        if (!$highlightPattern) {
            return $safe;
        }
        return preg_replace($highlightPattern, '<mark class="keyword-highlight">$1</mark>', $safe);
    };

    $collectionCompletionCounts = collect($collectionCompletionCounts ?? []);
    $collectionPublishedJourneyCounts = $collections
        ->filter(fn ($collection) => isset($collection->id))
        ->mapWithKeys(fn ($collection) => [$collection->id => (int) ($collection->published_journeys_count ?? 0)]);
    $isAdminUser = auth()->check() && auth()->user()->role === 'administrator';

    $journeyGroups = $journeyCollection
        ->filter(fn ($journey) => (bool) $journey->collection)
        ->groupBy(fn ($journey) => $journey->collection->id)
        ->map(function ($group) use ($journeyProgress, $collectionCompletionCounts, $collectionPublishedJourneyCounts) {
            $firstJourney = $group->first();
            $collection = $firstJourney?->collection;
            $collectionId = $collection?->id;
            $label = $collection?->name ?? 'Collection';
            $stateKey = 'collection-' . ($collectionId ?? 'unknown');
            $panelId = $stateKey . '-panel';
            $sortedJourneys = $group
                ->sortBy(fn ($journey) => \Illuminate\Support\Str::lower($journey->title ?? ''))
                ->values();
            $completedCount = $collectionId ? (int) ($collectionCompletionCounts[$collectionId] ?? 0) : 0;
            $totalCount = $collectionId ? (int) ($collectionPublishedJourneyCounts[$collectionId] ?? $sortedJourneys->count()) : $sortedJourneys->count();
            $percentComplete = $totalCount > 0 ? (int) round(($completedCount / $totalCount) * 100) : 0;

            return [
                'label' => $label,
                'description' => optional($collection)->description,
                'collection_id' => $collectionId,
                'state_key' => $stateKey,
                'panel_id' => $panelId,
                'journeys' => $sortedJourneys,
                'completed' => $completedCount,
                'total' => $totalCount,
                'percent' => $percentComplete,
            ];
        })
        ->sortBy('label', SORT_NATURAL)
        ->values();
@endphp

<div class="shell"
     x-data='{
        activeCategory: @json(request("category", "All")),
        counts: @json($categoryCounts),
        activeCollection: null,
        searchActive: @json($searchTerm !== ''),
        get hasMatches() {
            const key = this.activeCategory;
            const normalizedKey = key in this.counts ? key : "All";
            return (this.counts[normalizedKey] ?? 0) > 0;
        },
        setCategory(value) { this.activeCategory = value; },
        toggleCollection(value) {
            if (this.searchActive) {
                return;
            }
            this.activeCollection = this.activeCollection === value ? null : value;
        },
        isCollectionOpen(value) {
            if (this.searchActive) {
                return true;
            }
            return this.activeCollection === value;
        }
     }'>



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
        <form id="journeySearchForm" method="GET" action="{{ route('journeys.index') }}" class="flex-grow-1 position-relative">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control form-control-lg rounded-pill border-0 bg-light ps-5 py-3 search-control"
                   name="search" value="{{ request('search') }}" placeholder="Search journeys, topics, keywords...">
            <input type="hidden" name="category" :value="activeCategory">
        </form>
        <button class="filter-btn shadow-sm d-flex align-items-center justify-content-center flex-shrink-0 gap-2"
                type="submit" form="journeySearchForm" aria-label="Search journeys">
            <i class="bi bi-search text-dark fs-4"></i>
            <span class="fw-semibold text-dark">Search</span>
        </button>
    </div>

    

    @if($journeys->count() > 0)
        <div x-show="hasMatches" x-cloak>
            <div class="journey-accordion">
                @foreach($journeyGroups as $group)
                    <section class="journey-collection">
                        <button type="button"
                            class="collection-header"
                            @click='toggleCollection(@json($group["state_key"]))'
                            :class='{ "is-open": isCollectionOpen(@json($group["state_key"])) }'
                            :aria-expanded='isCollectionOpen(@json($group["state_key"])) ? "true" : "false"'
                            aria-controls="{{ $group['panel_id'] }}">
                            <div class="collection-header-text">
                                <p class="collection-kicker text-uppercase">Collection</p>
                                <h3 class="collection-title mb-0">{{ $group['label'] }}</h3>
                                @if(!empty($group['description']))
                                    <p class="collection-description mb-0 text-muted">{{ \Illuminate\Support\Str::limit($group['description'], 140) }}</p>
                                @endif
                            </div>
                            <div class="collection-progress">
                                <span class="collection-progress-label">
                                    @if($group['total'] > 0)
                                        {{ $group['completed'] }} / {{ $group['total'] }} completed
                                    @else
                                        No journeys in this collection
                                    @endif
                                </span>
                                <div class="progress collection-progress-track">
                                    <div class="progress-bar"
                                         role="progressbar"
                                         aria-valuenow="{{ $group['percent'] }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"
                                         style="width: {{ $group['percent'] }}%"></div>
                                </div>
                            </div>
                                <span class="collection-toggle-icon"
                                    :class='{ "is-open": isCollectionOpen(@json($group["state_key"])) }'>
                                <i class="bi bi-chevron-down"></i>
                            </span>
                        </button>

                            <div class="collection-body"
                                id="{{ $group['panel_id'] }}"
                                x-show='isCollectionOpen(@json($group["state_key"]))'
                             x-transition
                             x-cloak>
                            <ul class="journey-list">
                                @foreach($group['journeys'] as $journey)
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
                                        $durationCopy = $journey->estimated_duration ? $journey->estimated_duration . ' min' : 'Flexible';
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
                                        $plainDescription = trim(strip_tags($journey->description ?? ''));
                                        $modalSummary = $plainDescription !== ''
                                            ? \Illuminate\Support\Str::limit($plainDescription, 220)
                                            : 'No summary available yet.';
                                        $canStartJourney = $journey->is_published && $stepsCount > 0 && !$activeAttempt;
                                        $detailPanelId = 'journey-details-' . $journey->id;
                                        $detailJourneyType = $journey->type ?? 'voice';
                                        $collectionName = optional($journey->collection)->name ?? 'Standalone Journey';
                                    @endphp
                                    <li class="journey-list-item"
                                        x-data="{ open: false }"
                                        :class="open ? 'is-open' : ''"
                                        x-show='activeCategory === "All" || activeCategory === @json($journeyCategory)'
                                        x-cloak>
                                        <div class="journey-inline-title">
                                            <h4 class="mb-0 fw-semibold">{!! $highlightText($journey->title) !!}</h4>
                                        </div>
                                        <div class="journey-inline-actions">
                                            <button type="button" class="btn btn-status {{ $statusClass }}" disabled>
                                                <span class="status-label">{{ $statusLabel }}</span>
                                            </button>
                                            <button type="button"
                                                class="btn btn-outline-secondary rounded-4 journey-details-toggle"
                                                @click="open = !open"
                                                :aria-expanded="open ? 'true' : 'false'"
                                                aria-controls="{{ $detailPanelId }}">
                                                <span x-show="!open">Details</span>
                                                <span x-show="open" x-cloak>Hide details</span>
                                                <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                            </button>
                                        </div>
                                        <div class="journey-detail-panel" id="{{ $detailPanelId }}" x-show="open" x-transition x-cloak>
                                            <p class="journey-detail-summary text-muted mb-4">{{ $modalSummary }}</p>
                                            <div class="journey-detail-meta row row-cols-1 row-cols-md-3 g-3">
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Category</span>
                                                    <p class="detail-value fw-semibold mb-0">{{ $journeyCategory }}</p>
                                                </div>
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Difficulty</span>
                                                    <p class="detail-value fw-semibold mb-0 {{ $difficultyClass }}">{{ $difficultyLabel }}</p>
                                                </div>
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Duration</span>
                                                    <p class="detail-value fw-semibold mb-0">{{ $durationCopy }}</p>
                                                </div>
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Steps</span>
                                                    <p class="detail-value fw-semibold mb-0">{{ $stepsCount }} steps</p>
                                                </div>
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Token cost</span>
                                                    <p class="detail-value fw-semibold mb-0">{{ $tokenCopy }}</p>
                                                </div>
                                                <div class="col">
                                                    <span class="detail-label text-uppercase small text-muted">Collection</span>
                                                    <p class="detail-value fw-semibold mb-0">{{ $collectionName }}</p>
                                                </div>
                                            </div>
                                            <div class="journey-detail-status p-3 rounded-4 bg-light mt-3">
                                                <span class="d-block fw-semibold">{{ $statusLabel }}</span>
                                                <span class="text-muted small">{{ $statusSubline }}</span>
                                            </div>
                                            <div class="journey-detail-actions d-flex flex-wrap gap-2 mt-3">
                                                @if($canStartJourney)
                                                    <button type="button"
                                                        class="btn btn-dark rounded-4"
                                                        data-start-journey
                                                        data-journey-id="{{ $journey->id }}"
                                                        data-journey-title="{{ e($journey->title) }}"
                                                        data-journey-type="{{ $detailJourneyType }}"
                                                        data-journey-cost="{{ (int) $journey->token_cost }}">
                                                        <i class="bi bi-play-circle"></i> Start Journey
                                                    </button>
                                                @else
                                                    <button type="button" class="btn btn-secondary rounded-4" disabled>
                                                        <i class="bi bi-lock"></i> Start unavailable
                                                    </button>
                                                @endif
                                                @if($isAdminUser)
                                                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-dark rounded-4">
                                                        <i class="bi bi-box-arrow-up-right"></i> Open Journey
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </section>
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
            <div>
                <label class="form-label fw-semibold">Collection</label>
                <select class="form-select" name="collection_id">
                    <option value="">All collections</option>
                    @foreach($collections as $collection)
                        <option value="{{ $collection->id }}" {{ (int) request('collection_id') === $collection->id ? 'selected' : '' }}>
                            {{ $collection->name }}@if($collection->institution) - {{ $collection->institution->name }}@endif
                        </option>
                    @endforeach
                </select>
                @if($collections->isEmpty())
                    <small class="text-muted d-block mt-2">No collections available for your institutions.</small>
                @endif
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const startButtons = document.querySelectorAll('[data-start-journey]');
    if (!startButtons.length) {
        return;
    }

    startButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!window.JourneyStartModal) {
                console.warn('JourneyStartModal module is not available.');
                return;
            }

            const journeyId = Number(button.getAttribute('data-journey-id'));
            if (!journeyId) {
                return;
            }

            const journeyTitle = button.getAttribute('data-journey-title') || 'Journey';
            const journeyType = button.getAttribute('data-journey-type') || 'voice';
            const journeyCost = Number(button.getAttribute('data-journey-cost')) || 0;

            window.JourneyStartModal.showStartJourneyModal(journeyId, journeyTitle, journeyType, journeyCost);
        });
    });
});
</script>
@endpush
