@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-pencil"></i> Edit Collection
                </h1>
                <div>
                    <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Collection
                    </a>
                    <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-list"></i> All Collections
                    </a>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="{{ route('collections.update', $collection) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="mb-3">
                                    <label for="name" class="form-label">Collection Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $collection->name) }}" 
                                           required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" 
                                              name="description" 
                                              rows="4" 
                                              required>{{ old('description', $collection->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
                                        <select class="form-select @error('institution_id') is-invalid @enderror" 
                                                id="institution_id" 
                                                name="institution_id" 
                                                required>
                                            <option value="">Select Institution</option>
                                            @foreach($institutions as $institution)
                                                <option value="{{ $institution->id }}" 
                                                        {{ old('institution_id', $collection->institution_id) == $institution->id ? 'selected' : '' }}>
                                                    {{ $institution->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('institution_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="editor_id" class="form-label">Editor <span class="text-danger">*</span></label>
                                        <select class="form-select @error('editor_id') is-invalid @enderror" 
                                                id="editor_id" 
                                                name="editor_id" 
                                                required>
                                            <option value="">Select Editor</option>
                                            @foreach($editors as $editor)
                                                <option value="{{ $editor->id }}" 
                                                        {{ old('editor_id', $collection->editor_id) == $editor->id ? 'selected' : '' }}>
                                                    {{ $editor->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('editor_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_active" 
                                               name="is_active" 
                                               value="1" 
                                               {{ old('is_active', $collection->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Active collections are visible to users and can contain journeys.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between">
                                    <div>
                                        @can('delete', $collection)
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                                <i class="bi bi-trash"></i> Delete Collection
                                            </button>
                                        @endcan
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('collections.show', $collection) }}" class="btn btn-secondary">
                                            <i class="bi bi-x-lg"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> Update Collection
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    @if($collection->journeys->count() > 0)
                        <!-- Journeys in Collection -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-map"></i> Journeys in this Collection ({{ $collection->journeys->count() }})
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    @foreach($collection->journeys as $journey)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $journey->title }}</strong>
                                                <div class="d-flex gap-2 mt-1">
                                                    <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                                        {{ ucfirst($journey->difficulty_level) }}
                                                    </span>
                                                    @if($journey->is_published)
                                                        <span class="badge bg-success">Published</span>
                                                    @else
                                                        <span class="badge bg-warning">Draft</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                @can('update', $journey)
                                                    <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-secondary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                @endcan
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
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
                        You must delete or move all journeys before deleting this collection.
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
