@extends('layouts.app')

@section('content')
@php
    $user = Auth::user();
    $hasCompleted = isset($profileFields) ? $user->hasCompletedRequiredProfileFields() : true;
    $missingFields = isset($profileFields) ? $user->getMissingRequiredProfileFields() : [];
    $completedJourneys = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'completed')->count();
    $inProgressJourneys = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'in_progress')->count();
    $totalAttempts = App\Models\JourneyAttempt::where('user_id', $user->id)->count();
    $avgScore = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'completed')->avg('score');
    $recentAttempts = App\Models\JourneyAttempt::where('user_id', $user->id)->with('journey')->orderByDesc('created_at')->take(5)->get();
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ');
    $firstName = $firstName->isNotEmpty() ? $firstName : $user->name;
@endphp

<section class="shell">
    {{-- Profile details --}}
    <div class="glass-card mb-4">
            <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                <div>
                    <p class="text-uppercase small text-muted mb-1 letter-spacing-wide">Account</p>
                    <h4 class="mb-0">Profile details</h4>
                </div>
            </div>
            <div class="profile-info-grid">
                <div class="info-pill">
                    <span class="info-label">Name</span>
                    <span class="info-value">{{ $user->name }}</span>
                </div>
                <div class="info-pill">
                    <span class="info-label">Email</span>
                    <span class="info-value">{{ $user->email }}</span>
                </div>
                <div class="info-pill">
                    <span class="info-label">Role</span>
                    <span class="info-value">{{ $user->role_label }}</span>
                </div>
                @if($user->institution)
                    <div class="info-pill">
                        <span class="info-label">Institution</span>
                        <span class="info-value">{{ $user->institution->name }}</span>
                    </div>
                @endif
                <div class="info-pill">
                    <span class="info-label">Last login</span>
                    <span class="info-value">{{ $user->updated_at->diffForHumans() }}</span>
                </div>
            </div>

            @if(isset($profileFields) && $profileFields->count())
                <hr class="my-4 opacity-10">
                <div>
                    <p class="text-uppercase small text-muted mb-3 letter-spacing-wide">Custom fields</p>
                    <div class="custom-field-grid">
                        @foreach($profileFields as $field)
                            @php
                                $value = $user->getProfileValue($field->short_name);
                            @endphp
                            <div class="custom-field">
                                <span class="info-label">{{ $field->name }}</span>
                                @if($value !== null && $value !== '')
                                    @if($field->input_type === 'select_multiple' && is_array($value))
                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                            @foreach($value as $option)
                                                <span class="pill sm muted">{{ $option }}</span>
                                            @endforeach
                                        </div>
                                    @elseif($field->input_type === 'textarea')
                                        <div class="custom-field-text">{{ $value }}</div>
                                    @else
                                        <span class="info-value">{{ $value }}</span>
                                    @endif
                                @else
                                    <span class="info-value text-muted fst-italic">Not filled</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
    </div>

    {{-- Recent attempts --}}
    @if($recentAttempts->count())
        <div class="glass-card recent-table mb-4">
                <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
                    <div>
                        <p class="text-uppercase small text-muted mb-1 letter-spacing-wide">Timeline</p>
                        <h4 class="mb-0">Recent attempts</h4>
                    </div>
                    <a href="{{ route('journeys.index') }}" class="btn btn-outline-dark rounded-pill btn-sm">
                        <i class="bi bi-collection"></i> Browse journeys
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Journey</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Score</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAttempts as $attempt)
                                <tr>
                                    <td class="fw-semibold" data-label="Journey">{{ $attempt->journey->title ?? 'Deleted journey' }}</td>
                                    <td data-label="Status">
                                        @if($attempt->status === 'completed')
                                            <span class="badge bg-success rounded-pill">Completed</span>
                                        @elseif($attempt->status === 'in_progress')
                                            <span class="badge bg-warning rounded-pill">In progress</span>
                                        @else
                                            <span class="badge bg-secondary rounded-pill">{{ ucfirst($attempt->status) }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Started">{{ $attempt->started_at ? $attempt->started_at->format('M d, Y') : $attempt->created_at->format('M d, Y') }}</td>
                                    <td data-label="Score">{{ $attempt->score ? number_format($attempt->score, 1) . '%' : '—' }}</td>
                                    <td class="text-end" data-label="Action">
                                        @if($attempt->status === 'in_progress')
                                            <a href="{{ route('journeys.' . $attempt->type, $attempt) }}" class="btn btn-sm btn-dark">Continue</a>
                                        @elseif($attempt->journey)
                                            <a href="{{ route('journeys.show', $attempt->journey) }}" class="btn btn-sm btn-outline-dark">View</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
        </div>
    @endif

    {{-- Account controls --}}
    <div class="glass-card">
            <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                <div>
                    <p class="text-uppercase small text-muted mb-1 letter-spacing-wide">Actions</p>
                    <h4 class="mb-0"><i class="bi bi-gear"></i> Account controls</h4>
                </div>
            </div>
            <div class="account-actions">
                <a href="{{ route('profile.edit') }}" class="action-chip"><i class="bi bi-pencil"></i> Edit profile</a>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="action-chip"><i class="bi bi-lock"></i> Change password</a>
                @endif
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="action-chip action-chip--danger" onclick="return confirm('Are you sure you want to logout?')"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
    </div>
</section>
@endsection
