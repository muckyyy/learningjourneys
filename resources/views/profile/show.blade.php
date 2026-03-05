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

    {{-- Referral Programme --}}
    @if(isset($referralStats) && $referralStats && $referralStats['enabled'])
    <div class="glass-card mb-4">
        <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
            <div>
                <p class="text-uppercase small text-muted mb-1 letter-spacing-wide">Earn tokens</p>
                <h4 class="mb-0"><i class="bi bi-people"></i> Referral Programme</h4>
            </div>
            @if($referralStats['rewards_earned'] > 0)
                <span class="badge bg-success rounded-pill px-3 py-2">{{ $referralStats['rewards_earned'] }} reward{{ $referralStats['rewards_earned'] > 1 ? 's' : '' }} earned</span>
            @endif
        </div>

        {{-- Referral link --}}
        <div class="p-3 rounded-3 mb-4" style="background: linear-gradient(135deg, rgba(99,102,241,.06), rgba(99,102,241,.02));">
            <label class="form-label small fw-semibold text-muted mb-1">Your referral link</label>
            <div class="input-group">
                <input type="text" class="form-control font-monospace" id="referralLink" value="{{ $referralStats['referral_link'] }}" readonly>
                <button class="btn btn-outline-primary" type="button" onclick="copyReferralLink()" id="copyBtn">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
            </div>
            <div class="form-text mt-1">Share this link. After {{ $referralStats['frequency'] }} referred users make a purchase, you earn bonus tokens.</div>
        </div>

        {{-- Progress stats --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                    <div class="fs-3 fw-bold" style="color: #6366f1;">{{ $referralStats['total_referred'] }}</div>
                    <div class="small text-muted">Total Referred</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                    <div class="fs-3 fw-bold text-success">{{ $referralStats['paid_referred'] }}</div>
                    <div class="small text-muted">Have Paid</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                    <div class="fs-3 fw-bold text-warning">{{ $referralStats['progress'] }} / {{ $referralStats['frequency'] }}</div>
                    <div class="small text-muted">Next Reward</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded-3" style="background: #f8fafc;">
                    <div class="fs-3 fw-bold text-primary">{{ $referralStats['rewards_earned'] }}</div>
                    <div class="small text-muted">Rewards Earned</div>
                </div>
            </div>
        </div>

        {{-- Progress bar --}}
        @if($referralStats['frequency'] > 0)
        @php $pct = min(100, round(($referralStats['progress'] / $referralStats['frequency']) * 100)); @endphp
        <div class="mb-4">
            <div class="d-flex justify-content-between small text-muted mb-1">
                <span>Progress toward next reward</span>
                <span>{{ $pct }}%</span>
            </div>
            <div class="progress" style="height: 10px; border-radius: 8px;">
                <div class="progress-bar" role="progressbar" style="width: {{ $pct }}%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 8px;"></div>
            </div>
        </div>
        @endif

        {{-- Recent referrals --}}
        @if($referralStats['recent_referrals']->count())
        <div>
            <p class="small fw-semibold text-muted mb-2">Recent Referrals</p>
            <div class="table-responsive">
                <table class="table table-sm table-borderless mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>User</th>
                            <th>Signed Up</th>
                            <th class="text-center">Has Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($referralStats['recent_referrals'] as $ref)
                        <tr>
                            <td>{{ $ref->referred->name ?? 'Deleted user' }}</td>
                            <td class="text-muted">{{ $ref->created_at->diffForHumans() }}</td>
                            <td class="text-center">
                                @if($ref->has_paid)
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                @else
                                    <i class="bi bi-clock text-muted"></i>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
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
                <a href="{{ route('profile.password.edit') }}" class="action-chip"><i class="bi bi-lock"></i> Change password</a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="action-chip action-chip--danger" onclick="return confirm('Are you sure you want to logout?')"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
    </div>
</section>

@if(isset($referralStats) && $referralStats && $referralStats['enabled'])
@push('scripts')
<script>
function copyReferralLink() {
    const input = document.getElementById('referralLink');
    const btn = document.getElementById('copyBtn');
    navigator.clipboard.writeText(input.value).then(function () {
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
        btn.classList.replace('btn-outline-primary', 'btn-success');
        setTimeout(function () {
            btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
            btn.classList.replace('btn-success', 'btn-outline-primary');
        }, 2000);
    });
}
</script>
@endpush
@endif
@endsection
