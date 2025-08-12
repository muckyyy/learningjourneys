@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-pencil-square"></i> Editors
                </h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEditorModal">
                    <i class="bi bi-plus-lg"></i> Create Editor
                </button>
            </div>

            @if($editors->count() > 0)
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Institution</th>
                                        <th>Collections</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($editors as $editor)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2">
                                                        {{ strtoupper(substr($editor->name, 0, 1)) }}
                                                    </div>
                                                    {{ $editor->name }}
                                                </div>
                                            </td>
                                            <td>{{ $editor->email }}</td>
                                            <td>{{ $editor->institution->name }}</td>
                                            <td>
                                                <span class="badge bg-info">{{ $editor->journeyCollections()->count() }}</span>
                                            </td>
                                            <td>
                                                @if($editor->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>{{ $editor->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    @if(auth()->user()->role === 'administrator')
                                                        <a href="{{ route('users.show', $editor) }}" class="btn btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="{{ route('users.edit', $editor) }}" class="btn btn-outline-secondary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $editors->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-pencil-square display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No editors found</h3>
                    <p class="text-muted">Editors create and manage learning journeys within collections.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEditorModal">
                        <i class="bi bi-plus-lg"></i> Create Your First Editor
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Editor Modal -->
<div class="modal fade" id="createEditorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('editors.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create Editor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="modal_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="modal_email" name="email" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="modal_password" name="password" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="modal_password_confirmation" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="modal_password_confirmation" name="password_confirmation" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_institution_id" name="institution_id" required>
                            <option value="">Select Institution</option>
                            @foreach($institutions as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Editor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}
</style>
@endsection
