@extends('layouts.app')

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
                        </div>
                        <!-- New Message Input area outside the card -->
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

                                @if($attempt->status === 'completed')
                                    <div class="alert alert-success mt-3" role="alert">
                                        <i class="bi bi-check-circle-fill me-2"></i>You have completed this journey. Great job!
                                    </div>
                                @endif
                                @if($attempt->status === 'abandoned')
                                    <div class="alert alert-warning mt-3" role="alert">
                                        <i class="bi bi-exclamation-circle-fill me-2"></i>This journey has been abandoned.
                                    </div>
                                @endif
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