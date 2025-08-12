@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="bi bi-person-circle"></i> My Profile
                        </h4>
                        @if(isset($profileFields) && $profileFields->count() > 0)
                            @php
                                $hasCompleted = Auth::user()->hasCompletedRequiredProfileFields();
                                $missingFields = Auth::user()->getMissingRequiredProfileFields();
                            @endphp
                            @if($hasCompleted)
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Profile Complete
                                </span>
                            @else
                                <span class="badge bg-warning">
                                    <i class="bi bi-exclamation-triangle"></i> {{ count($missingFields) }} Required Field{{ count($missingFields) > 1 ? 's' : '' }} Missing
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <i class="bi bi-person-circle display-1 text-muted"></i>
                            </div>
                            <h5>{{ Auth::user()->name }}</h5>
                            <p class="text-muted">{{ Auth::user()->role_label }}</p>
                        </div>
                        <div class="col-md-8">
                            <h6 class="text-primary">Profile Information</h6>
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <td><strong>Name:</strong></td>
                                        <td>{{ Auth::user()->name }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>{{ Auth::user()->email }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Role:</strong></td>
                                        <td>
                                            <span class="badge bg-primary">{{ Auth::user()->role_label }}</span>
                                        </td>
                                    </tr>
                                    @if(Auth::user()->institution)
                                        <tr>
                                            <td><strong>Institution:</strong></td>
                                            <td>{{ Auth::user()->institution->name }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td><strong>Member Since:</strong></td>
                                        <td>{{ Auth::user()->created_at->format('F j, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Last Login:</strong></td>
                                        <td>{{ Auth::user()->updated_at->diffForHumans() }}</td>
                                    </tr>
                                    @if(isset($profileFields) && $profileFields->count() > 0)
                                        @foreach($profileFields as $field)
                                            @php
                                                $value = Auth::user()->getProfileValue($field->short_name);
                                            @endphp
                                            @if($value !== null && $value !== '')
                                                <tr>
                                                    <td><strong>{{ $field->name }}:</strong></td>
                                                    <td>
                                                        @if($field->input_type === 'select_multiple' && is_array($value))
                                                            {{ implode(', ', $value) }}
                                                        @else
                                                            {{ $value }}
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif
                                </tbody>
                            </table>
                            
                            <!-- Always show Edit Profile button -->
                            <div class="mt-3">
                                <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Edit Profile
                                </a>
                                @if(isset($profileFields) && $profileFields->count() > 0)
                                    <small class="text-muted ms-2">Update your custom profile information</small>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Profile Fields Section -->
            @if(isset($profileFields) && $profileFields->count() > 0)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-lines-fill"></i> Additional Profile Information
                        </h5>
                    </div>
                    <div class="card-body">
                        @php
                            $hasCustomValues = false;
                        @endphp
                        <div class="row">
                            @foreach($profileFields as $field)
                                @php
                                    $value = Auth::user()->getProfileValue($field->short_name);
                                    if ($value !== null && $value !== '') {
                                        $hasCustomValues = true;
                                    }
                                @endphp
                                <div class="col-md-6 mb-3">
                                    <strong>{{ $field->name }}:</strong>
                                    @if($value !== null && $value !== '')
                                        <div class="mt-1">
                                            @if($field->input_type === 'select_multiple' && is_array($value))
                                                <span class="badge bg-secondary">{{ implode('</span> <span class="badge bg-secondary">', $value) }}</span>
                                            @elseif($field->input_type === 'textarea')
                                                <div class="border p-2 rounded bg-light">{{ $value }}</div>
                                            @else
                                                <span class="text-primary">{{ $value }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="text-muted mt-1">
                                            <em>Not filled</em>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-3 text-center">
                            <a href="{{ route('profile.edit') }}" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> {{ $hasCustomValues ? 'Update' : 'Fill Out' }} Additional Information
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            @if(Auth::user()->role === 'regular')
                <!-- Learning Statistics for Regular Users -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-graph-up"></i> Learning Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        @php
                            $completedJourneys = App\Models\JourneyAttempt::where('user_id', Auth::id())
                                ->where('status', 'completed')
                                ->count();
                            
                            $inProgressJourneys = App\Models\JourneyAttempt::where('user_id', Auth::id())
                                ->where('status', 'in_progress')
                                ->count();
                                
                            $totalAttempts = App\Models\JourneyAttempt::where('user_id', Auth::id())
                                ->count();
                                
                            $avgScore = App\Models\JourneyAttempt::where('user_id', Auth::id())
                                ->where('status', 'completed')
                                ->avg('score');
                        @endphp
                        
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h3>{{ $completedJourneys }}</h3>
                                        <p class="mb-0">Completed Journeys</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <h3>{{ $inProgressJourneys }}</h3>
                                        <p class="mb-0">In Progress</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <h3>{{ $totalAttempts }}</h3>
                                        <p class="mb-0">Total Attempts</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <h3>{{ $avgScore ? number_format($avgScore, 1) . '%' : 'N/A' }}</h3>
                                        <p class="mb-0">Average Score</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Journey Attempts -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history"></i> Recent Journey Attempts
                        </h5>
                    </div>
                    <div class="card-body">
                        @php
                            $recentAttempts = App\Models\JourneyAttempt::where('user_id', Auth::id())
                                ->with('journey')
                                ->orderBy('created_at', 'desc')
                                ->take(5)
                                ->get();
                        @endphp
                        
                        @if($recentAttempts->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Journey</th>
                                            <th>Status</th>
                                            <th>Started</th>
                                            <th>Score</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentAttempts as $attempt)
                                            <tr>
                                                <td>{{ $attempt->journey->title }}</td>
                                                <td>
                                                    @if($attempt->status === 'completed')
                                                        <span class="badge bg-success">Completed</span>
                                                    @elseif($attempt->status === 'in_progress')
                                                        <span class="badge bg-warning">In Progress</span>
                                                    @else
                                                        <span class="badge bg-secondary">{{ ucfirst($attempt->status) }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $attempt->started_at ? $attempt->started_at->format('M d, Y') : 'Not started' }}</td>
                                                <td>{{ $attempt->score ? number_format($attempt->score, 1) . '%' : '-' }}</td>
                                                <td>
                                                    @if($attempt->status === 'in_progress')
                                                        <a href="{{ route('dashboard') }}" class="btn btn-sm btn-primary">Continue</a>
                                                    @else
                                                        <a href="{{ route('journeys.show', $attempt->journey) }}" class="btn btn-sm btn-outline-primary">View</a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted">No journey attempts yet. <a href="{{ route('journeys.index') }}">Start your first journey!</a></p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Account Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear"></i> Account Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <button class="btn btn-outline-primary w-100" onclick="alert('Profile editing functionality coming soon!')">
                                <i class="bi bi-pencil"></i> Edit Profile
                            </button>
                        </div>
                        <div class="col-md-6 mb-3">
                            <button class="btn btn-outline-secondary w-100" onclick="alert('Password change functionality coming soon!')">
                                <i class="bi bi-lock"></i> Change Password
                            </button>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-info w-100">
                                <i class="bi bi-house"></i> Back to Dashboard
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <form action="{{ route('logout') }}" method="POST" class="d-inline w-100">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Are you sure you want to logout?')">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
