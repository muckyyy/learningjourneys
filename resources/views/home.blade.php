@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <!-- Welcome Section -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-house-heart"></i> Welcome, {{ Auth::user()->name }}!</h4>
                </div>
                <div class="card-body">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    <div class="row">
                        <div class="col-md-8">
                            <h5>Ready to Continue Your Learning Journey?</h5>
                            <p class="text-muted">Welcome to your learning hub. From here you can start new journeys, continue your progress, and track your achievements.</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">
                                <i class="bi bi-play-circle"></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card border-primary">
                        <div class="card-body text-center">
                            <i class="bi bi-mortarboard fs-1 text-primary"></i>
                            <h5 class="card-title mt-2">Learning Journeys</h5>
                            <p class="card-text">Explore structured learning paths designed to help you grow.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-success">
                        <div class="card-body text-center">
                            <i class="bi bi-trophy fs-1 text-success"></i>
                            <h5 class="card-title mt-2">Track Progress</h5>
                            <p class="card-text">Monitor your learning progress and celebrate achievements.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-info">
                        <div class="card-body text-center">
                            <i class="bi bi-chat-square-text fs-1 text-info"></i>
                            <h5 class="card-title mt-2">AI Assistance</h5>
                            <p class="card-text">Get personalized guidance and feedback on your journey.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-lg w-100 mb-3">
                                <i class="bi bi-speedometer2"></i> Go to Dashboard
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary btn-lg w-100 mb-3">
                                <i class="bi bi-person-circle"></i> View Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
