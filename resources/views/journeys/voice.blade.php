@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm" id="voiceModeCard">
                <!-- Overlay with Start/Continue Button -->
                <div id="voiceOverlay" class="position-absolute top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center @if(isset($existingMessages) && count($existingMessages) > 0) hidden @endif" style="background-color: rgba(248, 249, 250, 0.9); z-index: 10;">
                    <button id="startContinueButton" class="btn btn-primary btn-lg px-4 py-2 @if(isset($existingMessages) && count($existingMessages) > 0) voice-continue @else voice-start @endif" style="min-width: 150px; ">
                        @if(isset($existingMessages) && count($existingMessages) > 0)
                            <i class="bi bi-play-fill me-2 voice-continue"></i>Continue
                        @else
                            <i class="bi bi-play-circle me-2 voice-start"></i>Start
                        @endif
                    </button>
                </div>
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4>{{ $attempt->journey->title }}</h4>
                            <small class="text-muted">
                                
                                @if($attempt->status === 'completed')
                                    <span class="badge bg-success ms-2">Completed</span>
                                @endif
                            </small>
                        </div>
                        <div>
                            <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Journeys
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Hidden data for JavaScript -->
                    
                    <!-- Journey Progress -->
                    <div class="row mb-3">
                        <div class="col-12">
                            
                            <div class="progress">
                                <div id="progress-bar" class="progress-bar" role="progressbar" id="progressBar"
                                     style="width: {{ $progress }}%">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Voice Mode Container -->
                    <div id="voiceContainer" class="position-relative d-flex flex-column mb-3 chat-shell" style="height: calc(100vh - 250px); min-height: 400px;">
                        <div id="chatContainer" class="p-3 mb-3 chat-container" style="height: calc(100vh - 250px); min-height: 400px; overflow-y: auto;">
                            <!-- Pre-load existing messages -->
                            @if(isset($existingMessages) && count($existingMessages) > 0)
                                @foreach($existingMessages as $message)
                                    <div class="message {{ $message['type'] }}-message" data-jsrid="{{ $message['jsrid'] }}">
                                        {!! $message['content'] !!}
                                        @if($message['type'] === 'ai')
                                            <audio controls class="mt-2 voice-recording" >
                                                <source src="{{ route('journeys.aivoice', ['jsrid' => $message['jsrid']]) }}" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>
                                        @endif

                                    </div>
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