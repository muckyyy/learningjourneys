@extends('layouts.app')

@php
    $hasFeedback = $attempt->rating !== null;
    $needsFeedback = $attempt->status === 'completed' && !$hasFeedback;
@endphp

@section('content')
<div class="journey-player min-vh-100 d-flex flex-column voice-page g-0">
    <!-- Overlay with Start/Continue Button -->
    <div id="voiceOverlay" class="voice-overlay position-fixed top-0 start-0 w-100 h-100">
        <button id="startContinueButton" class="btn btn-primary btn-lg px-4 py-2 @if(isset($existingMessages) && count($existingMessages) > 0) voice-continue @else voice-start @endif" style="min-width: 150px;">
            @if(isset($existingMessages) && count($existingMessages) > 0)
                <i class="bi bi-play-fill me-2 voice-continue"></i>Continue
            @else
                <i class="bi bi-play-circle me-2 voice-start"></i>Start
            @endif
        </button>
    </div>

@push('styles')
<style>
    .mobile-bottom-nav {
        display: none !important;
    }

    .voice-page .journey-shell {
        width: min(1200px, 100%);
        max-width: 100%;
        margin: 0 auto;
        padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) clamp(1rem, 2vw, 1.75rem);
        box-sizing: border-box;
    }
</style>
@endpush

    <div id="voiceModeCard" class="journey-shell d-flex flex-column flex-grow-1 voice-panel mx-auto">
        <header class="journey-topbar glass-header rounded-4 px-3 px-lg-4 py-3" x-data="soundToggle()">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('journeys.index') }}" class="btn btn-light btn-back journey-action-btn">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="mb-1">{{ $attempt->journey->title }}</h4>
                        <div class="journey-meta d-flex align-items-center gap-2 flex-wrap">
                            @if($attempt->status === 'completed')
                                <span class="badge bg-success rounded-pill">Completed</span>
                            @endif
                           
                        </div>
                    </div>
                </div>
                <div class="journey-topbar-actions">
                    <button type="button" class="btn btn-sm sound-toggle-btn btn-outline-secondary" id="voiceSoundToggle">
                        <i class="bi bi-volume-up-fill" id="volumeUpIcon" aria-hidden="true"></i>
                        <i class="bi bi-volume-off-fill d-none" id="volumeOffIcon" aria-hidden="true"></i>
                        <span class="fw-semibold ms-1">Sound</span>
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <div class="progress progress-thin">
                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </header>

        <section class="journey-body voice-body flex-grow-1 d-flex flex-column gap-3 mt-4">
            <div id="voiceContainer" class="d-flex flex-column flex-grow-1 gap-3">
                <div id="chatContainer" class="journey-chat journey-chat-scroll flex-grow-1">
                    <!-- Pre-load existing messages grouped by step with a header shown once per step -->
                    @if(isset($existingMessages) && count($existingMessages) > 0)
                        @php $lastStepId = null; @endphp
                        @foreach($attempt->stepResponses as $resp)
                            @if($lastStepId !== $resp->journey_step_id)
                                @php
                                    $step = $attempt->journey->steps->firstWhere('id', $resp->journey_step_id);
                                    $lastStepId = $resp->journey_step_id;
                                @endphp
                                @if($step)
                                    <div class="step-info">
                                        <h4 class="mb-2">{{ $step->title }}</h4>
                                    </div>
                                @endif
                            @endif
                            @foreach($existingMessages as $message)
                                @if(isset($message['jsrid']) && $message['jsrid'] == $resp->id)
                                    <div class="message {{ $message['type'] }}-message" data-jsrid="{{ $message['jsrid'] }}">
                                        {!! $message['content'] !!}
                                        @if($message['type'] === 'ai')
                                            <audio controls class="mt-2 voice-recording" controlsList="nodownload noplaybackrate">
                                                <source src="{{ route('journeys.aivoice', ['jsrid' => $message['jsrid']]) }}" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    @endif
                    @if($attempt->status === 'completed' && $hasFeedback)
                        <div class="message system-message report-message mt-2">
                            {!! $attempt->report !!}
                        </div>
                        <div class="message system-message mt-2">
                            <strong class="d-block mb-1">Your feedback</strong>
                            <span class="badge bg-primary mb-2">Rating: {{ $attempt->rating }}/5</span>
                            <p class="mb-0">{{ $attempt->feedback }}</p>
                        </div>
                    @elseif($needsFeedback)
                        <div class="message system-message text-muted small mt-2">
                            Please rate this journey to unlock the final report.
                        </div>
                    @endif
                </div>

                @if($attempt->status === 'in_progress')
                    <div class="journey-input-zone">
                        <div class="chat-input-wrapper">
                            <div class="chat-input-inner">
                                <div class="journey-input-wrap chat-input d-flex align-items-center gap-2" id="inputGroup" @if($attempt->status === 'completed' || $attempt->status === 'abandoned') style="display:none" @endif>
                                    <textarea id="messageInput" class="form-control chat-textarea border-0 bg-transparent flex-grow-1" rows="1"
                                              placeholder="Type your response..."
                                              {{ $attempt->status === 'completed' ? 'disabled' : '' }}></textarea>
                                    <button class="btn btn-light journey-icon-btn chat-btn chat-btn-mic" id="micButton" type="button" aria-label="Record audio"
                                            {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                        <i id="recordingIcon" class="bi bi-mic-fill"></i>
                                        <span id="recordingText" class="visually-hidden">Record</span>
                                    </button>
                                    <button class="btn btn-primary journey-icon-btn chat-btn chat-btn-send" id="sendButton" aria-label="Send message" {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                                        <span id="sendButtonText" class="visually-hidden">Send</span>
                                        <span class="spinner-border spinner-border-sm d-none" id="sendSpinner" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <div id="feedbackFormWrapper" class="mb-4 @if(!$needsFeedback) d-none @endif">
                    <div class="rounded-4 shadow-sm p-4 bg-white">
                        <h5 class="mb-2">Share your feedback</h5>
                        <p class="text-muted small mb-3">Rate this journey and tell us how it went to unlock your final report.</p>
                        <form id="journeyFeedbackForm" novalidate>
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Rate this journey</label>
                                <div class="btn-group w-100" role="group" aria-label="Journey rating">
                                    @for($i = 1; $i <= 5; $i++)
                                        <input type="radio" class="btn-check" name="journey_rating" id="journeyRating{{ $i }}" value="{{ $i }}">
                                        <label class="btn btn-outline-secondary" for="journeyRating{{ $i }}">{{ $i }}</label>
                                    @endfor
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="journeyFeedbackText" class="form-label">Feedback</label>
                                <textarea class="form-control" id="journeyFeedbackText" rows="3" placeholder="Tell us about your experience" maxlength="2000"></textarea>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <button type="submit" class="btn btn-success" id="feedbackSubmitButton">
                                    <span class="feedback-submit-label">Submit feedback</span>
                                    <span class="spinner-border spinner-border-sm d-none" id="feedbackSubmitSpinner" role="status" aria-hidden="true"></span>
                                </button>
                                <span class="text-danger small d-none" id="feedbackError"></span>
                                <span class="text-success small d-none" id="feedbackSuccess"></span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Data container for JavaScript module -->
<div id="journey-data-voice" 
    data-attempt-id="{{ $attempt->id }}"
    data-journey-id="{{ $attempt->journey_id }}"
    data-current-step="{{ $attempt->current_step }}"
    data-total-steps="{{ $attempt->journey->steps->count() }}"
    data-mode="{{ $mode ?? 'chat' }}"
    data-status="{{ $attempt->status }}"
    data-recordtime="{{ $journey->recordtime }}"
    data-has-feedback="{{ $hasFeedback ? '1' : '0' }}"
    data-needs-feedback="{{ $needsFeedback ? '1' : '0' }}"
    data-feedback-url="{{ route('journeys.voice.feedback') }}"
    @if(!empty($lastResponseText)) data-last-ai-response="{{ base64_encode($lastResponseText) }}" @endif
    @if(!empty($lastResponseAudio)) data-last-ai-audio-id="{{ $lastResponseAudio }}" @endif
    style="display: none;"></div>



@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ta = document.getElementById('messageInput');
    if (!ta) return;
    const lineHeight =  ta.scrollHeight / (ta.rows || 2) || 20; // fallback
    const maxRows = 5;
    const maxHeight = lineHeight * maxRows + 24; // + padding approx

    const autoSize = () => {
        ta.style.height = 'auto';
        const newHeight = Math.min(ta.scrollHeight, maxHeight);
        ta.style.height = newHeight + 'px';
        ta.style.overflowY = ta.scrollHeight > newHeight ? 'auto' : 'hidden';
    };

    // Initialize and bind
    autoSize();
    ta.addEventListener('input', autoSize);
});
</script>
@endpush