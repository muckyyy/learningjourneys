@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">
            <!-- Hero Section -->
            <div class="bg-primary text-white p-5 rounded shadow mb-5">
                <div class="text-center">
                    <h1 class="display-4 mb-4">
                        <i class="bi bi-mortarboard"></i> Welcome to Learning Journeys
                    </h1>
                    <p class="lead mb-4">
                        Embark on personalized learning experiences designed to help you grow and achieve your educational goals.
                    </p>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="{{ route('login') }}" class="btn btn-light btn-lg me-md-2">
                            <i class="bi bi-box-arrow-in-right"></i> Login
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-person-plus"></i> Register
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Features Section -->
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-people-fill text-primary fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">For Learners</h5>
                            <p class="card-text text-muted">Take interactive journeys, track your progress, and achieve your learning goals with personalized content.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-pencil-square text-success fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">For Educators</h5>
                            <p class="card-text text-muted">Create engaging content, manage collections, and monitor learner progress with powerful authoring tools.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 text-center border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                <i class="bi bi-building text-warning fs-1"></i>
                            </div>
                            <h5 class="card-title fw-bold">For Institutions</h5>
                            <p class="card-text text-muted">Manage your educational programs, assign editors, and track institutional progress at scale.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats Section -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center p-5">
                            <h3 class="mb-4">Why Choose Learning Journeys?</h3>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="h2 text-primary fw-bold">Interactive</div>
                                    <p class="text-muted small">Engaging multimedia content</p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="h2 text-success fw-bold">Flexible</div>
                                    <p class="text-muted small">Learn at your own pace</p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="h2 text-warning fw-bold">Trackable</div>
                                    <p class="text-muted small">Monitor progress and performance</p>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="h2 text-info fw-bold">Scalable</div>
                                    <p class="text-muted small">Works for individuals and institutions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
