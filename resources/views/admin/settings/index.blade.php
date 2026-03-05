@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="section-title mb-1">Administration</p>
            <h2 class="fw-bold mb-1">Settings</h2>
            <p class="text-muted mb-0">Manage your platform from one place. Select a section below to get started.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/analytics" class="btn btn-outline-primary rounded-pill" target="_blank">
                <i class="bi bi-bar-chart-line"></i> Analytics
            </a>
            <a href="/pulse" class="btn btn-outline-primary rounded-pill" target="_blank">
                <i class="bi bi-activity"></i> Pulse
            </a>
        </div>
    </div>

    {{-- Settings grid --}}
    <div class="settings-grid">
        @foreach ($sections as $section)
            <div>
                <a href="{{ $section['route'] }}" class="text-decoration-none settings-card-link">
                    <div class="glass-card h-100 settings-card">
                        <div class="card-body d-flex flex-column align-items-start p-4">
                            <div class="settings-icon-wrapper mb-3">
                                <i class="bi {{ $section['icon'] }}"></i>
                            </div>
                            <h5 class="fw-semibold mb-2 text-dark">{{ $section['title'] }}</h5>
                            <p class="text-muted small mb-0 flex-grow-1">{{ $section['description'] }}</p>
                            <span class="settings-card-arrow mt-3">
                                <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

</div>
</div>
</section>
@endsection

@push('styles')
<style>
    /* ── Settings cards ────────────────────────────────── */
    .settings-card-link:hover .settings-card {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, .08);
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 260px));
        gap: 1.25rem;
    }

    .settings-card {
        transition: transform .2s ease, box-shadow .2s ease;
        cursor: pointer;
    }

    .settings-icon-wrapper {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(99, 102, 241, .12), rgba(99, 102, 241, .04));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        color: #6366f1;
    }

    .settings-card-arrow {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: rgba(99, 102, 241, .08);
        color: #6366f1;
        font-size: .85rem;
        transition: background .2s ease, transform .2s ease;
    }

    .settings-card-link:hover .settings-card-arrow {
        background: #6366f1;
        color: #fff;
        transform: translateX(3px);
    }
</style>
@endpush
