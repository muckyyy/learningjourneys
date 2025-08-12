@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header">
                    <h4>API Token Management</h4>
                    <small class="text-muted">Create and manage your personal access tokens for API usage</small>
                </div>
                
                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if (session('token'))
                        <div class="alert alert-info">
                            <h6><strong>Your new API token:</strong></h6>
                            <div class="input-group">
                                <input type="text" class="form-control" value="{{ session('token') }}" id="newToken" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyToken()">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                            <small class="text-muted">
                                <strong>Important:</strong> This token will only be shown once. Make sure to copy it now!
                            </small>
                        </div>
                    @endif

                    <!-- Create New Token Form -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h5>Create New Token</h5>
                            <form action="{{ route('api-tokens.store') }}" method="POST">
                                @csrf
                                <div class="input-group">
                                    <input type="text" class="form-control" name="name" placeholder="Token name (e.g., 'Chat API Token')" required>
                                    <button class="btn btn-primary" type="submit">Create Token</button>
                                </div>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </form>
                        </div>
                    </div>

                    <!-- Existing Tokens -->
                    <div class="row">
                        <div class="col-md-12">
                            <h5>Existing Tokens</h5>
                            @if($tokens->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Created</th>
                                                <th>Last Used</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tokens as $token)
                                                <tr>
                                                    <td>{{ $token->name }}</td>
                                                    <td>{{ $token->created_at->format('M d, Y H:i') }}</td>
                                                    <td>
                                                        {{ $token->last_used_at ? $token->last_used_at->format('M d, Y H:i') : 'Never' }}
                                                    </td>
                                                    <td>
                                                        <form action="{{ route('api-tokens.destroy', $token->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to revoke this token?')">
                                                                <i class="bi bi-trash"></i> Revoke
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-muted">You don't have any API tokens yet. Create one above to get started.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Usage Instructions -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6>How to use your API tokens:</h6>
                                    <ol>
                                        <li>Create a new token using the form above</li>
                                        <li>Copy the token value (it will only be shown once)</li>
                                        <li>Use it in your API requests with the Authorization header:</li>
                                    </ol>
                                    <code>Authorization: Bearer YOUR_TOKEN_HERE</code>
                                    <br><br>
                                    <strong>Available API endpoints:</strong>
                                    <ul class="mt-2">
                                        <li><code>POST /api/chat/start</code> - Start a new chat session</li>
                                        <li><code>POST /api/chat/submit</code> - Submit user input to chat</li>
                                    </ul>
                                    <p class="mt-2">
                                        <a href="/preview-chat" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-chat-dots"></i> Test Chat API
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToken() {
    const tokenInput = document.getElementById('newToken');
    tokenInput.select();
    tokenInput.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand('copy');
    
    // Show feedback
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i> Copied!';
    button.classList.remove('btn-outline-secondary');
    button.classList.add('btn-success');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.remove('btn-success');
        button.classList.add('btn-outline-secondary');
    }, 2000);
}
</script>
@endsection
