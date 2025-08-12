@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-person-lines-fill"></i> Profile Fields Management</h2>
                <a href="{{ route('profile-fields.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Field
                </a>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Fields</h5>
                </div>
                <div class="card-body">
                    @if($profileFields->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Sort Order</th>
                                        <th>Name</th>
                                        <th>Short Name</th>
                                        <th>Input Type</th>
                                        <th>Required</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($profileFields as $field)
                                        <tr>
                                            <td>{{ $field->sort_order }}</td>
                                            <td>
                                                <strong>{{ $field->name }}</strong>
                                                @if($field->description)
                                                    <br><small class="text-muted">{{ $field->description }}</small>
                                                @endif
                                            </td>
                                            <td><code>{{ $field->short_name }}</code></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    {{ ucfirst(str_replace('_', ' ', $field->input_type)) }}
                                                </span>
                                                @if(in_array($field->input_type, ['select', 'select_multiple']) && $field->options)
                                                    <br><small class="text-muted">
                                                        Options: {{ implode(', ', $field->options) }}
                                                    </small>
                                                @endif
                                            </td>
                                            <td>
                                                @if($field->required)
                                                    <span class="badge bg-warning">Required</span>
                                                @else
                                                    <span class="badge bg-secondary">Optional</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($field->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('profile-fields.edit', $field) }}" 
                                                       class="btn btn-outline-primary">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <form action="{{ route('profile-fields.destroy', $field) }}" 
                                                          method="POST" class="d-inline"
                                                          onsubmit="return confirm('Are you sure you want to delete this profile field?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-person-lines-fill fs-1 text-muted"></i>
                            <h5 class="mt-3">No Profile Fields</h5>
                            <p class="text-muted">Create your first custom profile field to get started.</p>
                            <a href="{{ route('profile-fields.create') }}" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Add First Field
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
