@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">

            {{-- Header --}}
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 mb-3" style="width:64px;height:64px;">
                    <i class="bi bi-patch-check-fill text-primary" style="font-size:1.75rem;"></i>
                </div>
                <h2 class="fw-bold mb-1">Certificate Verification</h2>
                <p class="text-muted">Enter a certificate code to verify its authenticity</p>
            </div>

            {{-- Search form --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-4">
                    <form action="{{ route('certificates.verify.lookup') }}" method="POST">
                        @csrf
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input
                                type="text"
                                name="qr_code"
                                class="form-control @error('qr_code') is-invalid @enderror"
                                placeholder="Enter certificate code…"
                                value="{{ old('qr_code', $qrCode ?? '') }}"
                                autofocus
                            >
                            <button type="submit" class="btn btn-primary px-4">Verify</button>
                        </div>
                        @error('qr_code')
                            <div class="invalid-feedback d-block mt-1">{{ $message }}</div>
                        @enderror
                    </form>
                </div>
            </div>

            {{-- Results --}}
            @if ($searched ?? false)
                @if ($issue)
                    {{-- Certificate found --}}
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            {{-- Status badge --}}
                            <div class="text-center mb-4">
                                @if ($issue->isExpired())
                                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Certificate Expired
                                    </span>
                                @else
                                    <span class="badge bg-success fs-6 px-3 py-2">
                                        <i class="bi bi-check-circle me-1"></i> Valid Certificate
                                    </span>
                                @endif
                            </div>

                            {{-- Certificate details --}}
                            <div class="table-responsive">
                                <table class="table table-borderless mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted fw-medium" style="width:160px;">Recipient</td>
                                            <td class="fw-semibold">{{ $issue->user->name ?? '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted fw-medium">Certificate</td>
                                            <td>{{ $issue->certificate->name ?? '—' }}</td>
                                        </tr>
                                        @if ($issue->collection)
                                            <tr>
                                                <td class="text-muted fw-medium">Collection</td>
                                                <td>{{ $issue->collection->name }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="text-muted fw-medium">Issued On</td>
                                            <td>{{ $issue->issued_at?->format('F j, Y') ?? '—' }}</td>
                                        </tr>
                                        @if ($issue->expires_at)
                                            <tr>
                                                <td class="text-muted fw-medium">Expires On</td>
                                                <td>
                                                    {{ $issue->expires_at->format('F j, Y') }}
                                                    @if ($issue->isExpired())
                                                        <span class="text-danger ms-2">(expired)</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td class="text-muted fw-medium">Code</td>
                                            <td><code class="user-select-all">{{ $issue->qr_code }}</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Not found --}}
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4 text-center">
                            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 mb-3" style="width:56px;height:56px;">
                                <i class="bi bi-x-circle text-danger" style="font-size:1.5rem;"></i>
                            </div>
                            <h5 class="fw-bold mb-1">Certificate Not Found</h5>
                            <p class="text-muted mb-0">No certificate was found matching the code <strong>{{ $qrCode }}</strong>. Please check the code and try again.</p>
                        </div>
                    </div>
                @endif
            @endif

        </div>
    </div>
</div>
@endsection
