@extends('layouts.app')

@section('content')
@php
    $journeyCount = $collection->journeys->count();
    $publishedCount = $collection->journeys->where('is_published', true)->count();
    $draftCount = $journeyCount - $publishedCount;
    $selectedEditorIds = old('editor_ids', $selectedEditors->pluck('id')->all());
    $groupEditorIds = $editorGroups->flatMap(fn($group) => $group->members)->pluck('id')->all();
@endphp

<div class="shell" style="max-width: 780px;">

    {{-- Header --}}
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="{{ route('collections.show', $collection) }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Back to collection
            </a>
        </div>
        <h2 class="fw-bold mb-1" style="color: var(--lj-ink); letter-spacing: -0.02em;">Edit {{ $collection->name }}</h2>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Update collection details, editors, and settings.</p>
    </header>

    <form action="{{ route('collections.update', $collection) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="glass-card">

        {{-- Collection Identity --}}
        <section class="mb-4">
            <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Collection Identity</h5>

            <div class="mb-3">
                <label for="name" class="form-label fw-medium">Collection Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $collection->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-medium">Description <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description', $collection->description) }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="certificate_prompt" class="form-label fw-medium">Certificate Prompt</label>
                <textarea class="form-control @error('certificate_prompt') is-invalid @enderror" id="certificate_prompt" name="certificate_prompt" rows="4" placeholder="Optional guidance for certificate text or AI instructions">{{ old('certificate_prompt', $collection->certificate_prompt ?? $defaultCertificatePrompt) }}</textarea>
                <small class="text-muted d-block mt-1">These instructions travel with every certificate generated from this collection.</small>
                @error('certificate_prompt')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="certificate_id" class="form-label fw-medium">Certificate Template</label>
                <select class="form-select @error('certificate_id') is-invalid @enderror" id="certificate_id" name="certificate_id">
                    <option value="">No certificate attached</option>
                    @foreach($certificates as $certificate)
                        <option value="{{ $certificate->id }}" {{ old('certificate_id', $collection->certificate_id) == $certificate->id ? 'selected' : '' }}>
                            {{ $certificate->name }}
                        </option>
                    @endforeach
                </select>
                <small class="text-muted d-block mt-1">Pick which certificate design issues automatically for journeys under this collection.</small>
                @error('certificate_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </section>

        {{-- Access & Alignment --}}
        <section class="mb-4 pt-3" style="border-top: 1px solid rgba(15,23,42,0.08);">
            <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Access & Alignment</h5>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="institution_id" class="form-label fw-medium">Institution <span class="text-danger">*</span></label>
                    <select class="form-select @error('institution_id') is-invalid @enderror" id="institution_id" name="institution_id" required>
                        <option value="">Select Institution</option>
                        @foreach($institutions as $institution)
                            <option value="{{ $institution->id }}" {{ old('institution_id', $collection->institution_id) == $institution->id ? 'selected' : '' }}>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6">
                    <label for="editor_ids" class="form-label fw-medium">Editors <span class="text-danger">*</span></label>
                    <select class="form-select @error('editor_ids') is-invalid @enderror" id="editor_ids" name="editor_ids[]" multiple required>
                        @foreach($editorGroups as $group)
                            <optgroup label="{{ $group->name }}">
                                @foreach($group->members as $editor)
                                    <option value="{{ $editor->id }}" {{ in_array($editor->id, $selectedEditorIds, true) ? 'selected' : '' }}>
                                        {{ $editor->name }} • {{ $editor->email }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        @foreach($selectedEditors as $editor)
                            @if(!in_array($editor->id, $groupEditorIds, true))
                                <option value="{{ $editor->id }}" selected>
                                    {{ $editor->name }} • {{ $editor->email }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <small class="text-muted d-block mt-1">Hold Ctrl/Cmd to select multiple editors.</small>
                    @error('editor_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex align-items-center gap-3 p-3 rounded-4" style="background: #f8fafc;">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $collection->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-medium" for="is_active">Active collection</label>
                </div>
                <small class="text-muted">When active, builders can add journeys here.</small>
            </div>
        </section>

        {{-- Actions --}}
        <div class="d-flex justify-content-between align-items-center pt-3 mt-2" style="border-top: 1px solid rgba(15,23,42,0.06);">
            <div>
                @can('delete', $collection)
                    <button type="button" class="btn btn-outline-danger rounded-pill" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i> Delete
                    </button>
                @endcan
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-secondary rounded-pill">
                    Cancel
                </a>
                <button type="submit" class="btn btn-primary rounded-pill">
                    <i class="bi bi-check-lg"></i> Save Changes
                </button>
            </div>
        </div>

        </div>{{-- /glass-card --}}
    </form>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Collection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this collection?</p>
                <p class="text-danger"><strong>{{ $collection->name }}</strong></p>
                @if($collection->journeys->count() > 0)
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        This collection contains {{ $collection->journeys->count() }} {{ Str::plural('journey', $collection->journeys->count()) }}.
                        Move or delete those journeys before removing the collection.
                    </div>
                @else
                    <p class="text-muted">This action cannot be undone.</p>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                @if($collection->journeys->count() == 0)
                    <form action="{{ route('collections.destroy', $collection) }}" method="POST" style="display: inline;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete Collection</button>
                    </form>
                @else
                    <button type="button" class="btn btn-danger" disabled>Cannot Delete</button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
