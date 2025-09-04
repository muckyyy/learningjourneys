@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <!-- Journey Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-compass"></i> Journey Status</h5>
                </div>
                <div class="card-body">
                    @if($activeAttempt)
                        <!-- Active Journey Section -->
                        <div class="alert alert-info mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-info-circle fs-4 me-3"></i>
                                <div>
                                    <h6 class="mb-1">You have an active journey in progress:</h6>
                                    <strong>{{ $activeAttempt->journey->title }}</strong>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <a href="{{ route('journeys.continue', $activeAttempt) }}" class="btn btn-success btn-lg w-100 mb-3">
                                    <i class="bi bi-play-circle"></i> Continue Journey
                                </a>
                            </div>
                            <div class="col-md-6">
                                <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="d-inline w-100">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-lg w-100 mb-3" 
                                            onclick="return confirm('Are you sure you want to abandon this journey? All progress will be lost.')">
                                        <i class="bi bi-x-circle"></i> Abandon Journey
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <!-- No Active Journey Section -->
                        <div class="alert alert-warning mb-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle fs-4 me-3"></i>
                                <div>
                                    <h6 class="mb-1">No active journey found</h6>
                                    <p class="mb-0">You don't have any active learning journey at the moment. Start a new journey to begin your learning experience!</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mx-auto">
                                <a href="{{ route('journeys.index') }}" class="btn btn-primary btn-lg w-100 mb-3">
                                    <i class="bi bi-list"></i> Browse Available Journeys
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
