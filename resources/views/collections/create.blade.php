@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-plus-lg"></i> Create Collection
                </h1>
                <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Collections
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="{{ route('collections.store') }}" method="POST">
                                @csrf

                                <div class="mb-3">
                                    <label for="name" class="form-label">Collection Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name') }}" 
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
                                              required>{{ old('description') }}</textarea>
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
                                                        {{ old('institution_id') == $institution->id ? 'selected' : '' }}>
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
                                                        {{ old('editor_id') == $editor->id ? 'selected' : '' }}>
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
                                               {{ old('is_active', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Active collections are visible to users and can contain journeys.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('collections.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Create Collection
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
