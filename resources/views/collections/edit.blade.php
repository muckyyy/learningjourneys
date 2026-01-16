@extends('layouts.app')

@section('content')
@php
    $journeyCount = $collection->journeys->count();
    $publishedCount = $collection->journeys->where('is_published', true)->count();
    $draftCount = $journeyCount - $publishedCount;
    $selectedEditorIds = old('editor_ids', $selectedEditors->pluck('id')->all());
    $groupEditorIds = $editorGroups->flatMap(fn($group) => $group->members)->pluck('id')->all();
@endphp

<div class="shell">
    <div class="hero blue">
        <div class="hero-content">
            <a href="{{ route('collections.index') }}" class="ghost-link d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Collections
            </a>
            <div class="hero-heading">
                <div>
                    <h1 class="hero-title mb-1">Edit {{ $collection->name }}</h1>
                    <span class="accent-pill">
                        <i class="bi bi-toggle-{{ $collection->is_active ? 'on' : 'off' }}"></i>
                        {{ $collection->is_active ? 'Active' : 'Inactive' }} collection
                    </span>
                </div>
                <div class="hero-actions">
                    <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-light">
                        <i class="bi bi-eye"></i> Preview
                    </a>
                    <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-light text-dark">
                        <i class="bi bi-plus-lg"></i> Add Journey
                    </a>
                </div>
            </div>
            <p class="text-white-75 mb-3" style="max-width: 640px;">Reposition institutional voice, editors, and visibility in one premium control surface.</p>
            <div class="edit-stats">
                <div class="edit-stat">
                    <small>Total journeys</small>
                    <h3>{{ $journeyCount }}</h3>
                </div>
                <div class="edit-stat">
                    <small>Published</small>
                    <h3>{{ $publishedCount }}</h3>
                </div>
                <div class="edit-stat">
                    <small>Drafts</small>
                    <h3>{{ max($draftCount, 0) }}</h3>
                </div>
                <div class="edit-stat">
                    <small>Institution</small>
                    <h3>{{ $collection->institution->name }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="edit-grid mt-4">
        <section class="editor-main">
            <form action="{{ route('collections.update', $collection) }}" method="POST" class="edit-panel">
                @csrf
                @method('PUT')
                <div class="panel-heading">
                    <div>
                        <h5 class="mb-1">Collection Identity</h5>
                        <p class="helper-text mb-0">Name, tone, and description that appear throughout the admin experience.</p>
                    </div>
                    <span class="accent-pill text-dark bg-light"><i class="bi bi-pencil"></i> Editable</span>
                </div>

                <div class="mb-4">
                    <label for="name" class="form-label">Collection Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $collection->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description', $collection->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="panel-heading" style="margin-top: 2rem;">
                    <div>
                        <h5 class="mb-1">Access & Alignment</h5>
                        <p class="helper-text mb-0">Connect this collection with the right institution and editors.</p>
                    </div>
                </div>

                <div class="field-grid">
                    <div>
                        <label for="institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
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

                    <div>
                        <label for="editor_ids" class="form-label">Editors <span class="text-danger">*</span></label>
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
                        <p class="helper-text mt-2">Hold Ctrl/Cmd to select multiple editors. Everyone highlighted can publish inside this collection.</p>
                        @error('editor_ids')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <label class="form-label mb-2">Visibility</label>
                    <div class="toggle-pill">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $collection->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active collection</label>
                        </div>
                        <span class="helper-text mb-0">When active, builders can add journeys here instantly.</span>
                    </div>
                </div>

                <div class="form-actions">
                    <div>
                        @can('delete', $collection)
                            <button type="button" class="btn btn-outline-danger rounded-pill" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash"></i> Delete Collection
                            </button>
                        @endcan
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-secondary rounded-pill">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>

            <div class="edit-panel">
                <div class="panel-heading">
                    <div>
                        <h5 class="mb-1">Journeys inside this collection</h5>
                        <p class="helper-text mb-0">Snapshot of every arc using this institutional blueprint.</p>
                    </div>
                    <span class="accent-pill text-dark bg-light"><i class="bi bi-map"></i> {{ $journeyCount }} journeys</span>
                </div>

                @if($journeyCount > 0)
                    <div class="journey-grid">
                        @foreach($collection->journeys as $journey)
                            <div class="journey-tile">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <h6>{{ $journey->title }}</h6>
                                        <span class="badge rounded-pill text-bg-light">{{ ucfirst($journey->difficulty_level ?? 'custom') }}</span>
                                    </div>
                                    <span class="status-chip {{ $journey->is_published ? 'published' : 'draft' }}">
                                        <i class="bi {{ $journey->is_published ? 'bi-check-circle' : 'bi-pencil' }}"></i>
                                        {{ $journey->is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-0">{{ \Illuminate\Support\Str::limit($journey->description, 100) }}</p>
                                <div class="meta">
                                    <span><i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min</span>
                                    <span><i class="bi bi-person"></i> {{ $journey->creator->name }}</span>
                                </div>
                                <div class="journey-actions">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <span class="badge rounded-pill text-bg-light">{{ $journey->steps()->count() }} steps</span>
                                        <span class="badge rounded-pill text-bg-light">{{ $journey->attempts()->count() }} attempts</span>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-dark rounded-pill">View</a>
                                        @can('update', $journey)
                                            <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-secondary rounded-pill">Edit</a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-4">
                        <i class="bi bi-map text-primary display-6"></i>
                        <h5 class="mt-3 mb-2">No journeys added yet</h5>
                        <p class="text-muted">Use "Add Journey" to seed this collection with its first arc.</p>
                    </div>
                @endif
            </div>
        </section>

        <aside class="editor-aside">
            <div class="sticky-stack">
                <div class="editor-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Collection Snapshot</h6>
                        <span class="accent-pill" style="background: rgba(15,23,42,0.08); color: #0f172a;">
                            <i class="bi bi-eye"></i> Overview
                        </span>
                    </div>
                    <ul class="info-list">
                        <li><span class="text-muted">Institution</span> <strong>{{ $collection->institution->name }}</strong></li>
                        <li><span class="text-muted">Lead editor</span> <strong>{{ optional($collection->editor)->name ?? 'Unassigned' }}</strong></li>
                        <li><span class="text-muted">Created</span> <strong>{{ $collection->created_at->format('M d, Y') }}</strong></li>
                        <li><span class="text-muted">Updated</span> <strong>{{ $collection->updated_at->diffForHumans() }}</strong></li>
                    </ul>
                </div>

                <div class="editor-card light">
                    <h6>Editing Tips</h6>
                    <ul class="info-list">
                        <li><i class="bi bi-lightning-charge text-warning"></i> Keep descriptions action-oriented for AI briefs.</li>
                        <li><i class="bi bi-people text-primary"></i> Rotate editors per institution for coverage.</li>
                        <li><i class="bi bi-phone text-success"></i> Plan 3-4 steps per arc for mobile sessions.</li>
                    </ul>
                </div>

                @can('delete', $collection)
                    <div class="editor-card danger-card">
                        <h6 class="mb-2">Danger Zone</h6>
                        <p class="small mb-3">Delete this collection only when it no longer anchors journeys. Move or archive those journeys first.</p>
                        <button type="button" class="btn btn-outline-danger w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i> Delete Collection
                        </button>
                    </div>
                @endcan
            </div>
        </aside>
    </div>
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
