@extends('layouts.app')

@section('content')
<section class="shell">

    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('users.report') }}" class="btn btn-outline-secondary rounded-pill btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to My Report
        </a>
    </div>

    <div class="full-width-card mt-0">
        <div class="d-flex align-items-start gap-3 mb-4">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;">
                <i class="bi bi-file-earmark-text text-primary" style="font-size:1.25rem;"></i>
            </div>
            <div>
                <h3 class="fw-bold mb-1">{{ $attempt->journey->title ?? 'Journey Report' }}</h3>
                <div class="d-flex flex-wrap gap-3 text-muted small">
                    <span>
                        @if($attempt->status === 'completed')
                            <span class="badge bg-success">Completed</span>
                        @elseif($attempt->status === 'in_progress')
                            <span class="badge bg-warning text-dark">In progress</span>
                        @else
                            <span class="badge bg-secondary">{{ ucfirst($attempt->status) }}</span>
                        @endif
                    </span>
                    @if($attempt->completed_at)
                        <span><i class="bi bi-calendar-check me-1"></i>Completed {{ $attempt->completed_at->format('M j, Y \a\t g:i A') }}</span>
                    @endif
                    @if($attempt->score !== null)
                        <span><i class="bi bi-star me-1"></i>Score: {{ round($attempt->score, 1) }}%</span>
                    @endif
                </div>
            </div>
        </div>

        @if($attempt->report)
            <div class="report-content border rounded-3 p-4 bg-light">
                {!! nl2br(e($attempt->report)) !!}
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="bi bi-file-earmark-x" style="font-size:2.5rem;"></i>
                <p class="mt-2 mb-0">No report available for this journey attempt.</p>
            </div>
        @endif
    </div>

</section>
@endsection
