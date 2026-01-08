@extends('layouts.app')

@push('styles')
<style>
.collection-create-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.collection-create-hero {
    background: linear-gradient(135deg, #0f172a, #22d3ee 70%);
    border-radius: 38px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(34, 211, 238, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.35);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.collection-create-hero h1 {
    font-size: clamp(2rem, 4.2vw, 3rem);
    margin-bottom: 0.35rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.8rem;
    font-weight: 600;
}
.glass-form-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    padding: clamp(1.75rem, 4vw, 3rem);
}
.form-grid {
    display: grid;
    gap: 1.5rem;
}
.form-control,
.form-select,
textarea {
    border-radius: 18px;
    padding: 0.95rem 1.1rem;
}
.form-section-title {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    color: #94a3b8;
}
.actions-row {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.actions-row .btn {
    border-radius: 999px;
    padding: 0.7rem 1.6rem;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .actions-row { flex-direction: column; }
    .actions-row .btn { width: 100%; }
}
</style>
@endpush

@section('content')
<section class="collection-create-shell">
    <div class="collection-create-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-plus-lg"></i> New collection</div>
            <h1>Spin up curated bundles for every institution.</h1>
            <p class="mb-0">Pair editors, brand the hero state, and keep journeys grouped by strategy—all from one glass form.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('collections.index') }}" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to collections
            </a>
        </div>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Collection details</p>
        <h2 class="h4 mb-4">Identity + ownership</h2>
        <form action="{{ route('collections.store') }}" method="POST" class="form-grid">
            @csrf

            <div>
                <label for="name" class="form-label">Collection name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
                    <select class="form-select @error('institution_id') is-invalid @enderror" id="institution_id" name="institution_id" required>
                        <option value="">Select institution</option>
                        @foreach($institutions as $institution)
                            <option value="{{ $institution->id }}" {{ old('institution_id') == $institution->id ? 'selected' : '' }}>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="editor_ids" class="form-label">Assigned editors <span class="text-danger">*</span></label>
                    @php($selectedEditorIds = old('editor_ids', []))
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
                            @if(!in_array($editor->id, $selectedEditorIds, true))
                                <option value="{{ $editor->id }}" selected>
                                    {{ $editor->name }} • {{ $editor->email }}
                                </option>
                            @endif
                        @endforeach
                    </select>
                    <div class="form-text">Hold Ctrl/Cmd to select multiple editors. Only members of the selected institution are listed.</div>
                    @error('editor_ids')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div>
                <label for="color_theme" class="form-label">Color theme</label>
                <input type="text" class="form-control @error('color_theme') is-invalid @enderror" id="color_theme" name="color_theme" value="{{ old('color_theme') }}" placeholder="#1D4ED8">
                <div class="form-text">Use a hex value (e.g., #1D4ED8) to brand the collection.</div>
                @error('color_theme')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="hero_title" class="form-label">Hero title</label>
                <input type="text" class="form-control @error('hero_title') is-invalid @enderror" id="hero_title" name="hero_title" value="{{ old('hero_title') }}">
                @error('hero_title')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="hero_subtitle" class="form-label">Hero subtitle</label>
                <textarea class="form-control @error('hero_subtitle') is-invalid @enderror" id="hero_subtitle" name="hero_subtitle" rows="2">{{ old('hero_subtitle') }}</textarea>
                @error('hero_subtitle')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active collection</label>
                </div>
                <div class="form-text">Active collections are visible to editors and learners.</div>
            </div>

            <div class="actions-row">
                <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Create collection
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
