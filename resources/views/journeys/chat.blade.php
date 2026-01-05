@extends('layouts.app')

@section('content')
<div class="journey-player min-vh-100 d-flex flex-column">
    <div class="journey-shell container-xxl px-3 px-lg-5 py-4 d-flex flex-column flex-grow-1">
        <header class="journey-topbar glass-header rounded-4 px-3 px-lg-4 py-3" x-data="soundToggle()">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('journeys.index') }}" class="btn btn-light btn-back journey-action-btn">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h4 class="mb-1">{{ $attempt->journey->title }}</h4>
                        <div class="journey-meta d-flex align-items-center gap-2 flex-wrap">
                            <span>Learning Journey · {{ ucfirst($attempt->mode) }} mode</span>
                            @if($attempt->status === 'completed')
                                <span class="badge bg-success rounded-pill">Completed</span>
                            @endif
                            <span class="journey-chip-muted">{{ $responsesCount }} interactions</span>
                        </div>
                    </div>
                </div>
                <div class="journey-topbar-actions">
                    <button type="button" class="btn btn-sm sound-toggle-btn" :class="soundEnabled ? 'btn-primary text-white' : 'btn-outline-secondary'" @click="toggleSound()">
                        <i class="bi" :class="soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'"></i>
                        <span class="fw-semibold">Sound</span>
                    </button>
                </div>
            </div>
            <div class="mt-3">
                <div class="progress progress-thin">
                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: {{ $progress }}%"></div>
                </div>
            </div>
        </header>

        <section class="journey-body flex-grow-1 d-flex flex-column gap-4 mt-4">
            <div id="chatContainer" class="journey-chat journey-chat-scroll flex-grow-1">
                @if(isset($existingMessages) && count($existingMessages) > 0)
                    @foreach($existingMessages as $message)
                        <div class="message {{ $message['type'] }}-message" data-jsrid="{{ $message['jsrid'] }}">
                            {!! $message['content'] !!}
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="journey-input-zone">
                <div class="journey-input-wrap d-flex align-items-center gap-2">
                    <button class="btn btn-light journey-icon-btn" id="micButton" type="button" disabled>
                        <i id="recordingIcon" class="bi bi-mic-fill"></i>
                        <span id="recordingText" class="visually-hidden">Record Audio</span>
                    </button>
                    <input type="text" id="messageInput" class="form-control" placeholder="Type your response..." disabled>
                    <button class="btn btn-primary journey-icon-btn" id="sendButton" disabled>
                        <span id="sendButtonText" class="visually-hidden">Send</span>
                        <i class="bi bi-send-fill"></i>
                        <span class="spinner-border spinner-border-sm d-none" id="sendSpinner"></span>
                    </button>
                </div>
                @if($attempt->status === 'completed')
                    <small class="text-muted d-block mt-2">This journey has been completed.</small>
                @endif
            </div>
        </section>
    </div>
</div>

<!-- Data container for JavaScript module -->
<div id="journey-data-chat" 
     data-attempt-id="{{ $attempt->id }}"
     data-journey-id="{{ $attempt->journey_id }}"
     data-current-step="{{ $attempt->current_step }}"
     data-total-steps="{{ $attempt->journey->steps->count() }}"
     data-mode="{{ $mode ?? 'chat' }}"
     data-status="{{ $attempt->status }}"
     data-interactions-count="{{ $responsesCount }}"
     style="display: none;"></div>

@endsection