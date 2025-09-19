@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-map"></i> Learning Journeys
                </h1>
                @can('create', App\Models\Journey::class)
                    <a href="{{ route('journeys.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create Journey
                    </a>
                @endcan
            </div>

            @if(Auth::user()->role === 'regular' && isset($activeAttempt) && $activeAttempt)
                <div class="alert alert-warning mb-4" role="alert">
                    <h5 class="alert-heading">
                        <i class="bi bi-exclamation-triangle"></i> Active Journey in Progress
                    </h5>
                    <p class="mb-2">
                        You currently have an active journey: <strong>{{ $activeAttempt->journey->title }}</strong>
                    </p>
                    <p class="mb-3">
                        You must complete or abandon your current journey before starting a new one.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-arrow-right-circle"></i> Continue Active Journey
                        </a>
                        <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                    onclick="return confirm('Are you sure you want to abandon your current journey? Your progress will be lost.')">
                                <i class="bi bi-x-circle"></i> Abandon Current Journey
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if($journeys->count() > 0)
                <div class="row">
                    @foreach($journeys as $journey)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title">{{ $journey->title }}</h5>
                                        <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($journey->difficulty_level) }}
                                        </span>
                                    </div>
                                    
                                    <p class="card-text text-muted small">
                                        {{ Str::limit($journey->description, 100) }}
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                        <span>
                                            <i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min
                                        </span>
                                        <span>
                                            <i class="bi bi-collection"></i> {{ $journey->collection->name }}
                                        </span>
                                    </div>

                                    @if($journey->tags)
                                        <div class="mb-3">
                                            @foreach(explode(',', $journey->tags) as $tag)
                                                <span class="badge bg-light text-dark me-1">{{ trim($tag) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                             @can('update', $journey)
                                                <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary btn-sm">
                                                    View
                                                </a>
                                           
                                                <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-secondary btn-sm">
                                                    Edit
                                                </a>
                                            @else
                                                @if($journey->is_published && $journey->steps->count() > 0 && (!$activeAttempt || $activeAttempt->journey_id !== $journey->id))
                                                    <button type="button" class="btn btn-primary btn-sm me-2" 
                                                            onclick="showStartJourneyModal({{ $journey->id }}, '{{ addslashes($journey->title) }}', 'chat')">
                                                        <i class="bi bi-chat-dots"></i> Start Chat
                                                    </button>
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="showStartJourneyModal({{ $journey->id }}, '{{ addslashes($journey->title) }}', 'voice')">
                                                        <i class="bi bi-mic"></i> Start Voice
                                                    </button>
                                                @elseif($activeAttempt && $activeAttempt->journey_id === $journey->id)
                                                    <a href="{{ route('journeys.continue', $activeAttempt) }}" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-arrow-right-circle"></i> Continue
                                                    </a>
                                                @else
                                                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary btn-sm">
                                                        View Details
                                                    </a>
                                                @endif
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                
                                @if(!$journey->is_published)
                                    <div class="card-footer bg-warning bg-opacity-10">
                                        <small class="text-warning">
                                            <i class="bi bi-eye-slash"></i> Draft
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $journeys->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-map display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No journeys found</h3>
                    <p class="text-muted">Start creating learning journeys to see them here.</p>
                    @can('create', App\Models\Journey::class)
                        <a href="{{ route('journeys.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Create Your First Journey
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Start Journey Confirmation Modal -->
<div class="modal fade" id="startJourneyModal" tabindex="-1" aria-labelledby="startJourneyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startJourneyModalLabel">Start Learning Journey</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to start <strong id="journeyTypeText">chat</strong> journey for:</p>
                <h6 id="journeyTitleText">Journey Title</h6>
                <p class="text-muted">This will create a new learning session and you can track your progress.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStartJourney">
                    <span class="spinner-border spinner-border-sm d-none" id="startJourneySpinner" role="status" aria-hidden="true"></span>
                    <span id="startJourneyText">Yes, Start Journey</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let selectedJourneyId = null;
let selectedJourneyType = null;
const currentUserId = {{ Auth::id() }};

function showStartJourneyModal(journeyId, journeyTitle, type) {
    selectedJourneyId = journeyId;
    selectedJourneyType = type;
    
    document.getElementById('journeyTitleText').textContent = journeyTitle;
    document.getElementById('journeyTypeText').textContent = type;
    
    const modal = new bootstrap.Modal(document.getElementById('startJourneyModal'));
    modal.show();
}

document.getElementById('confirmStartJourney').addEventListener('click', async function() {
    if (!selectedJourneyId || !selectedJourneyType) {
        return;
    }

    const spinner = document.getElementById('startJourneySpinner');
    const buttonText = document.getElementById('startJourneyText');
    const button = this;
    
    // Show loading state
    spinner.classList.remove('d-none');
    buttonText.textContent = 'Starting...';
    button.disabled = true;

    try {
        // Try to get or generate an API token for authorization
        let apiToken = await getOrGenerateApiToken();
        
        if (!apiToken) {
            throw new Error('Failed to get API token. Please try logging out and back in.');
        }
        
        console.log('🚀 Starting journey with API token:', apiToken.substring(0, 10) + '...');
        
        // Use the API endpoint for Bearer token authentication
        const response = await fetch('/api/start-journey', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + apiToken,
                'Accept': 'application/json'
                // No X-CSRF-TOKEN header
                // No X-Requested-With header
                // No credentials to force stateless behavior
            },
            body: JSON.stringify({
                journey_id: selectedJourneyId,
                user_id: currentUserId,
                type: selectedJourneyType
            })
        });

        console.log('🌐 Start journey response status:', response.status, response.statusText);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Start journey failed:', response.status, errorText);
            throw new Error(`Failed to start journey: ${response.status} - ${errorText}`);
        }

        const data = await response.json();

        if (data.success) {
            console.log('✅ Journey started successfully, redirecting...');
            // Redirect to the journey attempt page using the correct Laravel route
            window.location.href = data.redirect_url;
        } else {
            alert('Error: ' + (data.error || 'Failed to start journey'));
        }
    } catch (error) {
        console.error('💥 Error starting journey:', error);
        alert('Failed to start journey: ' + error.message);
    } finally {
        // Reset button state
        spinner.classList.add('d-none');
        buttonText.textContent = 'Yes, Start Journey';
        button.disabled = false;
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('startJourneyModal'));
        modal.hide();
    }
});

