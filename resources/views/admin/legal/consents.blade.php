@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="section-title mb-1">Legal Documents</p>
            <h2 class="fw-bold mb-1">Consent Records</h2>
            <p class="text-muted mb-0">{{ $document->title }} (v{{ $document->version }})</p>
        </div>
        <a href="{{ route('admin.legal.index') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <div class="glass-card">
        <div class="card-body p-4">
            @if ($consents->count())
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th class="text-center">Accepted</th>
                                <th>IP Address</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($consents as $consent)
                                <tr>
                                    <td>{{ $consent->user->name ?? '—' }}</td>
                                    <td>{{ $consent->user->email ?? '—' }}</td>
                                    <td class="text-center">
                                        @if ($consent->accepted)
                                            <i class="bi bi-check-circle text-success"></i>
                                        @else
                                            <i class="bi bi-x-circle text-danger"></i>
                                        @endif
                                    </td>
                                    <td><small class="text-muted">{{ $consent->ip_address ?? '—' }}</small></td>
                                    <td><small class="text-muted">{{ $consent->created_at->format('M d, Y H:i') }}</small></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $consents->links() }}
                </div>
            @else
                <p class="text-muted mb-0">No consent records yet for this document.</p>
            @endif
        </div>
    </div>

</div>
</div>
</section>
@endsection
