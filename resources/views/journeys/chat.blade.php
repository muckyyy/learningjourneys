@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4>{{ $attempt->journey->title }}</h4>
                            <small class="text-muted">
                                Learning Journey - {{ ucfirst($attempt->mode) }} Mode
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

                    <!-- Chat Container -->
                    <div id="chatContainer" class="border p-3 mb-3" style="height: calc(100vh - 250px); min-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                        <!-- Pre-load existing messages -->
                        @if(isset($existingMessages) && count($existingMessages) > 0)
                            @foreach($existingMessages as $message)
                                <div class="message {{ $message['type'] }}-message" data-jsrid="{{ $message['jsrid'] }}">
                                    {!! $message['content'] !!}
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <!-- Message Input -->
                    <div class="row">
                        <div class="col-12">
                            <!-- WebSocket and Audio Status -->
                            
                            <div class="input-group">
                                <input type="text" id="messageInput" class="form-control" 
                                       placeholder="Type your response..." 
                                       disabled>
                                <button class="btn btn-secondary" id="micButton" type="button" 
                                        disabled>
                                    <i id="recordingIcon" class="fas fa-microphone"></i>
                                    <span id="recordingText" class="ms-1">Record Audio</span>
                                </button>
                                <button class="btn btn-primary" id="sendButton" disabled>
                                    <span id="sendButtonText">Send</span>
                                    <span class="spinner-border spinner-border-sm d-none" id="sendSpinner"></span>
                                </button>
                            </div>
                            
                            @if($attempt->status === 'completed')
                                <small class="text-muted">This journey has been completed.</small>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
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