
@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row justify-content-center">
		<div class="col-md-12">
			<div class="card">
				<div class="card-header">
					<h4>Learning Journeys Chat API Test</h4>
					<small class="text-muted" id="pageSubtitle">
						@if($existingAttempt)
							Continuing preview session for "{{ $journey->title }}" ({{ $existingAttempt->created_at->format('M d, Y g:i A') }})
							@if($existingAttempt->status === 'completed')
								<span class="badge bg-success ms-2">Completed</span>
							@endif
						@else
							Test the start_chat and chat_submit API endpoints
						@endif
					</small>
				</div>
                
				<div class="card-body">
				<div id="preview-data" class="d-none"
					data-attempt-id="{{ $existingAttempt->id ?? '' }}"
					data-step-id="{{ $currentStepId ?? '' }}"
					data-step-order="{{ $currentStep->order ?? '' }}"
					data-step-title="{{ $currentStep->title ?? '' }}"
					data-total-steps="{{ $journey->steps()->count() ?? '' }}"
					data-attempt-count="{{ $attemptCount ?? '' }}"
					data-total-attempts="{{ $currentStep->maxattempts ?? 3 }}"
					data-attempt-status="{{ $existingAttempt->status ?? '' }}"
					data-is-started="{{ $existingAttempt ? '1' : '0' }}"
					data-is-completed="{{ $existingAttempt && $existingAttempt->status === 'completed' ? '1' : '0' }}"
					@if(isset($presetMessages) && count($presetMessages) > 0)
					data-preset-messages="{{ json_encode($presetMessages, JSON_HEX_QUOT | JSON_HEX_APOS) }}"
					@endif>
				</div>
					<div class="row mb-3">
						<div class="col-md-6">
							<label for="journeyId" class="form-label">Journey</label>
							<select class="form-select" id="journeyId" name="journey_id" style="width: 100%" {{ $existingAttempt ? 'disabled' : '' }}>
								<option value="">Select a journey...</option>
								@foreach(($availableJourneys ?? []) as $j)
									<option value="{{ $j->id }}" {{ ($journey && $journey->id === $j->id) ? 'selected' : '' }}>{{ $j->title }}</option>
								@endforeach
								@if($journey && !($availableJourneys ?? collect())->contains('id', $journey->id))
									<option value="{{ $journey->id }}" selected>{{ $journey->title }}</option>
								@endif
							</select>
							@if($existingAttempt && $journey)
								<input type="hidden" name="journey_id" value="{{ $journey->id }}">
							@endif
						</div>
					</div>

					@php
						// Exclude unnecessary fields from preview-chat
						$excludedVars = [
							'journey_description', 'student_email', 'institution_name', 'journey_title',
							'current_step', 'previous_step', 'previous_steps', 'next_step'
						];
						$locked = (bool) $existingAttempt;
						// Prepare combined list: profile fields first, then master variables as pseudo-fields
						$allInputs = [];
						foreach(($profileFields ?? []) as $field){
							$allInputs[] = [
								'type' => $field->input_type ?: 'text',
								'label' => $field->name,
								'short' => $field->short_name,
								'required' => $field->required,
								'options' => is_array($field->options) ? $field->options : null,
								'value' => $attemptVariables[$field->short_name] ?? ($userProfileDefaults[$field->short_name] ?? ''),
								'source' => 'profile'
							];
						}
						if($journey && !empty($masterVariables)){
							foreach($masterVariables as $var){
								if(in_array($var, $excludedVars)) continue;
								$allInputs[] = [
									'type' => 'text',
									'label' => str_replace('_',' ', $var),
									'short' => $var,
									'required' => false,
									'options' => null,
									'value' => $attemptVariables[$var] ?? ($userProfileDefaults[$var] ?? ''),
									'source' => 'master'
								];
							}
						}
						// Split into two roughly equal columns
						$half = (int) ceil(count($allInputs) / 2);
						$leftInputs = array_slice($allInputs, 0, $half);
						$rightInputs = array_slice($allInputs, $half);
					@endphp

					<div class="row" id="profileFieldsContainer">
						<div class="col-md-6">
							@foreach($leftInputs as $inp)
								@if(in_array($inp['short'], $excludedVars))
									@continue
								@endif
								<div class="mb-2">
									<label class="form-label">{{ $inp['label'] }}</label>
									@if($inp['type'] === 'select' && is_array($inp['options']))
										<select class="form-select variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
											@foreach($inp['options'] as $opt)
												<option value="{{ $opt }}" {{ $inp['value'] == $opt ? 'selected' : '' }}>{{ $opt }}</option>
											@endforeach
										</select>
									@else
										<input type="{{ $inp['type'] }}" class="form-control variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" value="{{ $inp['value'] }}" placeholder="Enter {{ strtolower($inp['label']) }}" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
									@endif
								</div>
							@endforeach
						</div>
						<div class="col-md-6">
							@foreach($rightInputs as $inp)
								@if(in_array($inp['short'], $excludedVars))
									@continue
								@endif
								<div class="mb-2">
									<label class="form-label">{{ $inp['label'] }}</label>
									@if($inp['type'] === 'select' && is_array($inp['options']))
										<select class="form-select variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
											@foreach($inp['options'] as $opt)
												<option value="{{ $opt }}" {{ $inp['value'] == $opt ? 'selected' : '' }}>{{ $opt }}</option>
											@endforeach
										</select>
									@else
										<input type="{{ $inp['type'] }}" class="form-control variable-input" id="{{ $inp['source'] === 'profile' ? 'profile_' : 'var_' }}{{ $inp['short'] }}" name="{{ $inp['source'] === 'profile' ? 'profile' : 'vars' }}[{{ $inp['short'] }}]" value="{{ $inp['value'] }}" placeholder="Enter {{ strtolower($inp['label']) }}" {{ $inp['required'] ? 'required' : '' }} {{ $locked ? 'disabled' : '' }}>
									@endif
								</div>
							@endforeach
							@if(!$journey)
								<div class="text-muted small">Select a journey to see journey-specific variables.</div>
							@endif
						</div>
					</div>
					<div class="row mb-3">
						<div class="col-md-12 d-flex justify-content-end">
							<button class="btn btn-primary me-2" onclick="PreviewChat.startChat()" {{ $existingAttempt ? 'disabled' : '' }}>Start Chat</button>
							<button class="btn btn-secondary" onclick="PreviewChat.clearChat()">Clear</button>
						</div>
					</div>
                    
					<div id="chatContainer" class="border p-3 mb-3" style="height: 600px; overflow-y: auto; background-color: #f8f9fa;">
						@if(!empty($existingMessages))
							@foreach($existingMessages as $m)
								@if($m['type'] === 'step_info')
									{{-- Step information --}}
									<div class="step-info">
										<span class="badge bg-primary">Step {{ $m['step_order'] }}/{{ $m['total_steps'] }}</span>
										<span class="badge bg-info">Attempt {{ $m['step_attempt_count'] }}/{{ $m['step_max_attempts'] }}</span>
										@if($m['rating'])
											<span class="badge bg-warning">
												@for($i = 1; $i <= 5; $i++)
													@if($i <= $m['rating'])★@else☆@endif
												@endfor
												{{ $m['rating'] }}/5
											</span>
										@endif
										@if($m['step_title'])
											<strong>{{ $m['step_title'] }}</strong>
										@endif
									</div>
								@elseif($m['type'] === 'feedback_info')
									{{-- Feedback information --}}
									<div class="feedback-info action-{{ $m['action'] }}">
										@if($m['rating'])
											<strong>Rating:</strong> 
											@for($i = 1; $i <= 5; $i++)
												@if($i <= $m['rating'])★@else☆@endif
											@endfor
											({{ $m['rating'] }}/5)<br>
										@endif
										<strong>Attempt:</strong> {{ $m['step_attempt_count'] }}/{{ $m['step_max_attempts'] }}<br>
										<strong>Action:</strong> 
										@if($m['action'] === 'finish_journey')
											🎉 Journey Completed!
										@elseif($m['action'] === 'next_step')
											➡️ Moving to Next Step
										@elseif($m['action'] === 'retry_step')
											🔄 Retrying Current Step
										@else
											{{ $m['action'] }}
										@endif
									</div>
								@else
									{{-- Regular user/AI messages --}}
									<div class="message {{ $m['type'] === 'user' ? 'user-message' : 'ai-message' }}">
										@if(($m['type'] ?? '') === 'ai')
											{!! $m['content'] !!}
										@else
											{!! nl2br(e($m['content'])) !!}
										@endif
									</div>
								@endif
							@endforeach
							<div class="message system-message">💬 Continuing existing chat session...</div>
						@else
							<p class="text-muted">Click "Start Chat" to begin...</p>
						@endif
					</div>
                    
					<!-- WebSocket and Audio Status -->
					<div class="status-indicators mb-2">
						<small class="text-muted">
							<span id="websocket-status">🔌 WebSocket: <span class="status-text">Connecting...</span></span>
							<span class="mx-2">|</span>
							<span id="audio-status">🎤 Audio: <span class="status-text">Ready</span></span>
						</small>
					</div>
                    
					<div class="input-group">
						<input type="text" class="form-control" id="userInput" placeholder="{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'This session is completed - no more messages allowed' : 'Type your message...' }}" 
							   onkeypress="PreviewChat.handleKeyPress(event)" disabled {{ $existingAttempt && $existingAttempt->status === 'completed' ? 'readonly' : '' }}>
						<button class="btn btn-outline-secondary" id="micButton" type="button" title="Voice Input" 
								{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'style=display:none' : '' }}>
							🎤
						</button>
						<button class="btn btn-success" id="sendButton" onclick="PreviewChat.sendMessage()" disabled 
								{{ $existingAttempt && $existingAttempt->status === 'completed' ? 'style=display:none' : '' }}>Send</button>
						@if($existingAttempt && $existingAttempt->status === 'completed')
							<button class="btn btn-secondary" disabled>Session Completed</button>
						@endif
					</div>
                    

@endsection