// Token management functions
async function getOrGenerateApiToken() {
    try {
        // First try to get an existing token from localStorage
        let apiToken = localStorage.getItem('journey_api_token');
        
        if (apiToken) {
            // Validate the stored token
            const isValid = await validateToken(apiToken);
            if (isValid) {
                return apiToken;
            } else {
                // Remove invalid token
                localStorage.removeItem('journey_api_token');
            }
        }
        
        // Try to generate a new token
        apiToken = await generateNewApiToken();
        if (apiToken) {
            localStorage.setItem('journey_api_token', apiToken);
            return apiToken;
        }
        
        return null;
    } catch (error) {
        console.error('Error managing API token:', error);
        return null;
    }
}

async function validateToken(token) {
    try {
        const response = await fetch('/api/user', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });
        return response.status === 200;
    } catch (error) {
        return false;
    }
}

async function generateNewApiToken() {
    try {
        console.log('🔑 Attempting to generate new API token...');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        if (!csrfToken) {
            console.error('❌ CSRF token not found in meta tag');
            throw new Error('CSRF token not found. Please refresh the page.');
        }
        
        console.log('✅ CSRF token found:', csrfToken.substring(0, 10) + '...');
        
        const response = await fetch('/user/api-tokens', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: 'Journey Token - ' + new Date().toISOString().slice(0, 19).replace('T', ' ')
            })
        });
        
        console.log('🌐 Token generation response status:', response.status, response.statusText);
        
        if (response.ok) {
            const data = await response.json();
            console.log('✅ Token generated successfully');
            return data.token;
        } else {
            const errorText = await response.text();
            console.error('❌ Token generation failed:', response.status, errorText);
            throw new Error(`Failed to generate token: ${response.status} - ${errorText}`);
        }
    } catch (error) {
        console.error('💥 Error generating API token:', error);
        alert('Failed to generate API token. Please check the console for details and try refreshing the page.');
        return null;
    }
}
</script>
@endsection
