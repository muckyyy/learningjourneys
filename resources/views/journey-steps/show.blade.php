@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">Step {{ $step->order }}: {{ $step->title }}</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('journeys.index') }}">Journeys</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.show', $journey) }}">{{ $journey->title }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.steps.index', $journey) }}">Steps</a></li>
                            <li class="breadcrumb-item active">Step {{ $step->order }}</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Steps
                    </a>
                    @can('update', $journey)
                        <a href="{{ route('journeys.steps.edit', [$journey, $step]) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    @endcan
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex gap-2 mb-3">
                                <span class="badge bg-{{ 
                                    $step->type === 'text' ? 'secondary' : 
                                    ($step->type === 'video' ? 'danger' : 
                                    ($step->type === 'quiz' ? 'warning' : 
                                    ($step->type === 'interactive' ? 'info' : 'success'))) 
                                }}">
                                    {{ ucfirst($step->type) }}
                                </span>
                                @if($step->is_required)
                                    <span class="badge bg-dark">Required</span>
                                @endif
                                @if($step->time_limit)
                                    <span class="badge bg-warning">{{ $step->time_limit }} min limit</span>
                                @endif
                            </div>

                            <div class="content-preview">
                                {!! $step->content !!}
                            </div>

                            @if($step->type === 'video' && $step->configuration)
                                @php
                                    $config = json_decode($step->configuration, true);
                                @endphp
                                @if(isset($config['video_url']))
                                    <div class="mt-4">
                                        <h6>Video Configuration:</h6>
                                        <p><strong>URL:</strong> <a href="{{ $config['video_url'] }}" target="_blank">{{ $config['video_url'] }}</a></p>
                                        @if(isset($config['autoplay']) && $config['autoplay'])
                                            <p><strong>Auto-play:</strong> Enabled</p>
                                        @endif
                                    </div>
                                @endif
                            @endif

                            @if($step->type === 'quiz' && $step->configuration)
                                @php
                                    $config = json_decode($step->configuration, true);
                                @endphp
                                <div class="mt-4">
                                    <h6>Quiz Configuration:</h6>
                                    @if(isset($config['passing_score']))
                                        <p><strong>Passing Score:</strong> {{ $config['passing_score'] }}%</p>
                                    @endif
                                    @if(isset($config['randomize_questions']) && $config['randomize_questions'])
                                        <p><strong>Question Order:</strong> Randomized</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Step Information</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row">
                                <dt class="col-sm-4">Order:</dt>
                                <dd class="col-sm-8">{{ $step->order }}</dd>
                                
                                <dt class="col-sm-4">Type:</dt>
                                <dd class="col-sm-8">{{ ucfirst($step->type) }}</dd>
                                
                                <dt class="col-sm-4">Required:</dt>
                                <dd class="col-sm-8">{{ $step->is_required ? 'Yes' : 'No' }}</dd>
                                
                                @if($step->time_limit)
                                    <dt class="col-sm-4">Time Limit:</dt>
                                    <dd class="col-sm-8">{{ $step->time_limit }} minutes</dd>
                                @endif
                                
                                <dt class="col-sm-4">Created:</dt>
                                <dd class="col-sm-8">{{ $step->created_at->format('M d, Y') }}</dd>
                                
                                <dt class="col-sm-4">Updated:</dt>
                                <dd class="col-sm-8">{{ $step->updated_at->diffForHumans() }}</dd>
                            </dl>
                        </div>
                    </div>

                    <!-- Step Navigation -->
                    <div class="card shadow-sm mt-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Step Navigation</h5>
                        </div>
                        <div class="card-body">
                            @php
                                $prevStep = $journey->steps()->where('order', '<', $step->order)->orderBy('order', 'desc')->first();
                                $nextStep = $journey->steps()->where('order', '>', $step->order)->orderBy('order')->first();
                            @endphp

                            <div class="d-grid gap-2">
                                @if($prevStep)
                                    <a href="{{ route('journeys.steps.show', [$journey, $prevStep]) }}" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-left"></i> Previous: {{ $prevStep->title }}
                                    </a>
                                @endif

                                @if($nextStep)
                                    <a href="{{ route('journeys.steps.show', [$journey, $nextStep]) }}" class="btn btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i> Next: {{ $nextStep->title }}
                                    </a>
                                @endif

                                @if(!$prevStep && !$nextStep)
                                    <p class="text-muted text-center mb-0">This is the only step in the journey.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.content-preview {
    border: 1px solid #e0e0e0;
    border-radius: 0.375rem;
    padding: 1rem;
    background-color: #f8f9fa;
    min-height: 200px;
}

.content-preview h1, .content-preview h2, .content-preview h3, 
.content-preview h4, .content-preview h5, .content-preview h6 {
    margin-top: 0;
}

.content-preview pre {
    background-color: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.75rem;
    overflow-x: auto;
}
</style>
@endsection
