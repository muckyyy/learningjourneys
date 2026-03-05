@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <a href="{{ route('admin.settings.index') }}" class="text-muted text-decoration-none small">
                <i class="bi bi-arrow-left"></i> Back to Settings
            </a>
            <h2 class="fw-bold mt-2 mb-1">Prompt Management</h2>
            <p class="text-muted mb-0">Configure the AI prompts used across the platform. Changes take effect immediately.</p>
        </div>
    </div>

    <form action="{{ route('admin.settings.prompts.update') }}" method="POST">
        @csrf

        @foreach ($definitions as $def)
            <div class="glass-card mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="fw-semibold mb-1">
                                <i class="bi {{ $def['icon'] }}"></i> {{ $def['label'] }}
                            </h5>
                            <p class="text-muted small mb-0">{{ $def['description'] }}</p>
                        </div>
                        @if ($def['value'] !== null)
                            <span class="badge bg-primary rounded-pill">customised</span>
                        @endif
                    </div>

                    <textarea
                        name="prompts[{{ $def['key'] }}]"
                        class="form-control font-monospace mt-3"
                        rows="{{ $def['rows'] }}"
                        placeholder="Using default..."
                    >{{ $def['value'] ?? $def['default'] }}</textarea>

                    @if ($def['value'] !== null)
                        <div class="mt-2 text-end">
                            <button type="button"
                                    class="btn btn-sm btn-outline-secondary rounded-pill js-reset-prompt"
                                    data-key="{{ $def['key'] }}">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset to default
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-check-lg"></i> Save All Prompts
            </button>
        </div>
    </form>

    {{-- Hidden reset form --}}
    <form id="resetForm" action="{{ route('admin.settings.prompts.reset') }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
        <input type="hidden" name="key" id="resetKey">
    </form>

</div>
</div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-reset-prompt').forEach(function (btn) {
    btn.addEventListener('click', function () {
        if (!confirm('Reset this prompt back to the built-in default?')) return;
        document.getElementById('resetKey').value = this.dataset.key;
        document.getElementById('resetForm').submit();
    });
});
</script>
@endpush
