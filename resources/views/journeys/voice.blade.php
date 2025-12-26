@extends('layouts.app')

@php
    $hasFeedback = $attempt->rating !== null;
    $needsFeedback = $attempt->status === 'completed' && !$hasFeedback;
@endphp

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" id="voiceModeCard">
                <!-- Overlay with Start/Continue Button -->
                <div id="voiceOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center @if(isset($existingMessages) && count($existingMessages) > 0) hidden @endif">
                    <button id="startContinueButton" class="btn btn-primary btn-lg px-4 py-2 @if(isset($existingMessages) && count($existingMessages) > 0) voice-continue @else voice-start @endif" style="min-width: 150px; ">
                        @if(isset($existingMessages) && count($existingMessages) > 0)
                            <i class="bi bi-play-fill me-2 voice-continue"></i>Continue
                        @else
                            <i class="bi bi-play-circle me-2 voice-start"></i>Start
                        @endif
                    </button>
                </div>
                <div class="card-header sticky-top bg-white" style="z-index: 1020;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="mb-0">{{ $attempt->journey->title }}</h4>
                                @if($attempt->status == 'in_progress')
                                    <div class="text-muted d-flex align-items-center">
                                        <i class="bi bi-volume-up fs-2 ms-1 icon-circle-btn" id="volumeUpIcon" aria-hidden="true"></i>
                                        <i class="bi bi-volume-off fs-2 ms-2 icon-circle-btn d-none" id="volumeOffIcon" aria-hidden="true"></i>
                                    </div>
                                @endif
                            </div>

                            @if($attempt->status === 'completed')
                                <small class="text-muted">
                                    <span class="badge bg-success ms-2">Completed</span>
                                </small>
                            @endif
                        </div>
                        <div>
                            <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Journeys
                            </a>
                        </div>
                    </div>
                    <!-- Sticky progress bar inside header -->
                    <div class="mt-2">
                        <div class="progress" style="height: 6px;">
                            <div id="progress-bar" class="progress-bar" role="progressbar" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Hidden data for JavaScript -->
                    
                    

                    <!-- Voice Mode Container -->
                    <div id="voiceContainer" class="position-relative d-flex flex-column mb-3 chat-shell">
                        <div id="chatContainer" class="p-3 mb-3 chat-container">
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
                                                <h4>{{ $step->title }}</h4>
                                            </div>
                                        @endif
                                    @endif
                                    @foreach($existingMessages as $message)
                                        @if(isset($message['jsrid']) && $message['jsrid'] == $resp->id)
                                            <div class="message {{ $message['type'] }}-message" data-jsrid="{{ $message['jsrid'] }}">
                                                {!! $message['content'] !!}
                                                @if($message['type'] === 'ai')
                                                    <audio controls class="mt-2 voice-recording">
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
                        <!-- New Message Input area outside the card -->
                        @if($attempt->status === 'in_progress')
                            <div class="mt-3 chat-input-wrapper">
                            <div class="chat-input-inner">
                                <div class="input-group chat-input" id="inputGroup" @if($attempt->status === 'completed' || $attempt->status === 'abandoned') style="display:none" @endif>
                                    <textarea id="messageInput" class="form-control chat-textarea" rows="1"
                                            placeholder="Type your response..."
                                            {{ $attempt->status === 'completed' ? 'disabled' : '' }}></textarea>
                                    <button class="btn btn-secondary chat-btn chat-btn-mic" id="micButton" type="button" aria-label="Record audio"
                                            {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                        <i id="recordingIcon" class="bi bi-mic-fill"></i>
                                        <span id="recordingText" class="visually-hidden">Record</span>
                                    </button>
                                    <button class="btn btn-primary chat-btn chat-btn-send" id="sendButton" aria-label="Send message" {{ $attempt->status === 'completed' ? 'disabled' : '' }}>
                                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                                        <span id="sendButtonText" class="visually-hidden">Send</span>
                                        <span class="spinner-border spinner-border-sm d-none" id="sendSpinner" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div id="feedbackFormWrapper" class="mt-3 @if(!$needsFeedback) d-none @endif">
                            <div class="border rounded p-3 bg-light">
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
                                    <div class="d-flex align-items-center gap-3">
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
                </div>
            </div>

            
        </div>
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